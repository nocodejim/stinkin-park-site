<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Database;
use StinkinPark\Station;

$station = new Station();
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stationId = $station->create([
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'background_video' => $_POST['background_video'],
            'background_image' => $_POST['background_image'],
            'active' => isset($_POST['active']) ? 1 : 0
        ]);
        
        // Process tag rules
        $tagRules = [];
        foreach ($_POST['tags'] ?? [] as $tagId => $ruleType) {
            if ($ruleType !== 'none') {
                $tagRules[$tagId] = $ruleType;
            }
        }
        
        $station->setStationTags($stationId, $tagRules);
        
        $message = "Station created successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get all tags for the form
$tags = Database::execute("SELECT * FROM tags ORDER BY category, name")->fetchAll();
$tagsByCategory = [];
foreach ($tags as $tag) {
    $tagsByCategory[$tag['category']][] = $tag;
}

// Get existing stations
$stations = $station->getAll(false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Management - Stinkin' Park Admin</title>
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
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        
        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .tag-rules {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .tag-rule-row {
            display: grid;
            grid-template-columns: 200px 1fr;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px;
            background: white;
            border-radius: 4px;
        }
        
        .tag-name {
            font-weight: 500;
            color: #333;
        }
        
        .rule-options {
            display: flex;
            gap: 15px;
        }
        
        .rule-options label {
            display: flex;
            align-items: center;
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }
        
        .rule-options input[type="radio"] {
            margin-right: 5px;
        }
        
        .station-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .station-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .station-info h3 {
            margin: 0 0 5px 0;
            color: #333;
        }
        
        .station-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .station-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #28a745;
            color: white;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .rule-type-none { color: #999; }
        .rule-type-include { color: #28a745; }
        .rule-type-require { color: #ffc107; }
        .rule-type-exclude { color: #dc3545; }
        
        .help-text {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1976d2;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .tag-rule-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📻 Station Management</h1>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <?php if ($message): ?>
        <div class="card" style="background: #d4edda; color: #155724;">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h2>Create New Station</h2>
                
                <div class="help-text">
                    <strong>Tag Rules:</strong><br>
                    • <span class="rule-type-include">Include</span>: Songs with ANY of these tags<br>
                    • <span class="rule-type-require">Require</span>: Songs must have ALL of these tags<br>
                    • <span class="rule-type-exclude">Exclude</span>: Songs with these tags are NOT included<br>
                    • <span class="rule-type-none">None</span>: Tag not used for this station
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="name">Station Name *</label>
                        <input type="text" id="name" name="name" required 
                               placeholder="e.g., Heavy Hitters, Chill Vibes">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" 
                                  placeholder="What's the vibe of this station?"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="background_video">Background Video (filename)</label>
                        <input type="text" id="background_video" name="background_video" 
                               placeholder="video.mp4">
                    </div>

                    <div class="form-group">
                        <label for="background_image">Background Image (filename)</label>
                        <input type="text" id="background_image" name="background_image" 
                               placeholder="background.jpg">
                    </div>

                    <div class="form-group">
                        <label>Tag Rules</label>
                        <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                        <div class="tag-rules">
                            <h4 style="margin-bottom: 10px; text-transform: uppercase; font-size: 12px; color: #666;">
                                <?= ucfirst($category) ?>
                            </h4>
                            <?php foreach ($categoryTags as $tag): ?>
                            <div class="tag-rule-row">
                                <div class="tag-name"><?= htmlspecialchars($tag['name']) ?></div>
                                <div class="rule-options">
                                    <label>
                                        <input type="radio" name="tags[<?= $tag['id'] ?>]" value="none" checked>
                                        None
                                    </label>
                                    <label>
                                        <input type="radio" name="tags[<?= $tag['id'] ?>]" value="include">
                                        Include
                                    </label>
                                    <label>
                                        <input type="radio" name="tags[<?= $tag['id'] ?>]" value="require">
                                        Require
                                    </label>
                                    <label>
                                        <input type="radio" name="tags[<?= $tag['id'] ?>]" value="exclude">
                                        Exclude
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="active" value="1" checked>
                            Make station active immediately
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Station</button>
                </form>
            </div>

            <div class="card">
                <h2>Existing Stations</h2>
                <div class="station-list">
                    <?php foreach ($stations as $stationItem): ?>
                    <div class="station-item">
                        <div class="station-info">
                            <h3><?= htmlspecialchars($stationItem['name']) ?></h3>
                            <p>Slug: /<?= htmlspecialchars($stationItem['slug']) ?></p>
                            <p>Status: <?= $stationItem['active'] ? '🟢 Active' : '🔴 Inactive' ?></p>
                        </div>
                        <div class="station-actions">
                            <a href="<?= BASE_URL ?>/stations/<?= $stationItem['slug'] ?>"
                               class="btn btn-secondary btn-small" 
                               target="_blank">View</a>
                            <a href="edit-station.php?id=<?= $stationItem['id'] ?>" 
                               class="btn btn-primary btn-small">Edit</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
