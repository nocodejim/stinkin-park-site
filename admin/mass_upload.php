<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';
require_once __DIR__ . '/../includes/FileUploader.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Song;
use StinkinPark\FileUploader;
use StinkinPark\Logger;

$logger = Logger::getInstance();
$message = '';
$messageType = '';
$uploadResults = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $logger->info("Mass upload initiated", ['file_count' => count($_FILES['audio_files']['name'])], 'MASS_UPLOAD');
        
        // Validate common tags
        $commonTags = $_POST['common_tags'] ?? [];
        if (empty($commonTags)) {
            throw new Exception("Please select at least one common tag to apply to all uploads");
        }
        
        $activeStatus = isset($_POST['active']) ? 1 : 0;
        $useFilenameAsTitle = isset($_POST['use_filename_as_title']);
        
        $uploader = new FileUploader();
        $song = new Song();
        
        $successCount = 0;
        $errorCount = 0;
        
        // Process each uploaded file
        for ($i = 0; $i < count($_FILES['audio_files']['name']); $i++) {
            // Skip if no file uploaded for this slot
            if ($_FILES['audio_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            $fileData = [
                'name' => $_FILES['audio_files']['name'][$i],
                'tmp_name' => $_FILES['audio_files']['tmp_name'][$i],
                'size' => $_FILES['audio_files']['size'][$i],
                'error' => $_FILES['audio_files']['error'][$i]
            ];
            
            $originalFilename = $fileData['name'];
            
            try {
                // Upload file
                $uploadResult = $uploader->uploadAudio($fileData);
                
                // Determine title
                $title = $useFilenameAsTitle ? 
                    pathinfo($originalFilename, PATHINFO_FILENAME) : 
                    ($_POST['custom_titles'][$i] ?? pathinfo($originalFilename, PATHINFO_FILENAME));
                
                // Clean up title
                $title = trim($title);
                if (empty($title)) {
                    $title = pathinfo($originalFilename, PATHINFO_FILENAME);
                }
                
                // Create song record
                $songId = $song->create([
                    'title' => $title,
                    'filename' => $uploadResult['filename'],
                    'duration' => $uploadResult['duration'],
                    'file_size' => $uploadResult['file_size'],
                    'active' => $activeStatus
                ]);
                
                // Attach common tags
                $song->attachTags($songId, array_map('intval', $commonTags));
                
                // Attach individual tags if specified
                if (!empty($_POST['individual_tags'][$i])) {
                    $individualTags = array_filter(explode(',', $_POST['individual_tags'][$i]));
                    if (!empty($individualTags)) {
                        $song->attachTags($songId, array_map('intval', $individualTags));
                    }
                }
                
                $uploadResults[] = [
                    'success' => true,
                    'filename' => $originalFilename,
                    'title' => $title,
                    'song_id' => $songId
                ];
                
                $successCount++;
                
                $logger->info("Song uploaded successfully", [
                    'song_id' => $songId,
                    'title' => $title,
                    'filename' => $uploadResult['filename']
                ], 'MASS_UPLOAD');
                
            } catch (Exception $e) {
                $uploadResults[] = [
                    'success' => false,
                    'filename' => $originalFilename,
                    'error' => $e->getMessage()
                ];
                
                $errorCount++;
                
                $logger->error("Song upload failed", [
                    'filename' => $originalFilename,
                    'error' => $e->getMessage()
                ], 'MASS_UPLOAD');
            }
        }
        
        $message = "Upload completed: $successCount successful, $errorCount failed";
        $messageType = $errorCount === 0 ? 'success' : 'mixed';
        
        $logger->info("Mass upload completed", [
            'success_count' => $successCount,
            'error_count' => $errorCount
        ], 'MASS_UPLOAD');
        
    } catch (Exception $e) {
        $message = "✗ " . $e->getMessage();
        $messageType = 'error';
        $logger->error("Mass upload failed", ['error' => $e->getMessage()], 'MASS_UPLOAD');
    }
}

