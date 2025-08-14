<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Logger;

$logger = Logger::getInstance();

// Get date range from parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Analytics data collection
$analytics = [];

try {
    // Basic statistics
    $analytics['totals'] = Database::execute("
        SELECT 
            COUNT(*) as total_songs,
            COUNT(CASE WHEN active = 1 THEN 1 END) as active_songs,
            SUM(play_count) as total_plays,
            AVG(play_count) as avg_plays_per_song,
            SUM(CASE WHEN duration IS NOT NULL THEN duration ELSE 0 END) as total_duration,
            AVG(CASE WHEN duration IS NOT NULL THEN duration ELSE NULL END) as avg_duration,
            SUM(CASE WHEN file_size IS NOT NULL THEN file_size ELSE 0 END) as total_file_size
        FROM songs
    ")->fetch();

    // Upload trends (last 30 days)
    $analytics['upload_trends'] = Database::execute("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as uploads
        FROM songs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();

    // Tag usage statistics
    $analytics['tag_usage'] = Database::execute("
        SELECT 
            t.name,
            t.category,
            COUNT(st.song_id) as usage_count,
            ROUND(COUNT(st.song_id) * 100.0 / (SELECT COUNT(*) FROM songs), 2) as usage_percentage
        FROM tags t
        LEFT JOIN song_tags st ON t.id = st.tag_id
        GROUP BY t.id
        HAVING usage_count > 0
        ORDER BY usage_count DESC
        LIMIT 20
    ")->fetchAll();

    // Station statistics
    $analytics['station_stats'] = Database::execute("
        SELECT 
            s.name,
            s.slug,
            s.active,
            COUNT(DISTINCT st.tag_id) as tag_rules,
            (SELECT COUNT(DISTINCT song_id) 
             FROM song_tags st2 
             JOIN station_tags stat ON st2.tag_id = stat.tag_id 
             WHERE stat.station_id = s.id) as eligible_songs
        FROM stations s
        LEFT JOIN station_tags st ON s.id = st.station_id
        GROUP BY s.id
        ORDER BY s.name
    ")->fetchAll();

    // Most played songs
    $analytics['top_songs'] = Database::execute("
        SELECT 
            title,
            play_count,
            duration,
            created_at,
            GROUP_CONCAT(t.name SEPARATOR ', ') as tags
        FROM songs s
        LEFT JOIN song_tags st ON s.id = st.song_id
        LEFT JOIN tags t ON st.tag_id = t.id
        WHERE s.active = 1
        GROUP BY s.id
        ORDER BY play_count DESC
        LIMIT 10
    ")->fetchAll();

    // File size distribution
    $analytics['file_size_distribution'] = Database::execute("
        SELECT 
            CASE 
                WHEN file_size < 5242880 THEN 'Under 5MB'
                WHEN file_size < 10485760 THEN '5-10MB'
                WHEN file_size < 20971520 THEN '10-20MB'
                WHEN file_size < 52428800 THEN '20-50MB'
                ELSE 'Over 50MB'
            END as size_range,
            COUNT(*) as count
        FROM songs
        WHERE file_size IS NOT NULL
        GROUP BY size_range
        ORDER BY MIN(file_size)
    ")->fetchAll();

    // Duration distribution
    $analytics['duration_distribution'] = Database::execute("
        SELECT 
            CASE 
                WHEN duration < 120 THEN 'Under 2 min'
                WHEN duration < 180 THEN '2-3 min'
                WHEN duration < 240 THEN '3-4 min'
                WHEN duration < 300 THEN '4-5 min'
                ELSE 'Over 5 min'
            END as duration_range,
            COUNT(*) as count
        FROM songs
        WHERE duration IS NOT NULL
        GROUP BY duration_range
        ORDER BY MIN(duration)
    ")->fetchAll();

    // Category breakdown
    $analytics['category_breakdown'] = Database::execute("
        SELECT 
            t.category,
            COUNT(DISTINCT t.id) as tag_count,
            COUNT(st.song_id) as total_usage,
            ROUND(AVG(usage_per_tag.usage_count), 2) as avg_usage_per_tag
        FROM tags t
        LEFT JOIN song_tags st ON t.id = st.tag_id
        LEFT JOIN (
            SELECT tag_id, COUNT(*) as usage_count
            FROM song_tags
            GROUP BY tag_id
        ) usage_per_tag ON t.id = usage_per_tag.tag_id
        GROUP BY t.category
        ORDER BY total_usage DESC
    ")->fetchAll();

    // System health metrics
    $analytics['system_health'] = [
        'error_count_24h' => Database::execute("
            SELECT COUNT(*) as count 
            FROM system_logs 
            WHERE level = 'ERROR' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetch()['count'] ?? 0,
        
        'warning_count_24h' => Database::execute("
            SELECT COUNT(*) as count 
            FROM system_logs 
            WHERE level = 'WARNING' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetch()['count'] ?? 0,
        
        'recent_uploads' => Database::execute("
            SELECT COUNT(*) as count 
            FROM songs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetch()['count'] ?? 0,
        
        'database_size' => Database::execute("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ")->fetch()['size_mb'] ?? 0
    ];

} catch (Exception $e) {
    $logger->error("Analytics data collection failed", ['error' => $e->getMessage()], 'ANALYTICS');
    $analytics = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - Stinkin' Park Admin</title>
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
        
        .dashboard-grid {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .metric-card {
            text-align: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .metric-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .metric-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-detail {
            font-size: 12px;
            margin-top: 8px;
            opacity: 0.8;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .chart-placeholder {
            width: 100%;
            height: 100%;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-style: italic;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 4px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .tag-badge {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px;
            background: #e0e0e0;
            border-radius: 4px;
            font-size: 11px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-good { background: #28a745; }
        .status-warning { background: #ffc107; }
        .status-error { background: #dc3545; }
        
        .filters {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filters input,
        .filters select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .health-status {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .overview-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Analytics Dashboard - Stinkin' Park</h1>
            <nav class="nav">
                <a href="upload.php">Upload</a>
                <a href="manage.php">Manage Songs</a>
                <a href="analytics.php">Analytics</a>
                <a href="stations.php">Stations</a>
                <a href="logs.php">Logs</a>
            </nav>
        </div>

        <!-- System Health Overview -->
        <div class="card">
            <h2 class="section-title">🔋 System Health</h2>
            <div class="health-status">
                <div>
                    <span class="status-indicator <?= $analytics['system_health']['error_count_24h'] > 0 ? 'status-error' : 'status-good' ?>"></span>
                    <strong><?= $analytics['system_health']['error_count_24h'] ?></strong> errors (24h)
                </div>
                <div>
                    <span class="status-indicator <?= $analytics['system_health']['warning_count_24h'] > 5 ? 'status-warning' : 'status-good' ?>"></span>
                    <strong><?= $analytics['system_health']['warning_count_24h'] ?></strong> warnings (24h)
                </div>
                <div>
                    <span class="status-indicator status-good"></span>
                    <strong><?= $analytics['system_health']['recent_uploads'] ?></strong> uploads (7 days)
                </div>
                <div>
                    <span class="status-indicator <?= $analytics['system_health']['database_size'] > 1000 ? 'status-warning' : 'status-good' ?>"></span>
                    <strong><?= $analytics['system_health']['database_size'] ?>MB</strong> database size
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="overview-cards">
            <div class="card metric-card">
                <div class="metric-number"><?= number_format($analytics['totals']['total_songs']) ?></div>
                <div class="metric-label">Total Songs</div>
                <div class="metric-detail"><?= $analytics['totals']['active_songs'] ?> active</div>
            </div>
            
            <div class="card metric-card">
                <div class="metric-number"><?= number_format($analytics['totals']['total_plays']) ?></div>
                <div class="metric-label">Total Plays</div>
                <div class="metric-detail">Avg: <?= round($analytics['totals']['avg_plays_per_song'], 1) ?> per song</div>
            </div>
            
            <div class="card metric-card">
                <div class="metric-number"><?= gmdate("H:i", $analytics['totals']['total_duration']) ?></div>
                <div class="metric-label">Total Duration</div>
                <div class="metric-detail">Avg: <?= gmdate("i:s", $analytics['totals']['avg_duration']) ?> per song</div>
            </div>
            
            <div class="card metric-card">
                <div class="metric-number"><?= round($analytics['totals']['total_file_size'] / 1073741824, 1) ?>GB</div>
                <div class="metric-label">Storage Used</div>
                <div class="metric-detail"><?= count($analytics['tag_usage']) ?> active tags</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- Upload Trends -->
            <div class="card">
                <h2 class="section-title">📈 Upload Trends (30 Days)</h2>
                <div class="chart-placeholder">
                    Upload trend visualization
                    <br><small>Shows daily upload activity</small>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Uploads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($analytics['upload_trends'], 0, 7) as $trend): ?>
                        <tr>
                            <td><?= date('M j', strtotime($trend['date'])) ?></td>
                            <td><?= $trend['uploads'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Tags -->
            <div class="card">
                <h2 class="section-title">🏷️ Most Used Tags</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Category</th>
                            <th>Usage</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($analytics['tag_usage'], 0, 10) as $tag): ?>
                        <tr>
                            <td><?= htmlspecialchars($tag['name']) ?></td>
                            <td><span class="tag-badge"><?= ucfirst($tag['category']) ?></span></td>
                            <td><?= $tag['usage_count'] ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $tag['usage_percentage'] ?>%"></div>
                                </div>
                                <?= $tag['usage_percentage'] ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Full Width Sections -->
        <div class="card">
            <h2 class="section-title">🎵 Top Played Songs</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Plays</th>
                        <th>Duration</th>
                        <th>Tags</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['top_songs'] as $song): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($song['title']) ?></strong></td>
                        <td><?= $song['play_count'] ?></td>
                        <td><?= $song['duration'] ? gmdate("i:s", $song['duration']) : '-' ?></td>
                        <td>
                            <?php if ($song['tags']): ?>
                                <?php foreach (explode(', ', $song['tags']) as $tag): ?>
                                    <span class="tag-badge"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M j, Y', strtotime($song['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <!-- Stations Overview -->
            <div class="card">
                <h2 class="section-title">📻 Stations</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Station</th>
                            <th>Songs</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['station_stats'] as $station): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($station['name']) ?></strong>
                                <br><small><?= htmlspecialchars($station['slug']) ?></small>
                            </td>
                            <td><?= $station['eligible_songs'] ?></td>
                            <td>
                                <span class="status-indicator <?= $station['active'] ? 'status-good' : 'status-error' ?>"></span>
                                <?= $station['active'] ? 'Active' : 'Inactive' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- File Size Distribution -->
            <div class="card">
                <h2 class="section-title">💾 File Sizes</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Size Range</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['file_size_distribution'] as $dist): ?>
                        <tr>
                            <td><?= $dist['size_range'] ?></td>
                            <td><?= $dist['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Duration Distribution -->
            <div class="card">
                <h2 class="section-title">⏱️ Durations</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Duration</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analytics['duration_distribution'] as $dist): ?>
                        <tr>
                            <td><?= $dist['duration_range'] ?></td>
                            <td><?= $dist['count'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Analysis -->
        <div class="card">
            <h2 class="section-title">📊 Tag Category Analysis</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Number of Tags</th>
                        <th>Total Usage</th>
                        <th>Average Usage per Tag</th>
                        <th>Usage Distribution</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxUsage = max(array_column($analytics['category_breakdown'], 'total_usage'));
                    foreach ($analytics['category_breakdown'] as $category): 
                    ?>
                    <tr>
                        <td><strong><?= ucfirst($category['category']) ?></strong></td>
                        <td><?= $category['tag_count'] ?></td>
                        <td><?= $category['total_usage'] ?></td>
                        <td><?= $category['avg_usage_per_tag'] ?></td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= ($category['total_usage'] / $maxUsage) * 100 ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);

        // Add interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight current metrics
            const metricCards = document.querySelectorAll('.metric-card');
            metricCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 200);
                });
            });

            // Add tooltips to progress bars
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.title = `Usage: ${width}`;
            });

            // Add click handlers for table rows
            const tableRows = document.querySelectorAll('.data-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    // Add visual feedback
                    this.style.backgroundColor = '#e3f2fd';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 300);
                });
            });
        });

        // Export functionality
        function exportAnalytics() {
            const data = {
                totals: <?= json_encode($analytics['totals']) ?>,
                upload_trends: <?= json_encode($analytics['upload_trends']) ?>,
                tag_usage: <?= json_encode($analytics['tag_usage']) ?>,
                top_songs: <?= json_encode($analytics['top_songs']) ?>,
                generated_at: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `stinkin-park-analytics-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add export button
        const header = document.querySelector('.header');
        const exportBtn = document.createElement('button');
        exportBtn.textContent = '📤 Export Data';
        exportBtn.className = 'btn btn-secondary';
        exportBtn.onclick = exportAnalytics;
        exportBtn.style.float = 'right';
        exportBtn.style.marginTop = '10px';
        header.appendChild(exportBtn);
    </script>
</body>
</html>