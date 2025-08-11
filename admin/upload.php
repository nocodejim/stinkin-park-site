<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';
require_once __DIR__ . '/../includes/FileUploader.php';

use StinkinPark\Database;
use StinkinPark\Song;
use StinkinPark\FileUploader;

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate title
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) {
            throw new Exception("Song title is required");
        }
        
        // Validate tags
        $selectedTags = $_POST['tags'] ?? [];
        if (empty($selectedTags)) {
            throw new Exception("Please select at least one tag");
        }
        
        // Upload file
        if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select an audio file to upload");
        }
        
        $uploader = new FileUploader();
        $fileData = $uploader->uploadAudio($_FILES['audio_file']);
        
        // Save to database
        $song = new Song();
        $songId = $song->create([
            'title' => $title,
            'filename' => $fileData['filename'],
            'duration' => $fileData['duration'],
            'file_size' => $fileData['file_size'],
            'active' => isset($_POST['active']) ? 1 : 0
        ]);
        
        // Attach tags
        $song->attachTags($songId, array_map('intval', $selectedTags));
        
        $message = "✓ Song uploaded successfully!";
        $messageType = 'success';
        
        // Clear form
        $title = '';
        $selectedTags = [];
        
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
    <title>Upload Song - Stinkin' Park Admin</title>
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
        
        .upload-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        
        input[type="text"],
        input[type="file"] {
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
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-label {
            display: block;
            padding: 12px;
            background: #f5f5f5;
            border: 2px dashed #d0d0d0;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            background: #ebebeb;
            border-color: #667eea;
        }
        
        input[type="file"] {
            position: absolute;
            left: -9999px;
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
        
        .file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
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
            <h1>🎵 Stinkin' Park - Upload Song</h1>
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

        <form class="upload-form" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Song Title *</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       required 
                       placeholder="Enter song title...">
            </div>

            <div class="form-group">
                <label for="audio_file">Audio File * (MP3 or WAV, max 50MB)</label>
                <div class="file-input-wrapper">
                    <label for="audio_file" class="file-input-label" id="file-label">
                        📁 Click to select audio file...
                    </label>
                    <input type="file" 
                           id="audio_file" 
                           name="audio_file" 
                           accept="audio/mpeg,audio/mp3,audio/wav" 
                           required>
                </div>
                <div class="file-info" id="file-info"></div>
            </div>

            <div class="form-group">
                <label>Tags * (Select all that apply)</label>
                <div class="tag-section">
                    <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                    <div class="tag-category">
                        <h3><?= ucfirst($category) ?></h3>
                        <div class="tag-grid">
                            <?php foreach ($categoryTags as $tag): ?>
                            <div class="tag-checkbox">
                                <input type="checkbox" 
                                       id="tag_<?= $tag['id'] ?>" 
                                       name="tags[]" 
                                       value="<?= $tag['id'] ?>"
                                       <?= in_array($tag['id'], $_POST['tags'] ?? []) ? 'checked' : '' ?>>
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
                           checked>
                    <label for="active">Make song active immediately</label>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Upload Song</button>
                <button type="reset" class="btn btn-secondary">Clear Form</button>
            </div>
        </form>
    </div>

    <script>
        // File input display
        document.getElementById('audio_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.getElementById('file-label');
            const info = document.getElementById('file-info');
            
            if (file) {
                label.textContent = `📄 ${file.name}`;
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                info.textContent = `Size: ${sizeMB} MB`;
                
                // Validate size
                if (file.size > 50 * 1024 * 1024) {
                    info.textContent += ' ⚠️ File too large!';
                    info.style.color = '#dc3545';
                } else {
                    info.style.color = '#666';
                }
            }
        });

        // Form validation
        document.querySelector('.upload-form').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="tags[]"]:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one tag for the song.');
                return false;
            }
        });

        // Tag selection helpers
        function selectAllInCategory(category) {
            const checkboxes = category.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = true);
        }

        function clearAllInCategory(category) {
            const checkboxes = category.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        }
    </script>
</body>
</html>
