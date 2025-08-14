<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$message = '';
$messageType = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'add_tag':
                $name = trim($_POST['name']);
                $category = trim($_POST['category']);
                $displayOrder = (int)($_POST['display_order'] ?? 0);
                
                if (empty($name) || empty($category)) {
                    throw new Exception("Name and category are required");
                }
                
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
                
                $sql = "INSERT INTO tags (name, category, slug, display_order) VALUES (?, ?, ?, ?)";
                Database::execute($sql, [$name, $category, $slug, $displayOrder]);
                
                $tagId = Database::lastInsertId();
                $logger->info("Tag created", ['tag_id' => $tagId, 'name' => $name, 'category' => $category], 'TAG_MANAGEMENT');
                
                echo json_encode(['success' => true, 'tag_id' => $tagId, 'message' => 'Tag created successfully']);
                exit;
                
            case 'update_tag':
                $tagId = (int)$_POST['tag_id'];
                $name = trim($_POST['name']);
                $category = trim($_POST['category']);
                $displayOrder = (int)($_POST['display_order'] ?? 0);
                
                if (empty($name) || empty($category)) {
                    throw new Exception("Name and category are required");
                }
                
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
                
                $sql = "UPDATE tags SET name = ?, category = ?, slug = ?, display_order = ? WHERE id = ?";
                Database::execute($sql, [$name, $category, $slug, $displayOrder, $tagId]);
                
                $logger->info("Tag updated", ['tag_id' => $tagId, 'name' => $name, 'category' => $category], 'TAG_MANAGEMENT');
                
                echo json_encode(['success' => true, 'message' => 'Tag updated successfully']);
                exit;
                
            case 'delete_tag':
                $tagId = (int)$_POST['tag_id'];
                
                // Check if tag is in use
                $usageCount = Database::execute("SELECT COUNT(*) as count FROM song_tags WHERE tag_id = ?", [$tagId])->fetch();
                
                if ($usageCount['count'] > 0) {
                    throw new Exception("Cannot delete tag: it is used by {$usageCount['count']} song(s)");
                }
                
                Database::execute("DELETE FROM tags WHERE id = ?", [$tagId]);
                
                $logger->info("Tag deleted", ['tag_id' => $tagId], 'TAG_MANAGEMENT');
                
                echo json_encode(['success' => true, 'message' => 'Tag deleted successfully']);
                exit;
                
            case 'bulk_update_order':
                $updates = json_decode($_POST['updates'], true);
                
                Database::beginTransaction();
                
                foreach ($updates as $update) {
                    $sql = "UPDATE tags SET display_order = ? WHERE id = ?";
                    Database::execute($sql, [(int)$update['order'], (int)$update['id']]);
                }
                
                Database::commit();
                
                $logger->info("Tag order updated", ['count' => count($updates)], 'TAG_MANAGEMENT');
                
                echo json_encode(['success' => true, 'message' => 'Tag order updated successfully']);
                exit;
                
            case 'merge_tags':
                $sourceTagId = (int)$_POST['source_tag_id'];
                $targetTagId = (int)$_POST['target_tag_id'];
                
                if ($sourceTagId === $targetTagId) {
                    throw new Exception("Cannot merge tag with itself");
                }
                
                Database::beginTransaction();
                
                // Update all song_tags references
                $sql = "UPDATE IGNORE song_tags SET tag_id = ? WHERE tag_id = ?";
                Database::execute($sql, [$targetTagId, $sourceTagId]);
                
                // Delete any duplicate relationships
                $sql = "DELETE FROM song_tags WHERE tag_id = ?";
                Database::execute($sql, [$sourceTagId]);
                
                // Delete the source tag
                Database::execute("DELETE FROM tags WHERE id = ?", [$sourceTagId]);
                
                Database::commit();
                
                $logger->info("Tags merged", ['source_tag_id' => $sourceTagId, 'target_tag_id' => $targetTagId], 'TAG_MANAGEMENT');
                
                echo json_encode(['success' => true, 'message' => 'Tags merged successfully']);
                exit;
                
            default:
                throw new Exception("Unknown action");
        }
        
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        
        $logger->error("Tag management error", ['action' => $_POST['action'], 'error' => $e->getMessage()], 'TAG_MANAGEMENT');
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get all tags with usage statistics
$sql = "
    SELECT 
        t.*,
        COUNT(st.song_id) as usage_count,
        GROUP_CONCAT(DISTINCT s.title ORDER BY s.title SEPARATOR ', ') as sample_songs
    FROM tags t
    LEFT JOIN song_tags st ON t.id = st.tag_id
    LEFT JOIN songs s ON st.song_id = s.id
    GROUP BY t.id
    ORDER BY t.category, t.display_order, t.name
