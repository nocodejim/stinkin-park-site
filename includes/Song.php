<?php
declare(strict_types=1);

namespace StinkinPark;

use Exception;
use PDO;

class Song
{
    private Database $db;
    
    public function __construct()
    {
        $this->db = new Database();
    }
    
    /**
     * Create a new song record
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO songs (title, filename, duration, file_size, active) 
                VALUES (:title, :filename, :duration, :file_size, :active)";
        
        Database::execute($sql, [
            ':title' => $data['title'],
            ':filename' => $data['filename'],
            ':duration' => $data['duration'] ?? null,
            ':file_size' => $data['file_size'] ?? null,
            ':active' => $data['active'] ?? 1
        ]);
        
        return Database::lastInsertId();
    }
    
    /**
     * Attach tags to a song
     */
    public function attachTags(int $songId, array $tagIds): void
    {
        if (empty($tagIds)) return;
        
        // Remove existing tags
        Database::execute("DELETE FROM song_tags WHERE song_id = ?", [$songId]);
        
        // Build bulk insert
        $values = [];
        $params = [];
        foreach ($tagIds as $index => $tagId) {
            $values[] = "(?, ?)";
            $params[] = $songId;
            $params[] = $tagId;
        }
        
        $sql = "INSERT INTO song_tags (song_id, tag_id) VALUES " . implode(', ', $values);
        Database::execute($sql, $params);
    }
    
    /**
     * Get all songs with their tags
     */
    public function getAllWithTags(int $limit = 100, int $offset = 0): array
    {
        $sql = "
            SELECT 
                s.*,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags,
                GROUP_CONCAT(DISTINCT CONCAT(t.id, ':', t.name, ':', t.category) SEPARATOR '|') as tag_details
            FROM songs s
            LEFT JOIN song_tags st ON s.id = st.song_id
            LEFT JOIN tags t ON st.tag_id = t.id
            GROUP BY s.id
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get song by ID with tags
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT 
                s.*,
                GROUP_CONCAT(t.id) as tag_ids
            FROM songs s
            LEFT JOIN song_tags st ON s.id = st.song_id
            LEFT JOIN tags t ON st.tag_id = t.id
            WHERE s.id = ?
            GROUP BY s.id
        ";
        
        $result = Database::execute($sql, [$id])->fetch();
        return $result ?: null;
    }
    
    /**
     * Update song details
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE songs SET title = :title, active = :active WHERE id = :id";
        
        Database::execute($sql, [
            ':id' => $id,
            ':title' => $data['title'],
            ':active' => $data['active'] ?? 1
        ]);
        
        return true;
    }
    
    /**
     * Delete a song
     */
    public function delete(int $id): bool
    {
        // Get filename first to delete the file
        $song = $this->getById($id);
        if (!$song) return false;
        
        // Delete database record (cascade will handle song_tags)
        Database::execute("DELETE FROM songs WHERE id = ?", [$id]);
        
        // Delete physical file
        $filePath = __DIR__ . '/../audio/' . $song['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Get total song count
     */
    public function getTotalCount(): int
    {
        $result = Database::execute("SELECT COUNT(*) as count FROM songs")->fetch();
        return (int) $result['count'];
    }
}
