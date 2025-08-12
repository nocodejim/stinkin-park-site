<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';

use StinkinPark\Database;
use StinkinPark\Song;

$message = '';
$messageType = '';

// Get song ID from URL
$songId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($songId === 0) {
    header('Location: manage.php');
    exit;
}

$song = new Song();
$songData = $song->getById($songId);

if (!$songData) {
    header('Location: manage.php?msg=notfound');
    exit;
}

// Parse existing tag IDs
$currentTagIds = !empty($songData['tag_ids']) ? explode(',', $songData['tag_ids']) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update song details
        $song->update($songId, [
            'title' => trim($_POST['title']),
            'active' => isset($_POST['active']) ? 1 : 0
        ]);
        
        // Update tags
        $selectedTags = $_POST['tags'] ?? [];
        $song->attachTags($songId, array_map('intval', $selectedTags));
        
        $message = "✓ Song updated successfully!";
        $messageType = 'success';
        
        // Reload song data
        $songData = $song->getById($songId);
        $currentTagIds = !empty($songData['tag_ids']) ? explode(',', $songData['tag_ids']) : [];
        
    } catch (Exception $e) {
        $message = "✗ " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all tags grouped by category
$tagsByCategory = [];
$tags = Database::execute("SELECT * FROM tags ORDER BY category, display_order, name")->fetchAll();

foreach ($tags as $tag) {
    $tagsByCategory[$tag['category']][] = $tag;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Song - Stinkin' Park Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #333;
            font-size: 24px;
        }
        
        .nav {
            margin-top: 10px;
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .nav a:hover {
            text-decoration: underline;
        }
        
        .edit-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .song-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .song-info p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .song-info strong {
            color: #333;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .tag-section {
            margin-bottom: 20px;
        }
        
        .tag-category {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .tag-category h3 {
            color: #555;
            font-size: 14px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .tag-checkbox {
            display: flex;
            align-items: center;
        }
        
        .tag-checkbox input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .tag-checkbox label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            font-size: 14px;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #d0d0d0;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .active-checkbox {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        
        .active-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
        
        .category-actions {
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .category-actions button {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
            margin-right: 10px;
            font-size: 12px;
        }
        
        @media (max-width: 600px) {
            .tag-grid {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Edit Song</h1>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <form class="edit-form" method="POST">
            <div class="song-info">
                <p><strong>Filename:</strong> <?= htmlspecialchars($songData['filename']) ?></p>
                <p><strong>Duration:</strong> <?= $songData['duration'] ? gmdate("i:s", $songData['duration']) : 'Unknown' ?></p>
                <p><strong>File Size:</strong> <?= $songData['file_size'] ? number_format($songData['file_size'] / 1048576, 2) . ' MB' : 'Unknown' ?></p>
                <p><strong>Play Count:</strong> <?= $songData['play_count'] ?></p>
                <p><strong>Uploaded:</strong> <?= date('M j, Y g:i A', strtotime($songData['created_at'])) ?></p>
            </div>

            <div class="form-group">
                <label for="title">Song Title *</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       value="<?= htmlspecialchars($songData['title']) ?>"
                       required>
            </div>

            <div class="form-group">
                <label>Tags * (Select all that apply)</label>
                <div class="tag-section">
                    <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                    <div class="tag-category">
                        <h3><?= ucfirst($category) ?></h3>
                        <div class="category-actions">
                            <button type="button" onclick="selectAllInCategory(this.parentElement.parentElement)">Select All</button>
                            <button type="button" onclick="clearAllInCategory(this.parentElement.parentElement)">Clear All</button>
                        </div>
                        <div class="tag-grid">
                            <?php foreach ($categoryTags as $tag): ?>
                            <div class="tag-checkbox">
                                <input type="checkbox" 
                                       id="tag_<?= $tag['id'] ?>" 
                                       name="tags[]" 
                                       value="<?= $tag['id'] ?>"
                                       <?= in_array($tag['id'], $currentTagIds) ? 'checked' : '' ?>>
                                <label for="tag_<?= $tag['id'] ?>">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <div class="active-checkbox">
                    <input type="checkbox" 
                           id="active" 
                           name="active" 
                           value="1" 
                           <?= $songData['active'] ? 'checked' : '' ?>>
                    <label for="active">Song is active (visible in stations)</label>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage.php" class="btn btn-secondary">Cancel</a>
                <a href="<?= BASE_URL ?>/audio/<?= htmlspecialchars($songData['filename']) ?>"
                   class="btn btn-secondary" 
                   target="_blank">▶ Preview</a>
            </div>
        </form>
    </div>

    <script>
        function selectAllInCategory(category) {
            const checkboxes = category.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = true);
        }

        function clearAllInCategory(category) {
            const checkboxes = category.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        }

        // Form validation
        document.querySelector('.edit-form').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="tags[]"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one tag for the song.');
                return false;
            }
        });
    </script>
</body>
</html>