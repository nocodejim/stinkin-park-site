<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Song;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$message = '';
$messageType = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'bulk_update':
                $songIds = json_decode($_POST['song_ids'], true);
                $updates = json_decode($_POST['updates'], true);
                
                if (empty($songIds) || empty($updates)) {
                    throw new Exception("No songs or updates specified");
                }
                
                Database::beginTransaction();
                
                $song = new Song();
                $updateCount = 0;
                
                foreach ($songIds as $songId) {
                    $songData = [];
                    
                    if (isset($updates['active'])) {
                        $songData['active'] = (int)$updates['active'];
                    }
                    
                    if (!empty($updates['title_prefix'])) {
                        $currentSong = $song->getById($songId);
                        $songData['title'] = $updates['title_prefix'] . $currentSong['title'];
                    }
                    
                    if (!empty($updates['title_suffix'])) {
                        $currentSong = $song->getById($songId);
                        $songData['title'] = $currentSong['title'] . $updates['title_suffix'];
                    }
                    
                    if (!empty($songData)) {
                        $song->update($songId, $songData);
                        $updateCount++;
                    }
                    
                    // Handle tag operations
                    if (!empty($updates['add_tags'])) {
                        $currentSong = $song->getById($songId);
                        $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                        $newTags = array_unique(array_merge($currentTags, $updates['add_tags']));
                        $song->attachTags($songId, array_map('intval', $newTags));
                    }
                    
                    if (!empty($updates['remove_tags'])) {
                        $currentSong = $song->getById($songId);
                        $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                        $newTags = array_diff($currentTags, $updates['remove_tags']);
                        $song->attachTags($songId, array_map('intval', $newTags));
                    }
                    
                    if (!empty($updates['replace_tags'])) {
                        $song->attachTags($songId, array_map('intval', $updates['replace_tags']));
                    }
                }
                
                Database::commit();
                
                $logger->info("Bulk update completed", [
                    'song_count' => count($songIds),
                    'updates' => $updates,
                    'updated_count' => $updateCount
                ], 'BULK_EDIT');
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Updated $updateCount songs successfully",
                    'updated_count' => $updateCount
                ]);
                exit;
                
            case 'bulk_delete':
                $songIds = json_decode($_POST['song_ids'], true);
                
                if (empty($songIds)) {
                    throw new Exception("No songs specified for deletion");
                }
                
                $song = new Song();
                $deleteCount = 0;
                
                foreach ($songIds as $songId) {
                    if ($song->delete($songId)) {
                        $deleteCount++;
                    }
                }
                
                $logger->info("Bulk delete completed", [
                    'song_count' => count($songIds),
                    'deleted_count' => $deleteCount
                ], 'BULK_EDIT');
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Deleted $deleteCount songs successfully",
                    'deleted_count' => $deleteCount
                ]);
                exit;
                
            default:
                throw new Exception("Unknown action");
        }
        
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        
        $logger->error("Bulk edit error", [
            'action' => $_POST['action'],
            'error' => $e->getMessage()
        ], 'BULK_EDIT');
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$tagFilter = $_GET['tag'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$limit = (int)($_GET['limit'] ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

// Build query with filters
$sql = "
    SELECT 
        s.*,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags,
        GROUP_CONCAT(DISTINCT CONCAT(t.id, ':', t.name, ':', t.category) SEPARATOR '|') as tag_details
    FROM songs s
    LEFT JOIN song_tags st ON s.id = st.song_id
    LEFT JOIN tags t ON st.tag_id = t.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND s.title LIKE ?";
    $params[] = "%$search%";
}

if (!empty($tagFilter)) {
    $sql .= " AND s.id IN (
        SELECT DISTINCT song_id 
        FROM song_tags st2 
        JOIN tags t2 ON st2.tag_id = t2.id 
        WHERE t2.name = ?
    )";
    $params[] = $tagFilter;
}

