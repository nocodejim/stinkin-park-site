<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Logger;

$logger = Logger::getInstance();

// Handle clear logs action
if (isset($_POST['clear_logs'])) {
    $daysToKeep = (int)($_POST['days_to_keep'] ?? 0);
    $deleted = $logger->clearOldLogs($daysToKeep);
    $message = "Cleared $deleted old log entries";
}

// Get filter parameters
$levelFilter = $_GET['level'] ?? null;
$categoryFilter = $_GET['category'] ?? null;
$limit = (int)($_GET['limit'] ?? 100);

// Get logs
$logs = $logger->getRecentLogs($limit, $levelFilter, $categoryFilter);

// Get unique categories for filter dropdown
$categories = \StinkinPark\Database::execute("
    SELECT DISTINCT category 
    FROM system_logs 
    WHERE category IS NOT NULL 
    ORDER BY category
")->fetchAll(\PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Stinkin' Park Admin</title>
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
        
        .nav {
            margin-top: 10px;
        }
        
        .nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .filter-bar {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }
        
        select, input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .log-table {
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
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            font-size: 14px;
        }
        
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .level-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .level-debug { background: #e0e0e0; color: #666; }
        .level-info { background: #e3f2fd; color: #1976d2; }
        .level-warning { background: #fff3e0; color: #f57c00; }
        .level-error { background: #ffebee; color: #c62828; }
        .level-critical { background: #c62828; color: white; }
        
        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            background: #f0f0f0;
            color: #666;
        }
        
        .message-cell {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .context-cell {
            max-width: 300px;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #666;
        }
        
        .context-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
        }
        
        .context-preview:hover {
            background: #e3f2fd;
            padding: 2px 4px;
            border-radius: 2px;
        }
        
        .timestamp {
            color: #666;
            font-size: 12px;
        }
        
        .stats-bar {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .stat {
            display: flex;
            flex-direction: column;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #333;
        }
        
        .auto-refresh {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message {
            padding: 10px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            margin-bottom: 10px;
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
            right: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .auto-refresh {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .log-table {
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
            <h1>📊 System Logs</h1>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="stations.php">Stations</a>
                <a href="logs.php">Logs</a>
            </nav>
        </div>

        <?php if (isset($message)): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="stats-bar">
            <div class="stat">
                <span class="stat-label">Total Logs</span>
                <span class="stat-value"><?= count($logs) ?></span>
            </div>
            <div class="stat">
                <span class="stat-label">Errors</span>
                <span class="stat-value" style="color: #dc3545;">
                    <?= count(array_filter($logs, fn($l) => $l['level'] === 'ERROR')) ?>
                </span>
            </div>
            <div class="stat">
                <span class="stat-label">Warnings</span>
                <span class="stat-value" style="color: #ffc107;">
                    <?= count(array_filter($logs, fn($l) => $l['level'] === 'WARNING')) ?>
                </span>
            </div>
            <div class="auto-refresh">
                <label>
                    <input type="checkbox" id="auto-refresh"> Auto-refresh (5s)
                </label>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
                <div class="filter-group">
                    <label for="level">Level</label>
                    <select name="level" id="level">
                        <option value="">All Levels</option>
                        <option value="DEBUG" <?= $levelFilter === 'DEBUG' ? 'selected' : '' ?>>Debug</option>
                        <option value="INFO" <?= $levelFilter === 'INFO' ? 'selected' : '' ?>>Info</option>
                        <option value="WARNING" <?= $levelFilter === 'WARNING' ? 'selected' : '' ?>>Warning</option>
                        <option value="ERROR" <?= $levelFilter === 'ERROR' ? 'selected' : '' ?>>Error</option>
                        <option value="CRITICAL" <?= $levelFilter === 'CRITICAL' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" 
                                <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="limit">Limit</label>
                    <input type="number" name="limit" id="limit" value="<?= $limit ?>" min="10" max="1000">
                </div>
                
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="logs.php" class="btn btn-secondary">Reset</a>
            </form>
            
            <form method="POST" style="margin-left: auto;">
                <input type="hidden" name="days_to_keep" value="7">
                <button type="submit" name="clear_logs" class="btn btn-danger" 
                        onclick="return confirm('Clear logs older than 7 days?')">
                    Clear Old Logs
                </button>
            </form>
        </div>

        <div class="log-table">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Level</th>
                        <th>Category</th>
                        <th>Message</th>
                        <th>Context</th>
                        <th>Request</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="timestamp">
                            <?= date('H:i:s', strtotime($log['created_at'])) ?><br>
                            <small><?= date('Y-m-d', strtotime($log['created_at'])) ?></small>
                        </td>
                        <td>
                            <span class="level-badge level-<?= strtolower($log['level']) ?>">
                                <?= htmlspecialchars($log['level']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['category']): ?>
                            <span class="category-badge">
                                <?= htmlspecialchars($log['category']) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="message-cell" title="<?= htmlspecialchars($log['message']) ?>">
                            <?= htmlspecialchars($log['message']) ?>
                        </td>
                        <td class="context-cell">
                            <?php if ($log['context']): ?>
                            <div class="context-preview" onclick="showContext(<?= $log['id'] ?>)">
                                <?= htmlspecialchars(substr($log['context'], 0, 50)) ?>...
                            </div>
                            <div id="context-<?= $log['id'] ?>" style="display:none;">
                                <?= htmlspecialchars($log['context']) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['request_uri']): ?>
                            <small><?= htmlspecialchars($log['request_uri']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="contextModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h3>Context Data</h3>
            <pre id="modalContent"></pre>
        </div>
    </div>

    <script>
        // Auto-refresh functionality
        let refreshInterval;
        document.getElementById('auto-refresh').addEventListener('change', function() {
            if (this.checked) {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 5000);
            } else {
                clearInterval(refreshInterval);
            }
        });

        // Show context in modal
        function showContext(id) {
            const contextData = document.getElementById('context-' + id).textContent;
            try {
                const parsed = JSON.parse(contextData);
                document.getElementById('modalContent').textContent = JSON.stringify(parsed, null, 2);
            } catch {
                document.getElementById('modalContent').textContent = contextData;
            }
            document.getElementById('contextModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('contextModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('contextModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>