<?php
// api/station.php
declare(strict_types=1);
header('Content-Type: application/json');

require_once '../includes/functions.php';

function send_json(int $status_code, array $data): void {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

$slug = $_GET['slug'] ?? null;

if (!$slug) {
    send_json(400, ['error' => 'Station slug is required']);
}

try {
    $pdo = get_db_connection();
    
    // 1. Get Station ID and verify it exists/is active
    $stmt = db_query($pdo, "SELECT id FROM stations WHERE slug = ? AND active = TRUE", [$slug]);
    $station = $stmt->fetch();
    
    if (!$station) {
        send_json(404, ['error' => 'Station not found or is inactive']);
    }
    
    // 2. Get Tag Rules
    $rules = get_station_tag_rules($pdo, $station['id']);
    
    $required_tags = [];
    $optional_tags = []; // "Include" rules
    $excluded_tags = [];

    foreach ($rules as $tag_id => $requirement_type) {
        switch ($requirement_type) {
            case 'required':
                $required_tags[] = $tag_id;
                break;
            case 'optional':
                $optional_tags[] = $tag_id;
                break;
            case 'excluded':
                $excluded_tags[] = $tag_id;
                break;
        }
    }

    // 3. Generate Playlist Query
    $sql = "SELECT s.id, s.title, s.artist, s.filename, s.duration_seconds 
            FROM songs s 
            WHERE s.active = TRUE";

    $params = [];

    // Handle Excluded Tags
    if (!empty($excluded_tags)) {
        $sql .= " AND s.id NOT IN (
            SELECT song_id FROM song_tags 
            WHERE tag_id IN (" . implode(',', array_fill(0, count($excluded_tags), '?')) . ")
        )";
        $params = array_merge($params, $excluded_tags);
    }

    // Handle Required Tags (Must have ALL)
    if (!empty($required_tags)) {
        foreach ($required_tags as $tag_id) {
            $sql .= " AND s.id IN (SELECT song_id FROM song_tags WHERE tag_id = ?)";
            $params[] = $tag_id;
        }
    }
    
    // Handle Optional/Include Tags (Must have ANY)
    // If Required tags exist, Optional tags are ignored for inclusion (but could be used for weighting later)
    if (empty($required_tags) && !empty($optional_tags)) {
        $sql .= " AND s.id IN (
            SELECT song_id FROM song_tags 
            WHERE tag_id IN (" . implode(',', array_fill(0, count($optional_tags), '?')) . ")
        )";
        $params = array_merge($params, $optional_tags);
    }
    
    // If no inclusion rules (required or optional) are defined, the station is empty.
    if (empty($required_tags) && empty($optional_tags)) {
        send_json(200, ['playlist' => []]);
    }

    // Execute the query
    $stmt = db_query($pdo, $sql, $params);
    $playlist = $stmt->fetchAll();

    // 4. Shuffle the playlist (Smart Randomization requirement)
    shuffle($playlist);

    // 5. Return the response
    send_json(200, [
        'playlist' => $playlist
    ]);

} catch (Exception $e) {
    error_log("Error in api/station.php: " . $e->getMessage());
    send_json(500, ['error' => 'Internal server error while generating playlist.']);
}