if ($statusFilter === 'active') {
    $sql .= " AND s.active = 1";
} elseif ($statusFilter === 'inactive') {
    $sql .= " AND s.active = 0";
}

$sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$songs = Database::execute($sql, $params)->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT s.id) as total FROM songs s";
if (!empty($tagFilter)) {
    $countSql .= " JOIN song_tags st ON s.id = st.song_id JOIN tags t ON st.tag_id = t.id WHERE t.name = ?";
    $countParams = [$tagFilter];
} else {
    $countParams = [];
}

$totalCount = Database::execute($countSql, $countParams)->fetch()['total'];

// Get all tags for filters
$allTags = Database::execute("SELECT DISTINCT name FROM tags ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Editor - Stinkin' Park Admin</title>
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
        
        .search-filters {
            display: flex;
            gap: 10px;
            flex: 1;
            flex-wrap: wrap;
        }
        
        .search-filters input,
        .search-filters select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
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
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .selection-info {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        
        .selection-info.active {
            display: flex;
        }
        
        .selection-count {
            font-weight: 600;
            color: #667eea;
        }
        
        .selection-actions {
            display: flex;
            gap: 10px;
        }
        
        .songs-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        tr.selected {
            background: #e3f2fd;
        }
        
        .song-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .song-title {
            font-weight: 600;
            color: #333;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .tag-badge {
            display: inline-block;
            padding: 2px 6px;
            margin: 1px;
            background: #e0e0e0;
            border-radius: 3px;
            font-size: 11px;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 600;
        }
        
        .pagination {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .tag-selection {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        
        .tag-option {
            display: flex;
            align-items: center;
            padding: 4px 0;
        }
        
        .tag-option input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
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
        
        @media (max-width: 768px) {
            .toolbar,
            .search-filters,
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .selection-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .songs-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Bulk Song Editor - Stinkin' Park</h1>
            <nav class="nav">
                <a href="upload.php">Single Upload</a>
                <a href="mass-upload.php">Mass Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="bulk-edit.php">Bulk Edit</a>
                <a href="tag-manager.php">Manage Tags</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <div class="toolbar">
            <div class="search-filters">
                <input type="text" id="search" placeholder="Search songs..." value="<?= htmlspecialchars($search) ?>">
                
                <select id="tagFilter">
                    <option value="">All Tags</option>
                    <?php foreach ($allTags as $tag): ?>
                    <option value="<?= htmlspecialchars($tag['name']) ?>" 
                            <?= $tagFilter === $tag['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tag['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="statusFilter">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                
                <button class="btn btn-secondary" onclick="applyFilters()">Apply Filters</button>
                <button class="btn btn-secondary" onclick="clearFilters()">Clear</button>
            </div>
        </div>

        <div id="alert-container"></div>

        <div class="selection-info" id="selection-info">
            <div>
                <span class="selection-count" id="selection-count">0</span> songs selected
            </div>
            <div class="selection-actions">
                <button class="btn btn-primary" onclick="showBulkEditModal()">✏️ Bulk Edit</button>
                <button class="btn btn-success" onclick="bulkActivate()">✅ Activate</button>
                <button class="btn btn-warning" onclick="bulkDeactivate()">❌ Deactivate</button>
                <button class="btn btn-danger" onclick="bulkDelete()">🗑️ Delete</button>
                <button class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
            </div>
        </div>

        <div class="songs-table">
            <table>
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="select-all" class="song-checkbox">
                        </th>
                        <th>Title</th>
                        <th>Tags</th>
                        <th>Status</th>
                        <th>Plays</th>
                        <th>Duration</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $song): ?>
                    <tr data-song-id="<?= $song['id'] ?>">
                        <td>
                            <input type="checkbox" class="song-checkbox song-select" value="<?= $song['id'] ?>">
                        </td>
                        <td>
                            <div class="song-title" title="<?= htmlspecialchars($song['title']) ?>">
                                <?= htmlspecialchars($song['title']) ?>
                            </div>
                            <small style="color: #666;"><?= htmlspecialchars($song['filename']) ?></small>
                        </td>
                        <td>
                            <div style="max-width: 250px; overflow: hidden;">
                                <?php 
                                if ($song['tag_details']) {
                                    $tagDetails = explode('|', $song['tag_details']);
                                    foreach ($tagDetails as $detail) {
                                        list($id, $name, $category) = explode(':', $detail);
                                        echo '<span class="tag-badge">' . htmlspecialchars($name) . '</span>';
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <span class="<?= $song['active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $song['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= $song['play_count'] ?></td>
                        <td>
                            <?= $song['duration'] ? gmdate("i:s", $song['duration']) : '-' ?>
                        </td>
                        <td>
                            <?= date('M j, Y', strtotime($song['created_at'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $totalCount) ?> of <?= $totalCount ?> songs
            </div>
            <div class="pagination-controls">
                <?php if ($offset > 0): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['offset' => max(0, $offset - $limit)])) ?>" 
                   class="btn btn-secondary">← Previous</a>
                <?php endif; ?>
                
                <?php if ($offset + $limit < $totalCount): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['offset' => $offset + $limit])) ?>" 
                   class="btn btn-secondary">Next →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <div id="bulkEditModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('bulkEditModal')">&times;</span>
            <h3>Bulk Edit Songs</h3>
            <p style="margin-bottom: 20px; color: #666;">
                Changes will be applied to <span id="edit-count">0</span> selected songs.
            </p>
            
            <form id="bulkEditForm">
                <div class="form-group">
                    <label>Status</label>
                    <select id="bulkStatus">
                        <option value="">No Change</option>
                        <option value="1">Activate</option>
                        <option value="0">Deactivate</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Title Modifications</label>
                    <input type="text" id="titlePrefix" placeholder="Add prefix to titles">
                    <input type="text" id="titleSuffix" placeholder="Add suffix to titles" style="margin-top: 10px;">
                </div>
                
                <div class="form-group">
                    <label>Tag Operations</label>
                    <select id="tagOperation" onchange="updateTagSelection()">
                        <option value="">No Tag Changes</option>
                        <option value="add">Add Tags</option>
                        <option value="remove">Remove Tags</option>
                        <option value="replace">Replace All Tags</option>
                    </select>
                    
                    <div id="tagSelectionContainer" style="display: none; margin-top: 10px;">
                        <div class="tag-selection" id="tagSelection">
                            <!-- Tags will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('bulkEditModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedSongs = new Set();
        const allTags = <?= json_encode($allTags) ?>;

        // Selection handling
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.song-select');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    selectedSongs.add(parseInt(cb.value));
                } else {
                    selectedSongs.delete(parseInt(cb.value));
                }
            });
            updateSelectionUI();
        });

        document.querySelectorAll('.song-select').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const songId = parseInt(this.value);
                if (this.checked) {
                    selectedSongs.add(songId);
                } else {
                    selectedSongs.delete(songId);
                }
                updateSelectionUI();
                
                // Update select-all checkbox
                const allCheckboxes = document.querySelectorAll('.song-select');
                const checkedCount = document.querySelectorAll('.song-select:checked').length;
                const selectAllCheckbox = document.getElementById('select-all');
                
                selectAllCheckbox.checked = checkedCount === allCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
            });
        });

        function updateSelectionUI() {
            const count = selectedSongs.size;
            const selectionInfo = document.getElementById('selection-info');
            const selectionCount = document.getElementById('selection-count');
            
            selectionCount.textContent = count;
            selectionInfo.classList.toggle('active', count > 0);
            
            // Update row highlighting
            document.querySelectorAll('tr[data-song-id]').forEach(row => {
                const songId = parseInt(row.dataset.songId);
                row.classList.toggle('selected', selectedSongs.has(songId));
            });
        }

        function clearSelection() {
            selectedSongs.clear();
            document.querySelectorAll('.song-select').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateSelectionUI();
        }

        // Filter functions
        function applyFilters() {
            const params = new URLSearchParams();
            
            const search = document.getElementById('search').value;
            const tag = document.getElementById('tagFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            if (search) params.set('search', search);
            if (tag) params.set('tag', tag);
            if (status) params.set('status', status);
            
            window.location.href = '?' + params.toString();
        }

        function clearFilters() {
            window.location.href = 'bulk-edit.php';
        }

        // Enter key search
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        // Bulk operations
        function bulkActivate() {
            if (selectedSongs.size === 0) {
                showAlert('Please select songs first', 'error');
                return;
            }
            
            performBulkUpdate({active: 1}, 'activated');
        }

        function bulkDeactivate() {
            if (selectedSongs.size === 0) {
                showAlert('Please select songs first', 'error');
                return;
            }
            
            performBulkUpdate({active: 0}, 'deactivated');
        }

        function bulkDelete() {
            if (selectedSongs.size === 0) {
                showAlert('Please select songs first', 'error');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selectedSongs.size} songs? This cannot be undone.`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'bulk_delete');
            formData.append('song_ids', JSON.stringify(Array.from(selectedSongs)));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        }

        function showBulkEditModal() {
            if (selectedSongs.size === 0) {
                showAlert('Please select songs first', 'error');
                return;
            }
            
            document.getElementById('edit-count').textContent = selectedSongs.size;
            loadTagsForSelection();
            showModal('bulkEditModal');
        }

        function updateTagSelection() {
            const operation = document.getElementById('tagOperation').value;
            const container = document.getElementById('tagSelectionContainer');
            
            if (operation) {
                container.style.display = 'block';
                loadTagsForSelection();
            } else {
                container.style.display = 'none';
            }
        }

        function loadTagsForSelection() {
            const tagSelection = document.getElementById('tagSelection');
            tagSelection.innerHTML = allTags.map(tag => `
                <div class="tag-option">
                    <input type="checkbox" id="tag_${tag.name}" value="${tag.name}">
                    <label for="tag_${tag.name}">${tag.name}</label>
                </div>
            `).join('');
        }

        // Bulk edit form submission
        document.getElementById('bulkEditForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const updates = {};
            
            // Status update
            const status = document.getElementById('bulkStatus').value;
            if (status !== '') {
                updates.active = parseInt(status);
            }
            
            // Title modifications
            const prefix = document.getElementById('titlePrefix').value.trim();
            const suffix = document.getElementById('titleSuffix').value.trim();
            if (prefix) updates.title_prefix = prefix;
            if (suffix) updates.title_suffix = suffix;
            
            // Tag operations
            const tagOperation = document.getElementById('tagOperation').value;
            if (tagOperation) {
                const selectedTagIds = [];
                document.querySelectorAll('#tagSelection input:checked').forEach(cb => {
                    // Find tag ID by name (you might want to include tag IDs in the data)
                    selectedTagIds.push(cb.value);
                });
                
                if (selectedTagIds.length > 0) {
                    switch (tagOperation) {
                        case 'add':
                            updates.add_tags = selectedTagIds;
                            break;
                        case 'remove':
                            updates.remove_tags = selectedTagIds;
                            break;
                        case 'replace':
                            updates.replace_tags = selectedTagIds;
                            break;
                    }
                }
            }
            
            if (Object.keys(updates).length === 0) {
                showAlert('Please specify at least one change to make', 'error');
                return;
            }
            
            performBulkUpdate(updates, 'updated');
        });

        function performBulkUpdate(updates, action) {
            const formData = new FormData();
            formData.append('action', 'bulk_update');
            formData.append('song_ids', JSON.stringify(Array.from(selectedSongs)));
            formData.append('updates', JSON.stringify(updates));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeModal('bulkEditModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Network error: ' + error.message, 'error');
            });
        }

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

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Initialize
        updateSelectionUI();
    </script>
</body>
</html>