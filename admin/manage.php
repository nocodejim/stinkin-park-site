<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Song.php';

use StinkinPark\Song;

$song = new Song();

// Handle delete action
if (isset($_GET['delete'])) {
    try {
        $song->delete((int)$_GET['delete']);
        header('Location: manage.php?msg=deleted');
        exit;
    } catch (Exception $e) {
        $error = "Failed to delete song";
    }
}

// Get all songs
$songs = $song->getAllWithTags();
$totalSongs = $song->getTotalCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Songs - Stinkin' Park Admin</title>
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
        
        h1 {
            color: #333;
            font-size: 24px;
        }
        
        .stats {
            margin-top: 10px;
            color: #666;
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
        
        .table-container {
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
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .tag-badge {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            background: #e0e0e0;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .tag-badge.genre {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .tag-badge.mood {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .tag-badge.situational {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .tag-badge.style {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .tag-badge.intensity {
            background: #ffebee;
            color: #c62828;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: #667eea;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-play {
            background: #28a745;
            color: white;
        }
        
        .status-active {
            color: #28a745;
            font-weight: 600;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: 600;
        }
        
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎵 Stinkin' Park - Manage Songs</h1>
            <div class="stats">Total Songs: <?= $totalSongs ?></div>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="stations.php">Stations</a>
            </nav>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="message">
            Song deleted successfully!
        </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Tags</th>
                        <th>Duration</th>
                        <th>Plays</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($songs as $songItem): ?>
                    <tr>
                        <td><?= $songItem['id'] ?></td>
                        <td><strong><?= htmlspecialchars($songItem['title']) ?></strong></td>
                        <td>
                            <?php 
                            if ($songItem['tag_details']) {
                                $tagDetails = explode('|', $songItem['tag_details']);
                                foreach ($tagDetails as $detail) {
                                    list($id, $name, $category) = explode(':', $detail);
                                    echo '<span class="tag-badge ' . $category . '">' . 
                                         htmlspecialchars($name) . '</span>';
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?= $songItem['duration'] ? 
                                gmdate("i:s", $songItem['duration']) : 
                                '-' ?>
                        </td>
                        <td><?= $songItem['play_count'] ?></td>
                        <td>
                            <?php if ($songItem['active']): ?>
                                <span class="status-active">Active</span>
                            <?php else: ?>
                                <span class="status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="<?= BASE_URL ?>/audio/<?= $songItem['filename'] ?>"
                                   class="btn-small btn-play" 
                                   target="_blank">▶</a>
                                <a href="edit.php?id=<?= $songItem['id'] ?>" 
                                   class="btn-small btn-edit">Edit</a>
                                <a href="manage.php?delete=<?= $songItem['id'] ?>" 
                                   class="btn-small btn-delete"
                                   onclick="return confirm('Delete this song?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