";
$tags = Database::execute($sql)->fetchAll();

// Group tags by category
$tagsByCategory = [];
foreach ($tags as $tag) {
    $tagsByCategory[$tag['category']][] = $tag;
}

// Get available categories
$categories = array_keys($tagsByCategory);

// Get category statistics
$categoryStats = [];
foreach ($categories as $category) {
    $categoryTags = $tagsByCategory[$category];
    $categoryStats[$category] = [
        'tag_count' => count($categoryTags),
        'total_usage' => array_sum(array_column($categoryTags, 'usage_count'))
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tag Manager - Stinkin' Park Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .toolbar {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-small {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .category-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .category-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            text-transform: capitalize;
        }
        
        .category-stats {
            font-size: 12px;
            color: #666;
        }
        
        .category-actions {
            display: flex;
            gap: 10px;
        }
        
        .tags-container {
            padding: 20px;
        }
        
        .tags-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .tag-card {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            position: relative;
            transition: all 0.3s;
        }
        
        .tag-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .tag-card.editing {
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .tag-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .tag-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .tag-slug {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        
        .tag-actions {
            display: flex;
            gap: 5px;
        }
        
        .tag-stats {
            margin-bottom: 10px;
        }
        
        .usage-count {
            font-size: 14px;
            color: #667eea;
            font-weight: 600;
        }
        
        .sample-songs {
            font-size: 11px;
            color: #888;
            line-height: 1.3;
            max-height: 40px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .tag-form {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .tag-form input,
        .tag-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .drag-handle {
            cursor: move;
            color: #999;
            font-size: 12px;
            margin-right: 8px;
        }
        
        .sortable-ghost {
            opacity: 0.5;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .category-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .tags-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏷️ Tag Manager - Stinkin' Park</h1>
            <nav class="nav">
                <a href="upload.php">Single Upload</a>
                <a href="mass-upload.php">Mass Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="tag-manager.php">Manage Tags</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <div class="toolbar">
            <button class="btn btn-primary" onclick="showAddTagModal()">+ Add New Tag</button>
            <button class="btn btn-secondary" onclick="showAddCategoryModal()">+ Add Category</button>
            <button class="btn btn-warning" onclick="showMergeModal()">🔗 Merge Tags</button>
            <button class="btn btn-success" onclick="exportTags()">📤 Export Tags</button>
            <span style="margin-left: auto; font-size: 14px; color: #666;">
                Total: <?= count($tags) ?> tags across <?= count($categories) ?> categories
            </span>
        </div>

        <div class="stats-grid">
            <?php foreach ($categoryStats as $category => $stats): ?>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['tag_count'] ?></div>
                <div class="stat-label"><?= ucfirst($category) ?> Tags</div>
                <div style="font-size: 12px; color: #888; margin-top: 4px;">
                    <?= $stats['total_usage'] ?> total uses
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="alert-container"></div>

        <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
        <div class="category-section">
            <div class="category-header">
                <div>
                    <div class="category-title"><?= ucfirst($category) ?></div>
                    <div class="category-stats">
                        <?= count($categoryTags) ?> tags, <?= array_sum(array_column($categoryTags, 'usage_count')) ?> total uses
                    </div>
                </div>
                <div class="category-actions">
                    <button class="btn btn-small btn-primary" onclick="addTagToCategory('<?= $category ?>')">
                        + Add Tag
                    </button>
                    <button class="btn btn-small btn-secondary" onclick="reorderCategory('<?= $category ?>')">
                        ↕️ Reorder
                    </button>
                </div>
            </div>
            
            <div class="tags-container">
                <?php if (empty($categoryTags)): ?>
                <div class="empty-state">
                    <p>No tags in this category yet.</p>
                    <button class="btn btn-primary" onclick="addTagToCategory('<?= $category ?>')">
                        Add First Tag
                    </button>
                </div>
                <?php else: ?>
                <div class="tags-grid sortable" data-category="<?= $category ?>">
                    <?php foreach ($categoryTags as $tag): ?>
                    <div class="tag-card" data-tag-id="<?= $tag['id'] ?>">
                        <div class="tag-header">
                            <div>
                                <div class="tag-name"><?= htmlspecialchars($tag['name']) ?></div>
                                <div class="tag-slug"><?= htmlspecialchars($tag['slug']) ?></div>
                            </div>
                            <div class="tag-actions">
                                <button class="btn btn-small btn-secondary" onclick="editTag(<?= $tag['id'] ?>)">
                                    ✏️
                                </button>
                                <button class="btn btn-small btn-danger" onclick="deleteTag(<?= $tag['id'] ?>)" 
                                        <?= $tag['usage_count'] > 0 ? 'disabled title="Tag is in use"' : '' ?>>
                                    🗑️
                                </button>
                            </div>
                        </div>
                        
                        <div class="tag-stats">
                            <div class="usage-count">
                                <?= $tag['usage_count'] ?> song<?= $tag['usage_count'] !== 1 ? 's' : '' ?>
                            </div>
                            <?php if ($tag['sample_songs']): ?>
                            <div class="sample-songs" title="<?= htmlspecialchars($tag['sample_songs']) ?>">
                                <?= htmlspecialchars(substr($tag['sample_songs'], 0, 100)) ?>
                                <?= strlen($tag['sample_songs']) > 100 ? '...' : '' ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Tag Modal -->
    <div id="addTagModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('addTagModal')">&times;</span>
            <h3>Add New Tag</h3>
            <form id="addTagForm">
                <div class="form-row">
                    <input type="text" id="newTagName" placeholder="Tag Name" required>
                    <input type="number" id="newTagOrder" placeholder="Order" value="0">
                </div>
                <select id="newTagCategory" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?= $category ?>"><?= ucfirst($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">Add Tag</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addTagModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tag Modal -->
    <div id="editTagModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('editTagModal')">&times;</span>
            <h3>Edit Tag</h3>
            <form id="editTagForm">
                <input type="hidden" id="editTagId">
                <div class="form-row">
                    <input type="text" id="editTagName" placeholder="Tag Name" required>
                    <input type="number" id="editTagOrder" placeholder="Order">
                </div>
                <select id="editTagCategory" required>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?= $category ?>"><?= ucfirst($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-primary">Update Tag</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editTagModal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('addCategoryModal')">&times;</span>
            <h3>Add New Category</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Note: This will create a new category type. You'll need to add the category to your database schema manually.
            </p>
            <input type="text" id="newCategoryName" placeholder="Category Name (e.g., tempo, instrument)" 
                   style="width: 100%; padding: 8px; margin-bottom: 15px;">
            <div>
                <button class="btn btn-primary" onclick="suggestCategory()">Suggest Implementation</button>
                <button class="btn btn-secondary" onclick="closeModal('addCategoryModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Merge Tags Modal -->
    <div id="mergeTagsModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('mergeTagsModal')">&times;</span>
            <h3>Merge Tags</h3>
            <p style="margin-bottom: 15px; color: #666;">
                Merge two tags together. All songs tagged with the source tag will be moved to the target tag.
            </p>
            <select id="sourceTag" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                <option value="">Select source tag to merge from...</option>
                <?php foreach ($tags as $tag): ?>
                <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?> (<?= $tag['category'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <select id="targetTag" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                <option value="">Select target tag to merge into...</option>
                <?php foreach ($tags as $tag): ?>
                <option value="<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?> (<?= $tag['category'] ?>)</option>
                <?php endforeach; ?>
            </select>
            <div>
                <button class="btn btn-warning" onclick="mergeTags()">Merge Tags</button>
                <button class="btn btn-secondary" onclick="closeModal('mergeTagsModal')">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Utility functions
        function showAlert(message, type = 'success') {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Add Tag Functions
        function showAddTagModal(category = '') {
            if (category) {
                document.getElementById('newTagCategory').value = category;
            }
            showModal('addTagModal');
            document.getElementById('newTagName').focus();
        }

        function addTagToCategory(category) {
            showAddTagModal(category);
        }

        document.getElementById('addTagForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_tag');
            formData.append('name', document.getElementById('newTagName').value);
            formData.append('category', document.getElementById('newTagCategory').value);
            formData.append('display_order', document.getElementById('newTagOrder').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeModal('addTagModal');
                    location.reload();
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        });

        // Edit Tag Functions
        function editTag(tagId) {
            // Find tag data
            const tagCard = document.querySelector(`[data-tag-id="${tagId}"]`);
            const tagName = tagCard.querySelector('.tag-name').textContent;
            const category = tagCard.closest('.category-section').querySelector('.category-title').textContent.toLowerCase();
            
            document.getElementById('editTagId').value = tagId;
            document.getElementById('editTagName').value = tagName;
            document.getElementById('editTagCategory').value = category;
            
            showModal('editTagModal');
        }

        document.getElementById('editTagForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_tag');
            formData.append('tag_id', document.getElementById('editTagId').value);
            formData.append('name', document.getElementById('editTagName').value);
            formData.append('category', document.getElementById('editTagCategory').value);
            formData.append('display_order', document.getElementById('editTagOrder').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeModal('editTagModal');
                    location.reload();
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        });

        // Delete Tag Function
        function deleteTag(tagId) {
            if (!confirm('Are you sure you want to delete this tag?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_tag');
            formData.append('tag_id', tagId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    location.reload();
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        }

        // Category Functions
        function showAddCategoryModal() {
            showModal('addCategoryModal');
        }

        function suggestCategory() {
            const categoryName = document.getElementById('newCategoryName').value.trim();
            if (!categoryName) {
                showAlert('Please enter a category name first', 'error');
                return;
            }
            
            const suggestion = `
To add the "${categoryName}" category:

1. Update the database schema:
ALTER TABLE tags MODIFY COLUMN category ENUM('genre', 'mood', 'situational', 'style', 'intensity', '${categoryName}') NOT NULL;

2. Add it to your code documentation and forms.

3. Reload this page to see the new category option.
            `;
            
            alert(suggestion);
            closeModal('addCategoryModal');
        }

        // Merge Tags Functions
        function showMergeModal() {
            showModal('mergeTagsModal');
        }

        function mergeTags() {
            const sourceTagId = document.getElementById('sourceTag').value;
            const targetTagId = document.getElementById('targetTag').value;
            
            if (!sourceTagId || !targetTagId) {
                showAlert('Please select both source and target tags', 'error');
                return;
            }
            
            if (sourceTagId === targetTagId) {
                showAlert('Source and target tags cannot be the same', 'error');
                return;
            }
            
            if (!confirm('This will merge the tags and cannot be undone. Continue?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'merge_tags');
            formData.append('source_tag_id', sourceTagId);
            formData.append('target_tag_id', targetTagId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message);
                    closeModal('mergeTagsModal');
                    location.reload();
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        }

        // Export Functions
        function exportTags() {
            const tags = <?= json_encode($tags) ?>;
            const csv = 'Name,Category,Slug,Usage Count,Sample Songs\n' + 
                tags.map(tag => `"${tag.name}","${tag.category}","${tag.slug}",${tag.usage_count},"${tag.sample_songs || ''}"`).join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'stinkin-park-tags.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            
            showAlert('Tags exported successfully');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>