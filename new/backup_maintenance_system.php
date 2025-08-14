<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Logger;

$logger = Logger::getInstance();

// Maintenance and backup operations
class MaintenanceSystem
{
    private Logger $logger;
    private string $backupDir;
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->backupDir = dirname(__DIR__) . '/backups';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create a complete database backup
     */
    public function createDatabaseBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "stinkin_park_backup_{$timestamp}.sql";
        $filepath = $this->backupDir . '/' . $filename;
        
        try {
            // Get database connection details
            $config = $this->getDatabaseConfig();
            
            // Build mysqldump command
            $command = sprintf(
                'mysqldump -h%s -u%s %s %s > %s 2>&1',
                escapeshellarg($config['host']),
                escapeshellarg($config['username']),
                !empty($config['password']) ? '-p' . escapeshellarg($config['password']) : '',
                escapeshellarg($config['database']),
                escapeshellarg($filepath)
            );
            
            // Execute backup
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Backup failed: " . implode("\n", $output));
            }
            
            // Verify backup file was created and has content
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                throw new Exception("Backup file is empty or was not created");
            }
            
            // Compress the backup
            $gzFilepath = $filepath . '.gz';
            if (function_exists('gzencode')) {
                $data = file_get_contents($filepath);
                file_put_contents($gzFilepath, gzencode($data, 9));
                unlink($filepath); // Remove uncompressed version
                $filepath = $gzFilepath;
                $filename .= '.gz';
            }
            
            $this->logger->info("Database backup created", [
                'filename' => $filename,
                'size_bytes' => filesize($filepath)
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'size_formatted' => $this->formatBytes(filesize($filepath))
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Database backup failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a backup of audio files
     */
    public function createAudioBackup(): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "audio_files_backup_{$timestamp}.tar.gz";
        $filepath = $this->backupDir . '/' . $filename;
        $audioDir = dirname(__DIR__) . '/audio';
        
        try {
            if (!is_dir($audioDir)) {
                throw new Exception("Audio directory not found");
            }
            
            // Count files first
            $fileCount = count(glob($audioDir . '/*.{mp3,wav,flac,ogg}', GLOB_BRACE));
            
            if ($fileCount === 0) {
                throw new Exception("No audio files found to backup");
            }
            
            // Create tar.gz archive
            $command = sprintf(
                'cd %s && tar -czf %s audio/ 2>&1',
                escapeshellarg(dirname($audioDir)),
                escapeshellarg($filepath)
            );
            
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Audio backup failed: " . implode("\n", $output));
            }
            
            if (!file_exists($filepath)) {
                throw new Exception("Backup archive was not created");
            }
            
            $this->logger->info("Audio backup created", [
                'filename' => $filename,
                'file_count' => $fileCount,
                'size_bytes' => filesize($filepath)
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'file_count' => $fileCount,
                'size' => filesize($filepath),
                'size_formatted' => $this->formatBytes(filesize($filepath))
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Audio backup failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean old log entries
     */
    public function cleanOldLogs(int $daysToKeep = 30): array
    {
        try {
            $sql = "DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = Database::execute($sql, [$daysToKeep]);
            $deletedCount = $stmt->rowCount();
            
            $this->logger->info("Old logs cleaned", [
                'days_kept' => $daysToKeep,
                'deleted_count' => $deletedCount
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'days_kept' => $daysToKeep
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Log cleanup failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Optimize database tables
     */
    public function optimizeDatabase(): array
    {
        try {
            $tables = ['songs', 'tags', 'song_tags', 'stations', 'station_tags', 'system_logs'];
            $results = [];
            
            foreach ($tables as $table) {
                $sql = "OPTIMIZE TABLE $table";
                $result = Database::execute($sql)->fetch();
                $results[$table] = $result;
            }
            
            // Update tag usage counts
            $sql = "
                UPDATE tags SET 
                usage_count = (
                    SELECT COUNT(*) FROM song_tags WHERE tag_id = tags.id
                ),
                last_used = (
                    SELECT MAX(s.created_at) 
                    FROM song_tags st 
                    JOIN songs s ON st.song_id = s.id 
                    WHERE st.tag_id = tags.id
                )
            ";
            Database::execute($sql);
            
            $this->logger->info("Database optimized", [
                'tables_optimized' => count($tables)
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'tables_optimized' => count($tables),
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Database optimization failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for orphaned files
     */
    public function findOrphanedFiles(): array
    {
        try {
            $audioDir = dirname(__DIR__) . '/audio';
            $orphanedFiles = [];
            
            if (!is_dir($audioDir)) {
                return ['success' => false, 'error' => 'Audio directory not found'];
            }
            
            // Get all audio files
            $audioFiles = glob($audioDir . '/*.{mp3,wav,flac,ogg}', GLOB_BRACE);
            
            // Get all filenames from database
            $sql = "SELECT filename FROM songs";
            $dbFilenames = Database::execute($sql)->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($audioFiles as $file) {
                $filename = basename($file);
                if (!in_array($filename, $dbFilenames)) {
                    $orphanedFiles[] = [
                        'filename' => $filename,
                        'size' => filesize($file),
                        'size_formatted' => $this->formatBytes(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            }
            
            $this->logger->info("Orphaned files check completed", [
                'total_files' => count($audioFiles),
                'orphaned_count' => count($orphanedFiles)
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'total_files' => count($audioFiles),
                'orphaned_files' => $orphanedFiles,
                'orphaned_count' => count($orphanedFiles)
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Orphaned files check failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check for missing files
     */
    public function findMissingFiles(): array
    {
        try {
            $audioDir = dirname(__DIR__) . '/audio';
            $missingFiles = [];
            
            // Get all filenames from database
            $sql = "SELECT id, title, filename FROM songs WHERE active = 1";
            $dbSongs = Database::execute($sql)->fetchAll();
            
            foreach ($dbSongs as $song) {
                $filepath = $audioDir . '/' . $song['filename'];
                if (!file_exists($filepath)) {
                    $missingFiles[] = [
                        'id' => $song['id'],
                        'title' => $song['title'],
                        'filename' => $song['filename']
                    ];
                }
            }
            
            $this->logger->info("Missing files check completed", [
                'total_songs' => count($dbSongs),
                'missing_count' => count($missingFiles)
            ], 'MAINTENANCE');
            
            return [
                'success' => true,
                'total_songs' => count($dbSongs),
                'missing_files' => $missingFiles,
                'missing_count' => count($missingFiles)
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Missing files check failed", ['error' => $e->getMessage()], 'MAINTENANCE');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get system status
     */
    public function getSystemStatus(): array
    {
        try {
            $status = [];
            
            // Database size
            $sql = "
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ";
            $status['database_size_mb'] = Database::execute($sql)->fetch()['size_mb'] ?? 0;
            
            // Storage usage
            $audioDir = dirname(__DIR__) . '/audio';
            $status['audio_storage'] = $this->getDirectorySize($audioDir);
            
            // Log storage
            $logDir = dirname(__DIR__) . '/logs';
            $status['log_storage'] = $this->getDirectorySize($logDir);
            
            // Recent activity
            $sql = "SELECT COUNT(*) as count FROM songs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $status['recent_uploads'] = Database::execute($sql)->fetch()['count'];
            
            $sql = "SELECT COUNT(*) as count FROM system_logs WHERE level = 'ERROR' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            $status['recent_errors'] = Database::execute($sql)->fetch()['count'];
            
            // Backup info
            $status['backup_info'] = $this->getBackupInfo();
            
            return [
                'success' => true,
                'status' => $status
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get directory size recursively
     */
    private function getDirectorySize(string $directory): array
    {
        if (!is_dir($directory)) {
            return ['size_bytes' => 0, 'size_formatted' => '0 B', 'file_count' => 0];
        }
        
        $size = 0;
        $count = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $count++;
            }
        }
        
        return [
            'size_bytes' => $size,
            'size_formatted' => $this->formatBytes($size),
            'file_count' => $count
        ];
    }
    
    /**
     * Get backup information
     */
    private function getBackupInfo(): array
    {
        $backups = [];
        $totalSize = 0;
        
        if (is_dir($this->backupDir)) {
            $files = glob($this->backupDir . '/*.{sql,sql.gz,tar.gz}', GLOB_BRACE);
            
            foreach ($files as $file) {
                $size = filesize($file);
                $totalSize += $size;
                
                $backups[] = [
                    'filename' => basename($file),
                    'size' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'created' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
            
            // Sort by creation time, newest first
            usort($backups, function($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });
        }
        
        return [
            'backup_count' => count($backups),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'recent_backups' => array_slice($backups, 0, 5),
            'all_backups' => $backups
        ];
    }
    
    /**
     * Get database configuration from environment
     */
    private function getDatabaseConfig(): array
    {
        // This would need to be adapted based on your actual config structure
        return [
            'host' => 'localhost',
            'database' => 'stinkin_park_music', // You'd read this from your config
            'username' => 'stinkin_user',       // You'd read this from your config
            'password' => 'your_password'       // You'd read this from your config
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

$maintenance = new MaintenanceSystem();
$message = '';
$messageType = '';
$results = [];

// Handle maintenance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'backup_database':
            $results['backup_database'] = $maintenance->createDatabaseBackup();
            break;
            
        case 'backup_audio':
            $results['backup_audio'] = $maintenance->createAudioBackup();
            break;
            
        case 'clean_logs':
            $daysToKeep = (int)($_POST['days_to_keep'] ?? 30);
            $results['clean_logs'] = $maintenance->cleanOldLogs($daysToKeep);
            break;
            
        case 'optimize_database':
            $results['optimize_database'] = $maintenance->optimizeDatabase();
            break;
            
        case 'check_orphaned':
            $results['check_orphaned'] = $maintenance->findOrphanedFiles();
            break;
            
        case 'check_missing':
            $results['check_missing'] = $maintenance->findMissingFiles();
            break;
    }
}

// Get system status
$systemStatus = $maintenance->getSystemStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance - Stinkin' Park Admin</title>
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
        
        .status-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .status-number {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
        }
        
        .status-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .maintenance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .maintenance-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .maintenance-action {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .action-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .action-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .action-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
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
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .result-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .result-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .progress-indicator {
            display: none;
            margin-top: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #667eea;
            width: 0%;
            transition: width 0.3s;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .file-list {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .backup-list {
            margin-top: 15px;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .backup-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .backup-name {
            font-weight: 600;
            color: #333;
        }
        
        .backup-details {
            font-size: 12px;
            color: #666;
        }
        
        .warning-notice {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
            border-left: 4px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .maintenance-grid {
                grid-template-columns: 1fr;
            }
            
            .status-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-controls {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 System Maintenance - Stinkin' Park</h1>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="analytics.php">Analytics</a>
                <a href="maintenance.php">Maintenance</a>
                <a href="logs.php">Logs</a>
            </nav>
        </div>

        <!-- System Status Overview -->
        <?php if ($systemStatus['success']): ?>
        <div class="status-overview">
            <div class="status-card">
                <div class="status-number"><?= $systemStatus['status']['database_size_mb'] ?>MB</div>
                <div class="status-label">Database Size</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $systemStatus['status']['audio_storage']['size_formatted'] ?></div>
                <div class="status-label">Audio Storage</div>
            </div>
            <div class="status-card">
                <div class="status-number"><?= $systemStatus['status']['recent_uploads'] ?></div>
                <div class="status-label">Recent Uploads (7d)</div>
            </div>
            <div class="status-card">
                <div class="status-number" style="color: <?= $systemStatus['status']['recent_errors'] > 0 ? '#dc3545' : '#28a745' ?>">
                    <?= $systemStatus['status']['recent_errors'] ?>
                </div>
                <div class="status-label">Recent Errors (24h)</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="maintenance-grid">
            <!-- Backup Operations -->
            <div class="maintenance-section">
                <h2 class="section-title">💾 Backup Operations</h2>
                
                <div class="maintenance-action">
                    <div class="action-title">Database Backup</div>
                    <div class="action-description">
                        Create a compressed backup of the entire database including all songs, tags, and system data.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="backup_database">
                            <button type="submit" class="btn btn-primary">Create Database Backup</button>
                        </form>
                    </div>
                    <?php if (isset($results['backup_database'])): ?>
                    <div class="result-box <?= $results['backup_database']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['backup_database']['success']): ?>
                            ✓ Database backup created: <?= $results['backup_database']['filename'] ?> 
                            (<?= $results['backup_database']['size_formatted'] ?>)
                        <?php else: ?>
                            ✗ Backup failed: <?= $results['backup_database']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="maintenance-action">
                    <div class="action-title">Audio Files Backup</div>
                    <div class="action-description">
                        Create a compressed archive of all audio files. This may take several minutes for large collections.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="backup_audio">
                            <button type="submit" class="btn btn-warning">Create Audio Backup</button>
                        </form>
                    </div>
                    <?php if (isset($results['backup_audio'])): ?>
                    <div class="result-box <?= $results['backup_audio']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['backup_audio']['success']): ?>
                            ✓ Audio backup created: <?= $results['backup_audio']['filename'] ?> 
                            (<?= $results['backup_audio']['file_count'] ?> files, <?= $results['backup_audio']['size_formatted'] ?>)
                        <?php else: ?>
                            ✗ Audio backup failed: <?= $results['backup_audio']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Backups -->
                <?php if ($systemStatus['success'] && !empty($systemStatus['status']['backup_info']['recent_backups'])): ?>
                <div class="backup-list">
                    <h4 style="margin-bottom: 10px; color: #666;">Recent Backups</h4>
                    <?php foreach ($systemStatus['status']['backup_info']['recent_backups'] as $backup): ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name"><?= htmlspecialchars($backup['filename']) ?></div>
                            <div class="backup-details"><?= $backup['created'] ?> • <?= $backup['size_formatted'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Database Maintenance -->
            <div class="maintenance-section">
                <h2 class="section-title">🗄️ Database Maintenance</h2>
                
                <div class="maintenance-action">
                    <div class="action-title">Optimize Database</div>
                    <div class="action-description">
                        Optimize all database tables and update tag usage statistics for better performance.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="optimize_database">
                            <button type="submit" class="btn btn-success">Optimize Database</button>
                        </form>
                    </div>
                    <?php if (isset($results['optimize_database'])): ?>
                    <div class="result-box <?= $results['optimize_database']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['optimize_database']['success']): ?>
                            ✓ Database optimized successfully (<?= $results['optimize_database']['tables_optimized'] ?> tables)
                        <?php else: ?>
                            ✗ Optimization failed: <?= $results['optimize_database']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="maintenance-action">
                    <div class="action-title">Clean Old Logs</div>
                    <div class="action-description">
                        Remove old system log entries to reduce database size. Specify how many days to keep.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="action" value="clean_logs">
                            <input type="number" name="days_to_keep" value="30" min="7" max="365" 
                                   style="width: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <span style="font-size: 14px;">days</span>
                            <button type="submit" class="btn btn-warning">Clean Logs</button>
                        </form>
                    </div>
                    <?php if (isset($results['clean_logs'])): ?>
                    <div class="result-box <?= $results['clean_logs']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['clean_logs']['success']): ?>
                            ✓ Cleaned <?= $results['clean_logs']['deleted_count'] ?> old log entries 
                            (kept <?= $results['clean_logs']['days_kept'] ?> days)
                        <?php else: ?>
                            ✗ Log cleanup failed: <?= $results['clean_logs']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- File System Checks -->
            <div class="maintenance-section">
                <h2 class="section-title">📁 File System Checks</h2>
                
                <div class="maintenance-action">
                    <div class="action-title">Check for Orphaned Files</div>
                    <div class="action-description">
                        Find audio files that exist on disk but are not referenced in the database.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="check_orphaned">
                            <button type="submit" class="btn btn-primary">Check Orphaned Files</button>
                        </form>
                    </div>
                    <?php if (isset($results['check_orphaned'])): ?>
                    <div class="result-box <?= $results['check_orphaned']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['check_orphaned']['success']): ?>
                            ✓ Found <?= $results['check_orphaned']['orphaned_count'] ?> orphaned files 
                            out of <?= $results['check_orphaned']['total_files'] ?> total files
                            
                            <?php if (!empty($results['check_orphaned']['orphaned_files'])): ?>
                            <div class="file-list">
                                <?php foreach ($results['check_orphaned']['orphaned_files'] as $file): ?>
                                <div class="file-item">
                                    <span><?= htmlspecialchars($file['filename']) ?></span>
                                    <span><?= $file['size_formatted'] ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            ✗ Check failed: <?= $results['check_orphaned']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="maintenance-action">
                    <div class="action-title">Check for Missing Files</div>
                    <div class="action-description">
                        Find songs in the database that reference missing audio files.
                    </div>
                    <div class="action-controls">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="check_missing">
                            <button type="submit" class="btn btn-danger">Check Missing Files</button>
                        </form>
                    </div>
                    <?php if (isset($results['check_missing'])): ?>
                    <div class="result-box <?= $results['check_missing']['success'] ? 'result-success' : 'result-error' ?>">
                        <?php if ($results['check_missing']['success']): ?>
                            <?php if ($results['check_missing']['missing_count'] > 0): ?>
                                ⚠️ Found <?= $results['check_missing']['missing_count'] ?> missing files 
                                out of <?= $results['check_missing']['total_songs'] ?> active songs
                                
                                <div class="file-list">
                                    <?php foreach ($results['check_missing']['missing_files'] as $file): ?>
                                    <div class="file-item">
                                        <span><strong><?= htmlspecialchars($file['title']) ?></strong></span>
                                        <span><?= htmlspecialchars($file['filename']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                ✓ No missing files found (<?= $results['check_missing']['total_songs'] ?> songs checked)
                            <?php endif; ?>
                        <?php else: ?>
                            ✗ Check failed: <?= $results['check_missing']['error'] ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="warning-notice">
                    <strong>⚠️ Important:</strong> Always create backups before performing any maintenance operations that modify files or data.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add loading indicators to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                const originalText = button.textContent;
                
                button.disabled = true;
                button.textContent = 'Processing...';
                
                // Create and show progress indicator
                const progressDiv = document.createElement('div');
                progressDiv.className = 'progress-indicator';
                progressDiv.style.display = 'block';
                progressDiv.innerHTML = `
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                `;
                
                this.appendChild(progressDiv);
                
                // Animate progress bar
                setTimeout(() => {
                    progressDiv.querySelector('.progress-fill').style.width = '100%';
                }, 100);
            });
        });

        // Auto-refresh page every 30 seconds if no operations are running
        if (!document.querySelector('.progress-indicator[style*="block"]')) {
            setTimeout(() => {
                location.reload();
            }, 30000);
        }
    </script>
</body>
</html>