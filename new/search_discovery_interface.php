<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Logger.php';

use StinkinPark\Database;
use StinkinPark\Logger;

$logger = Logger::getInstance();

// Search parameters
$query = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'all';
$tag = $_GET['tag'] ?? '';
$sort = $_GET['sort'] ?? 'relevance';
$limit = (int)($_GET['limit'] ?? 20);
$offset = (int)($_GET['offset'] ?? 0);

$results = [
    'songs' => [],
    'stations' => [],
    'tags' => [],
    'total_songs' => 0,
    'total_stations' => 0,
    'total_tags' => 0
];

if (!empty($query) || !empty($tag)) {
    try {
        // Search songs
        if ($category === 'all' || $category === 'songs') {
            $songSql = "
                SELECT DISTINCT
                    s.*,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags,
                    MATCH(s.title) AGAINST(? IN BOOLEAN MODE) as relevance_score
                FROM songs s
                LEFT JOIN song_tags st ON s.id = st.song_id
                LEFT JOIN tags t ON st.tag_id = t.id
                WHERE s.active = 1
            ";
            
            $songParams = [];
            $conditions = [];
            
            if (!empty($query)) {
                $conditions[] = "(MATCH(s.title) AGAINST(? IN BOOLEAN MODE) OR s.title LIKE ?)";
                $songParams[] = $query;
                $songParams[] = "%$query%";
            }
            
            if (!empty($tag)) {
                $conditions[] = "s.id IN (
                    SELECT DISTINCT song_id 
                    FROM song_tags st2 
                    JOIN tags t2 ON st2.tag_id = t2.id 
                    WHERE t2.name LIKE ?
                )";
                $songParams[] = "%$tag%";
            }
            
            if (!empty($conditions)) {
                $songSql .= " AND (" . implode(' AND ', $conditions) . ")";
            }
            
            $songSql .= " GROUP BY s.id";
            
            // Apply sorting
            switch ($sort) {
                case 'title':
                    $songSql .= " ORDER BY s.title ASC";
                    break;
                case 'newest':
                    $songSql .= " ORDER BY s.created_at DESC";
                    break;
                case 'popular':
                    $songSql .= " ORDER BY s.play_count DESC";
                    break;
                case 'duration':
                    $songSql .= " ORDER BY s.duration DESC";
                    break;
                default: // relevance
                    if (!empty($query)) {
                        $songSql .= " ORDER BY relevance_score DESC, s.play_count DESC";
                    } else {
                        $songSql .= " ORDER BY s.play_count DESC";
                    }
            }
            
            $songSql .= " LIMIT ? OFFSET ?";
            $songParams[] = $limit;
            $songParams[] = $offset;
            
            $results['songs'] = Database::execute($songSql, $songParams)->fetchAll();
            
            // Get total count for pagination
            $countSql = str_replace(
                "SELECT DISTINCT s.*, GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags, MATCH(s.title) AGAINST(? IN BOOLEAN MODE) as relevance_score",
                "SELECT COUNT(DISTINCT s.id) as total",
                $songSql
            );
            $countSql = preg_replace('/ORDER BY.*?LIMIT.*?OFFSET.*?$/', '', $countSql);
            
            if (!empty($query)) {
                array_pop($songParams); // Remove offset
                array_pop($songParams); // Remove limit
                $results['total_songs'] = Database::execute($countSql, $songParams)->fetch()['total'];
            } else {
                $countParams = array_slice($songParams, 0, -2); // Remove limit and offset
                $results['total_songs'] = Database::execute($countSql, $countParams)->fetch()['total'];
            }
        }
        
        // Search stations
        if ($category === 'all' || $category === 'stations') {
            $stationSql = "
                SELECT 
                    s.*,
                    COUNT(DISTINCT st.tag_id) as tag_count,
                    MATCH(s.name, s.description) AGAINST(? IN BOOLEAN MODE) as relevance_score
                FROM stations s
                LEFT JOIN station_tags st ON s.id = st.station_id
                WHERE s.active = 1
            ";
            
            $stationParams = [];
            
            if (!empty($query)) {
                $stationSql .= " AND (MATCH(s.name, s.description) AGAINST(? IN BOOLEAN MODE) OR s.name LIKE ? OR s.description LIKE ?)";
                $stationParams[] = $query;
                $stationParams[] = $query;
                $stationParams[] = "%$query%";
                $stationParams[] = "%$query%";
            }
            
            $stationSql .= " GROUP BY s.id ORDER BY relevance_score DESC, s.name ASC LIMIT ?";
            $stationParams[] = $limit;
            
            $results['stations'] = Database::execute($stationSql, $stationParams)->fetchAll();
            $results['total_stations'] = count($results['stations']);
        }
        
        // Search tags
        if ($category === 'all' || $category === 'tags') {
            $tagSql = "
                SELECT 
                    t.*,
                    COUNT(st.song_id) as usage_count,
                    MATCH(t.name) AGAINST(? IN BOOLEAN MODE) as relevance_score
                FROM tags t
                LEFT JOIN song_tags st ON t.id = st.tag_id
                WHERE 1=1
            ";
            
            $tagParams = [];
            
            if (!empty($query)) {
                $tagSql .= " AND (MATCH(t.name) AGAINST(? IN BOOLEAN MODE) OR t.name LIKE ?)";
                $tagParams[] = $query;
                $tagParams[] = $query;
                $tagParams[] = "%$query%";
            }
            
            $tagSql .= " GROUP BY t.id HAVING usage_count > 0 ORDER BY relevance_score DESC, usage_count DESC LIMIT ?";
            $tagParams[] = $limit;
            
            $results['tags'] = Database::execute($tagSql, $tagParams)->fetchAll();
            $results['total_tags'] = count($results['tags']);
        }
        
        // Log search query
        $logger->info("Search performed", [
            'query' => $query,
            'category' => $category,
            'tag' => $tag,
            'results' => [
                'songs' => count($results['songs']),
                'stations' => count($results['stations']),
                'tags' => count($results['tags'])
            ]
        ], 'SEARCH');
        
    } catch (Exception $e) {
        $logger->error("Search failed", [
            'query' => $query,
            'error' => $e->getMessage()
        ], 'SEARCH');
    }
}

