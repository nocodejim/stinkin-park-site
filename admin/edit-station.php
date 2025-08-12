<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Station.php';

use StinkinPark\Database;
use StinkinPark\Station;

$message = '';
$messageType = '';

// Get station ID from URL
$stationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($stationId === 0) {
    header('Location: stations.php');
    exit;
}

// Get station data
$sql = "SELECT * FROM stations WHERE id = ?";
$stationData = Database::execute($sql, [$stationId])->fetch();

if (!$stationData) {
    header('Location: stations.php?msg=notfound');
    exit;
}

// Get current tag rules
$sql = "SELECT tag_id, rule_type FROM station_tags WHERE station_id = ?";
$currentRules = Database::execute($sql, [$stationId])->fetchAll();
$rulesByTagId = [];
foreach ($currentRules as $rule) {
    $rulesByTagId[$rule['tag_id']] = $rule['rule_type'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update station details
        $sql = "UPDATE stations SET 
                name = :name,
                description = :description,
                background_video = :background_video,
                background_image = :background_image,
                active = :active
                WHERE id = :id";
        
        Database::execute($sql, [
            ':id' => $stationId,
            ':name' => $_POST['name'],
            ':description' => $_POST['description'],
            ':background_video' => $_POST['background_video'],
            ':background_image' => $_POST['background_image'],
            ':active' => isset($_POST['active']) ? 1 : 0
        ]);
        
        // Update tag rules
        $station = new Station();
        $tagRules = [];
        foreach ($_POST['tags'] ?? [] as $tagId => $ruleType) {
            if ($ruleType !== 'none') {
                $tagRules[$tagId] = $ruleType;
            }
        }
        
        $station->setStationTags($stationId, $tagRules);
        
        $message = "✓ Station updated successfully!";
        $messageType = 'success';
        
        // Reload station data
        $stationData = Database::execute($sql, [$stationId])->fetch();
        $currentRules = Database::execute("SELECT tag_id, rule_type FROM station_tags WHERE station_id = ?", [$stationId])->fetchAll();
        $rulesByTagId = [];
        foreach ($currentRules as $rule) {
            $rulesByTagId[$rule['tag_id']] = $rule['rule_type'];
        }
        
    } catch (Exception $e) {
        $message = "✗ Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all tags for the form
$tags = Database::execute("SELECT * FROM tags ORDER BY category, name")->fetchAll();
$tagsByCategory = [];
foreach ($tags as $tag) {
    $tagsByCategory[$tag['category']][] = $tag;
}

// Get song count for this station
$station = new Station();
$songs = $station->getStationSongs($stationId);
$songCount = count($songs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Station - Stinkin' Park Admin</title>
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
            max-width: 900px;
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
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .station-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            color: #1976d2;
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
            font-family: inherit;
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
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
        
        .button-group {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
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
            <h1>📻 Edit Station: <?= htmlspecialchars($stationData['name']) ?></h1>
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

        <div class="card">
            <div class="station-info">
                <strong>Station Stats:</strong> 
                <?= $songCount ?> songs currently match this station's rules | 
                Slug: /stations/<?= htmlspecialchars($stationData['slug']) ?> | 
                Created: <?= date('M j, Y', strtotime($stationData['created_at'])) ?>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="name">Station Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= htmlspecialchars($stationData['name']) ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= htmlspecialchars($stationData['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="background_video">Background Video (filename)</label>
                    <input type="text" id="background_video" name="background_video" 
                           value="<?= htmlspecialchars($stationData['background_video'] ?? '') ?>"
                           placeholder="video.mp4">
                </div>

                <div class="form-group">
                    <label for="background_image">Background Image (filename)</label>
                    <input type="text" id="background_image" name="background_image" 
                           value="<?= htmlspecialchars($stationData['background_image'] ?? '') ?>"
                           placeholder="background.jpg">
                </div>

                <div class="form-group">
                    <label>Tag Rules</label>
                    
                    <div class="help-text">
                        <strong>Rule Types:</strong><br>
                        • <span class="rule-type-include">Include</span>: Songs with ANY of these tags<br>
                        • <span class="rule-type-require">Require</span>: Songs must have ALL of these tags<br>
                        • <span class="rule-type-exclude">Exclude</span>: Songs with these tags are NOT included<br>
                        • <span class="rule-type-none">None</span>: Tag not used for this station
                    </div>
                    
                    <?php foreach ($tagsByCategory as $category => $categoryTags): ?>
                    <div class="tag-rules">
                        <h4 style="margin-bottom: 10px; text-transform: uppercase; font-size: 12px; color: #666;">
                            <?= ucfirst($category) ?>
                        </h4>
                        <?php foreach ($categoryTags as $tag): 
                            $currentRule = $rulesByTagId[$tag['id']] ?? 'none';
                        ?>
                        <div class="tag-rule-row">
                            <div class="tag-name"><?= htmlspecialchars($tag['name']) ?></div>
                            <div class="rule-options">
                                <label>
                                    <input type="radio" name="tags[<?= $tag['id'] ?>]" value="none" 
                                           <?= $currentRule === 'none' ? 'checked' : '' ?>>
                                    None
                                </label>
                                <label>
                                    <input type="radio" name="tags[<?= $tag['id'] ?>]" value="include"
                                           <?= $currentRule === 'include' ? 'checked' : '' ?>>
                                    Include
                                </label>
                                <label>
                                    <input type="radio" name="tags[<?= $tag['id'] ?>]" value="require"
                                           <?= $currentRule === 'require' ? 'checked' : '' ?>>
                                    Require
                                </label>
                                <label>
                                    <input type="radio" name="tags[<?= $tag['id'] ?>]" value="exclude"
                                           <?= $currentRule === 'exclude' ? 'checked' : '' ?>>
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
                        <input type="checkbox" name="active" value="1" 
                               <?= $stationData['active'] ? 'checked' : '' ?>>
                        Station is active (visible to users)
                    </label>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="<?= BASE_URL ?>/stations/<?= htmlspecialchars($stationData['slug']) ?>"
                       class="btn btn-success" target="_blank">View Station</a>
                    <a href="stations.php" class="btn btn-secondary">Back to Stations</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>