// Get all tags grouped by category for the form
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
    <title>Mass Upload - Stinkin' Park Admin</title>
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
            max-width: 1200px;
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
        
        .upload-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .file-drop-zone {
            border: 3px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .file-drop-zone:hover,
        .file-drop-zone.dragover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .file-drop-zone input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-preview {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            background: white;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-info {
            flex: 1;
            margin-right: 15px;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .file-size {
            font-size: 12px;
            color: #666;
        }
        
        .file-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .file-controls input[type="text"] {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            width: 200px;
        }
        
        .remove-file {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .tag-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .tag-category {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .tag-category h4 {
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 8px;
        }
        
        .tag-checkbox {
            display: flex;
            align-items: center;
            padding: 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .tag-checkbox:hover {
            background: #f5f5f5;
        }
        
        .tag-checkbox input[type="checkbox"] {
            margin-right: 6px;
            width: 16px;
            height: 16px;
        }
        
        .tag-checkbox label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
            font-size: 13px;
            line-height: 1.2;
        }
        
        .options-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .option-group {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .checkbox-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .checkbox-option input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
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
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
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
        
        .message.mixed {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .results {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .result-item {
            padding: 10px;
            margin-bottom: 8px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .result-success {
            background: #d4edda;
            color: #155724;
        }
        
        .result-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: #667eea;
            width: 0%;
            transition: width 0.3s;
        }
        
        @media (max-width: 768px) {
            .tag-section,
            .options-section {
                grid-template-columns: 1fr;
            }
            
            .file-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .file-controls input[type="text"] {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Mass Upload - Stinkin' Park</h1>
            <nav class="nav">
                <a href="upload.php">Single Upload</a>
                <a href="mass-upload.php">Mass Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="tag-manager.php">Manage Tags</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($uploadResults)): ?>
        <div class="results">
            <h2>Upload Results</h2>
            <?php foreach ($uploadResults as $result): ?>
            <div class="result-item <?= $result['success'] ? 'result-success' : 'result-error' ?>">
                <span>
                    <?= $result['success'] ? '✓' : '✗' ?> 
                    <?= htmlspecialchars($result['filename']) ?>
                    <?php if ($result['success']): ?>
                        → "<?= htmlspecialchars($result['title']) ?>" (ID: <?= $result['song_id'] ?>)
                    <?php endif; ?>
                </span>
                <?php if (!$result['success']): ?>
                <span><?= htmlspecialchars($result['error']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form class="upload-form" method="POST" enctype="multipart/form-data" id="mass-upload-form">
            <!-- File Selection Section -->
            <div class="section">
                <h3>📁 Select Audio Files</h3>
                <div class="form-group">
                    <div class="file-drop-zone" id="drop-zone">
                        <input type="file" 
                               id="audio_files" 
                               name="audio_files[]" 
                               multiple 
                               accept="audio/mpeg,audio/mp3,audio/wav">
                        <div class="drop-text">
                            <p><strong>Drag & drop audio files here</strong></p>
                            <p>or click to browse</p>
                            <p><small>Supports MP3 and WAV files, max 50MB each</small></p>
                        </div>
                    </div>
                    <div id="file-preview" class="file-preview" style="display: none;"></div>
                </div>
            </div>

            <!-- Common Tags Section -->
            <div class="section">
                <h3>🏷️ Common Tags (Applied to All Files)</h3>
                <p style="margin-bottom: 15px; color: #666;">Select tags that will be applied to every uploaded song:</p>
                <div class="tag-section">
                    <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                    <div class="tag-category">
                        <h4><?= ucfirst($category) ?></h4>
                        <div class="tag-grid">
                            <?php foreach ($categoryTags as $tag): ?>
                            <div class="tag-checkbox">
                                <input type="checkbox" 
                                       id="common_tag_<?= $tag['id'] ?>" 
                                       name="common_tags[]" 
                                       value="<?= $tag['id'] ?>">
                                <label for="common_tag_<?= $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Upload Options Section -->
            <div class="section">
                <h3>⚙️ Upload Options</h3>
                <div class="options-section">
                    <div class="option-group">
                        <h4>Naming Options</h4>
                        <div class="checkbox-option">
                            <input type="checkbox" 
                                   id="use_filename_as_title" 
                                   name="use_filename_as_title" 
                                   checked>
                            <label for="use_filename_as_title">
                                Use filename as song title
                                <br><small>You can customize individual titles in the file preview</small>
                            </label>
                        </div>
                    </div>
                    
                    <div class="option-group">
                        <h4>Activation Options</h4>
                        <div class="checkbox-option">
                            <input type="checkbox" 
                                   id="active" 
                                   name="active" 
                                   value="1" 
                                   checked>
                            <label for="active">
                                Make all songs active immediately
                                <br><small>Active songs appear in stations</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; padding-top: 20px;">
                <button type="submit" class="btn btn-primary" id="upload-btn" disabled>
                    Upload All Files
                </button>
                <button type="button" class="btn btn-secondary" onclick="clearFiles()">
                    Clear All
                </button>
            </div>
        </form>
    </div>

    <script>
        let selectedFiles = [];
        
        // File handling
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('audio_files');
        const filePreview = document.getElementById('file-preview');
        const uploadBtn = document.getElementById('upload-btn');

        // Drag and drop handlers
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            selectedFiles = Array.from(files).filter(file => {
                const validTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav'];
                return validTypes.includes(file.type) && file.size <= 50 * 1024 * 1024;
            });

            if (selectedFiles.length !== files.length) {
                alert('Some files were filtered out (invalid type or too large)');
            }

            updateFilePreview();
            updateUploadButton();
        }

        function updateFilePreview() {
            if (selectedFiles.length === 0) {
                filePreview.style.display = 'none';
                return;
            }

            filePreview.style.display = 'block';
            filePreview.innerHTML = selectedFiles.map((file, index) => {
                const filename = file.name;
                const title = filename.replace(/\.[^/.]+$/, ""); // Remove extension
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);

                return `
                    <div class="file-item" data-index="${index}">
                        <div class="file-info">
                            <div class="file-name">${filename}</div>
                            <div class="file-size">${sizeMB} MB</div>
                        </div>
                        <div class="file-controls">
                            <input type="text" 
                                   name="custom_titles[${index}]" 
                                   value="${title}" 
                                   placeholder="Song title">
                            <button type="button" class="remove-file" onclick="removeFile(${index})">Remove</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFilePreview();
            updateUploadButton();
            updateFileInput();
        }

        function updateFileInput() {
            // Create new FileList from selectedFiles
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            fileInput.files = dt.files;
        }

        function updateUploadButton() {
            uploadBtn.disabled = selectedFiles.length === 0;
            
            if (selectedFiles.length > 0) {
                uploadBtn.textContent = `Upload ${selectedFiles.length} File${selectedFiles.length > 1 ? 's' : ''}`;
            } else {
                uploadBtn.textContent = 'Upload All Files';
            }
        }

        function clearFiles() {
            selectedFiles = [];
            fileInput.value = '';
            updateFilePreview();
            updateUploadButton();
        }

        // Form validation
        document.getElementById('mass-upload-form').addEventListener('submit', function(e) {
            if (selectedFiles.length === 0) {
                e.preventDefault();
                alert('Please select at least one audio file to upload.');
                return false;
            }

            const commonTags = document.querySelectorAll('input[name="common_tags[]"]:checked');
            if (commonTags.length === 0) {
                e.preventDefault();
                alert('Please select at least one common tag to apply to all uploads.');
                return false;
            }

            // Show progress indicator
            uploadBtn.textContent = 'Uploading...';
            uploadBtn.disabled = true;
        });

        // Category selection helpers
        function selectAllInCategory(categoryElement) {
            const checkboxes = categoryElement.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = true);
        }

        function clearAllInCategory(categoryElement) {
            const checkboxes = categoryElement.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);
        }

        // Add category controls
        document.querySelectorAll('.tag-category').forEach(category => {
            const header = category.querySelector('h4');
            const controls = document.createElement('div');
            controls.style.fontSize = '11px';
            controls.style.marginBottom = '8px';
            controls.innerHTML = `
                <button type="button" onclick="selectAllInCategory(this.closest('.tag-category'))" 
                        style="background:none;border:none;color:#667eea;cursor:pointer;text-decoration:underline;margin-right:10px;">
                    Select All
                </button>
                <button type="button" onclick="clearAllInCategory(this.closest('.tag-category'))" 
                        style="background:none;border:none;color:#667eea;cursor:pointer;text-decoration:underline;">
                    Clear All
                </button>
            `;
            header.insertAdjacentElement('afterend', controls);
        });
    </script>
</body>
</html>