// Get popular tags for suggestions
$popularTags = Database::execute("
    SELECT t.name, COUNT(st.song_id) as usage_count
    FROM tags t
    JOIN song_tags st ON t.id = st.tag_id
    GROUP BY t.id
    ORDER BY usage_count DESC
    LIMIT 15
")->fetchAll();

// Get recent additions
$recentSongs = Database::execute("
    SELECT s.*, GROUP_CONCAT(t.name SEPARATOR ', ') as tags
    FROM songs s
    LEFT JOIN song_tags st ON s.id = st.song_id
    LEFT JOIN tags t ON st.tag_id = t.id
    WHERE s.active = 1
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($query) ? "Search: " . htmlspecialchars($query) . " - " : "" ?>Discover Music - Stinkin' Park</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(20px);
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .search-hero {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .search-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .search-subtitle {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }
        
        .search-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            padding: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .search-input {
            flex: 1;
            background: none;
            border: none;
            padding: 15px 20px;
            font-size: 16px;
            color: white;
            outline: none;
        }
        
        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .search-filters {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 8px 15px;
            color: white;
            font-size: 14px;
            outline: none;
        }
        
        .filter-select option {
            background: #2a5298;
            color: white;
        }
        
        .search-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .search-suggestions {
            margin-top: 20px;
            text-align: center;
        }
        
        .suggestion-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }
        
        .tag-suggestion {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .tag-suggestion:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .results-section {
            margin-top: 40px;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .results-info {
            font-size: 18px;
            font-weight: 600;
        }
        
        .results-count {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .sort-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .sort-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .results-grid {
            display: grid;
            gap: 20px;
        }
        
        .results-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            padding: 5px;
        }
        
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 12px 20px;
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .song-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .song-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .song-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .song-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: white;
        }
        
        .song-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .song-tags {
            margin-top: 10px;
        }
        
        .song-tag {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin: 2px;
        }
        
        .station-list {
            display: grid;
            gap: 15px;
        }
        
        .station-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 20px;
            text-decoration: none;
            color: white;
        }
        
        .station-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .station-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .station-info {
            flex: 1;
        }
        
        .station-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .station-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .tag-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .tag-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
            text-decoration: none;
            color: white;
        }
        
        .tag-card:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .tag-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .tag-info {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .no-results h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: white;
        }
        
        .recent-section {
            margin-top: 60px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        @media (max-width: 768px) {
            .search-title {
                font-size: 36px;
            }
            
            .search-form {
                flex-direction: column;
                border-radius: 15px;
            }
            
            .search-filters {
                width: 100%;
                justify-content: space-between;
            }
            
            .song-grid {
                grid-template-columns: 1fr;
            }
            
            .station-card {
                flex-direction: column;
                text-align: center;
            }
            
            .results-header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="<?= BASE_URL ?>/" class="logo">🎵 Stinkin' Park</a>
            <nav class="nav-links">
                <a href="<?= BASE_URL ?>/">Home</a>
                <a href="<?= BASE_URL ?>/search.php" class="active">Discover</a>
                <a href="<?= BASE_URL ?>/admin/upload.php">Admin</a>
            </nav>
        </div>
    </div>

    <div class="container">
        <div class="search-hero">
            <h1 class="search-title">Discover Music</h1>
            <p class="search-subtitle">Explore the complete Stinkin' Park collection</p>
            
            <form class="search-form" method="GET">
                <input type="text" 
                       name="q" 
                       class="search-input" 
                       placeholder="Search songs, stations, or tags..." 
                       value="<?= htmlspecialchars($query) ?>"
                       autofocus>
                
                <div class="search-filters">
                    <select name="category" class="filter-select">
                        <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="songs" <?= $category === 'songs' ? 'selected' : '' ?>>Songs</option>
                        <option value="stations" <?= $category === 'stations' ? 'selected' : '' ?>>Stations</option>
                        <option value="tags" <?= $category === 'tags' ? 'selected' : '' ?>>Tags</option>
                    </select>
                    
                    <select name="sort" class="filter-select">
                        <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Relevance</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Played</option>
                        <option value="duration" <?= $sort === 'duration' ? 'selected' : '' ?>>Duration</option>
                    </select>
                </div>
                
                <button type="submit" class="search-btn">Search</button>
            </form>
            
            <?php if (empty($query) && empty($tag)): ?>
            <div class="search-suggestions">
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 10px;">Popular tags:</p>
                <div class="suggestion-tags">
                    <?php foreach ($popularTags as $popularTag): ?>
                    <a href="?tag=<?= urlencode($popularTag['name']) ?>" class="tag-suggestion">
                        <?= htmlspecialchars($popularTag['name']) ?> (<?= $popularTag['usage_count'] ?>)
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($query) || !empty($tag)): ?>
        <div class="results-section">
            <div class="results-header">
                <div>
                    <h2 class="results-info">
                        Search Results
                        <?php if (!empty($query)): ?>
                        for "<?= htmlspecialchars($query) ?>"
                        <?php endif; ?>
                        <?php if (!empty($tag)): ?>
                        tagged with "<?= htmlspecialchars($tag) ?>"
                        <?php endif; ?>
                    </h2>
                    <p class="results-count">
                        <?= $results['total_songs'] ?> songs, 
                        <?= $results['total_stations'] ?> stations, 
                        <?= $results['total_tags'] ?> tags
                    </p>
                </div>
            </div>

            <div class="results-tabs">
                <button class="tab-btn active" data-tab="songs">
                    Songs (<?= count($results['songs']) ?>)
                </button>
                <button class="tab-btn" data-tab="stations">
                    Stations (<?= count($results['stations']) ?>)
                </button>
                <button class="tab-btn" data-tab="tags">
                    Tags (<?= count($results['tags']) ?>)
                </button>
            </div>

            <!-- Songs Results -->
            <div class="tab-content active" id="songs-tab">
                <?php if (!empty($results['songs'])): ?>
                <div class="song-grid">
                    <?php foreach ($results['songs'] as $song): ?>
                    <div class="song-card" onclick="playSong('<?= htmlspecialchars($song['filename']) ?>')">
                        <div class="song-title"><?= htmlspecialchars($song['title']) ?></div>
                        <div class="song-meta">
                            <span><?= $song['duration'] ? gmdate("i:s", $song['duration']) : '--:--' ?></span>
                            <span><?= $song['play_count'] ?> plays</span>
                        </div>
                        <?php if ($song['tags']): ?>
                        <div class="song-tags">
                            <?php foreach (explode(', ', $song['tags']) as $songTag): ?>
                            <span class="song-tag"><?= htmlspecialchars($songTag) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination for songs -->
                <?php if ($results['total_songs'] > $limit): ?>
                <div class="pagination">
                    <?php if ($offset > 0): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['offset' => max(0, $offset - $limit)])) ?>">
                        ← Previous
                    </a>
                    <?php endif; ?>
                    
                    <span class="current">
                        Page <?= floor($offset / $limit) + 1 ?> of <?= ceil($results['total_songs'] / $limit) ?>
                    </span>
                    
                    <?php if ($offset + $limit < $results['total_songs']): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['offset' => $offset + $limit])) ?>">
                        Next →
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="no-results">
                    <h3>No songs found</h3>
                    <p>Try different search terms or browse by tags</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stations Results -->
            <div class="tab-content" id="stations-tab">
                <?php if (!empty($results['stations'])): ?>
                <div class="station-list">
                    <?php foreach ($results['stations'] as $station): ?>
                    <a href="<?= BASE_URL ?>/stations/<?= htmlspecialchars($station['slug']) ?>" class="station-card">
                        <div class="station-icon">📻</div>
                        <div class="station-info">
                            <div class="station-name"><?= htmlspecialchars($station['name']) ?></div>
                            <?php if ($station['description']): ?>
                            <div class="station-description"><?= htmlspecialchars($station['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-results">
                    <h3>No stations found</h3>
                    <p>Try different search terms</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tags Results -->
            <div class="tab-content" id="tags-tab">
                <?php if (!empty($results['tags'])): ?>
                <div class="tag-list">
                    <?php foreach ($results['tags'] as $resultTag): ?>
                    <a href="?tag=<?= urlencode($resultTag['name']) ?>" class="tag-card">
                        <div class="tag-name"><?= htmlspecialchars($resultTag['name']) ?></div>
                        <div class="tag-info">
                            <?= ucfirst($resultTag['category']) ?> • <?= $resultTag['usage_count'] ?> songs
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-results">
                    <h3>No tags found</h3>
                    <p>Try different search terms</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Additions (shown when no search) -->
        <?php if (empty($query) && empty($tag) && !empty($recentSongs)): ?>
        <div class="recent-section">
            <h2 class="section-title">Recent Additions</h2>
            <div class="song-grid">
                <?php foreach ($recentSongs as $song): ?>
                <div class="song-card" onclick="playSong('<?= htmlspecialchars($song['filename']) ?>')">
                    <div class="song-title"><?= htmlspecialchars($song['title']) ?></div>
                    <div class="song-meta">
                        <span><?= $song['duration'] ? gmdate("i:s", $song['duration']) : '--:--' ?></span>
                        <span><?= date('M j', strtotime($song['created_at'])) ?></span>
                    </div>
                    <?php if ($song['tags']): ?>
                    <div class="song-tags">
                        <?php foreach (explode(', ', $song['tags']) as $songTag): ?>
                        <span class="song-tag"><?= htmlspecialchars($songTag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active from all tabs and contents
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active to clicked tab
                btn.classList.add('active');
                document.getElementById(btn.dataset.tab + '-tab').classList.add('active');
            });
        });

        // Song playing functionality
        function playSong(filename) {
            // Create or update audio player
            let audio = document.getElementById('global-audio-player');
            if (!audio) {
                audio = document.createElement('audio');
                audio.id = 'global-audio-player';
                audio.controls = true;
                audio.style.position = 'fixed';
                audio.style.bottom = '20px';
                audio.style.right = '20px';
                audio.style.zIndex = '1000';
                audio.style.background = 'rgba(0, 0, 0, 0.8)';
                audio.style.borderRadius = '10px';
                audio.style.padding = '10px';
                document.body.appendChild(audio);
            }
            
            audio.src = '<?= BASE_URL ?>/audio/' + encodeURIComponent(filename);
            audio.play().catch(e => {
                console.error('Playback failed:', e);
                alert('Unable to play this song. Please check if the file exists.');
            });
            
            // Show player
            audio.style.display = 'block';
        }

        // Search suggestions on input
        const searchInput = document.querySelector('.search-input');
        let suggestionTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(suggestionTimeout);
            
            if (this.value.length > 2) {
                suggestionTimeout = setTimeout(() => {
                    fetchSearchSuggestions(this.value);
                }, 300);
            }
        });

        function fetchSearchSuggestions(query) {
            // In a real implementation, this would call an API endpoint
            // for real-time search suggestions
            console.log('Fetching suggestions for:', query);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Focus search on Ctrl+K or Cmd+K
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Clear search on Escape
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        });

        // Auto-submit search after typing pause
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            if (this.value.length > 0) {
                searchTimeout = setTimeout(() => {
                    // Auto-submit search after 2 seconds of no typing
                    if (this.value.length > 2) {
                        this.form.submit();
                    }
                }, 2000);
            }
        });

        // Track search analytics
        if ('<?= $query ?>' || '<?= $tag ?>') {
            // Log search event for analytics
            console.log('Search performed:', {
                query: '<?= $query ?>',
                tag: '<?= $tag ?>',
                category: '<?= $category ?>',
                results: {
                    songs: <?= count($results['songs']) ?>,
                    stations: <?= count($results['stations']) ?>,
                    tags: <?= count($results['tags']) ?>
                }
            });
        }
    </script>
</body>
</html>