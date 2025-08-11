<?php
declare(strict_types=1);

namespace StinkinPark;

use PDO;

class Station
{
    /**
     * Create a new station
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO stations (name, slug, description, background_video, background_image, active) 
                VALUES (:name, :slug, :description, :background_video, :background_image, :active)";
        
        Database::execute($sql, [
            ':name' => $data['name'],
            ':slug' => $this->generateSlug($data['name']),
            ':description' => $data['description'] ?? null,
            ':background_video' => $data['background_video'] ?? null,
            ':background_image' => $data['background_image'] ?? null,
            ':active' => $data['active'] ?? 1
        ]);
        
        return Database::lastInsertId();
    }
    
    /**
     * Set which tags qualify songs for this station
     */
    public function setStationTags(int $stationId, array $tagRules): void
    {
        // Clear existing rules
        Database::execute("DELETE FROM station_tags WHERE station_id = ?", [$stationId]);
        
        if (empty($tagRules)) return;
        
        // Insert new rules
        $values = [];
        $params = [];
        
        foreach ($tagRules as $tagId => $ruleType) {
            if (empty($ruleType)) continue;
            
            $values[] = "(?, ?, ?)";
            $params[] = $stationId;
            $params[] = $tagId;
            $params[] = $ruleType; // 'include', 'exclude', or 'require'
        }
        
        if (!empty($values)) {
            $sql = "INSERT INTO station_tags (station_id, tag_id, rule_type) VALUES " . implode(', ', $values);
            Database::execute($sql, $params);
        }
    }
    
    /**
     * Get all stations
     */
    public function getAll(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM stations";
        if ($activeOnly) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY display_order, name";
        
        return Database::execute($sql)->fetchAll();
    }
    
    /**
     * Get station by slug with its rules
     */
    public function getBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM stations WHERE slug = ? AND active = 1";
        $station = Database::execute($sql, [$slug])->fetch();
        
        if (!$station) return null;
        
        // Get tag rules
        $sql = "
            SELECT t.*, st.rule_type
            FROM station_tags st
            JOIN tags t ON st.tag_id = t.id
            WHERE st.station_id = ?
        ";
        
        $station['tag_rules'] = Database::execute($sql, [$station['id']])->fetchAll();
        
        return $station;
    }
    
    /**
     * Get songs for a station based on its tag rules
     */
    public function getStationSongs(int $stationId): array
    {
        // Get station rules
        $sql = "SELECT tag_id, rule_type FROM station_tags WHERE station_id = ?";
        $rules = Database::execute($sql, [$stationId])->fetchAll();
        
        if (empty($rules)) {
            // No rules = all active songs
            $sql = "SELECT * FROM songs WHERE active = 1 ORDER BY RAND()";
            return Database::execute($sql)->fetchAll();
        }
        
        // Build dynamic query based on rules
        $requireTags = [];
        $includeTags = [];
        $excludeTags = [];
        
        foreach ($rules as $rule) {
            switch ($rule['rule_type']) {
                case 'require':
                    $requireTags[] = $rule['tag_id'];
                    break;
                case 'include':
                    $includeTags[] = $rule['tag_id'];
                    break;
                case 'exclude':
                    $excludeTags[] = $rule['tag_id'];
                    break;
            }
        }
        
        $sql = "
            SELECT DISTINCT s.*
            FROM songs s
            WHERE s.active = 1
        ";
        
        $params = [];
        
        // Handle REQUIRE tags (song must have ALL of these)
        if (!empty($requireTags)) {
            $sql .= " AND s.id IN (
                SELECT song_id 
                FROM song_tags 
                WHERE tag_id IN (" . str_repeat('?,', count($requireTags) - 1) . "?)
                GROUP BY song_id
                HAVING COUNT(DISTINCT tag_id) = ?
            )";
            $params = array_merge($params, $requireTags);
            $params[] = count($requireTags);
        }
        
        // Handle INCLUDE tags (song must have AT LEAST ONE of these)
        if (!empty($includeTags)) {
            $sql .= " AND s.id IN (
                SELECT song_id 
                FROM song_tags 
                WHERE tag_id IN (" . str_repeat('?,', count($includeTags) - 1) . "?)
            )";
            $params = array_merge($params, $includeTags);
        }
        
        // Handle EXCLUDE tags (song must NOT have ANY of these)
        if (!empty($excludeTags)) {
            $sql .= " AND s.id NOT IN (
                SELECT song_id 
                FROM song_tags 
                WHERE tag_id IN (" . str_repeat('?,', count($excludeTags) - 1) . "?)
            )";
            $params = array_merge($params, $excludeTags);
        }
        
        $sql .= " ORDER BY RAND()";
        
        return Database::execute($sql, $params)->fetchAll();
    }
    
    /**
     * Generate URL-safe slug
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
