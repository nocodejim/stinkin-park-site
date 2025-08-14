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

    /**
     * Get filtered songs with pagination
     */
    public function getFilteredSongs(string $search = '', string $tag = '', string $status = '', int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT 
                s.*,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') as tags,
                GROUP_CONCAT(DISTINCT t.id) as tag_ids
            FROM songs s
            LEFT JOIN song_tags st ON s.id = st.song_id
            LEFT JOIN tags t ON st.tag_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND s.title LIKE ?";
            $params[] = "%$search%";
        }
        
        if (!empty($tag)) {
            $sql .= " AND s.id IN (
                SELECT DISTINCT song_id 
                FROM song_tags st2 
                JOIN tags t2 ON st2.tag_id = t2.id 
                WHERE t2.name = ?
            )";
            $params[] = $tag;
        }
        
        if ($status === 'active') {
            $sql .= " AND s.active = 1";
        } elseif ($status === 'inactive') {
            $sql .= " AND s.active = 0";
        }
        
        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        return Database::execute($sql, $params)->fetchAll();
    }

    /**
     * Get filtered count for pagination
     */
    public function getFilteredCount(string $search = '', string $tag = '', string $status = ''): int
    {
        $sql = "SELECT COUNT(DISTINCT s.id) as total FROM songs s";
        $params = [];
        
        $conditions = [];
        
        if (!empty($search)) {
            $conditions[] = "s.title LIKE ?";
            $params[] = "%$search%";
        }
        
        if (!empty($tag)) {
            $sql .= " JOIN song_tags st ON s.id = st.song_id JOIN tags t ON st.tag_id = t.id";
            $conditions[] = "t.name = ?";
            $params[] = $tag;
        }
        
        if ($status === 'active') {
            $conditions[] = "s.active = 1";
        } elseif ($status === 'inactive') {
            $conditions[] = "s.active = 0";
        }
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        return (int) Database::execute($sql, $params)->fetch()['total'];
    }

    /**
     * Get song statistics
     */
    public function getStatistics(): array
    {
        $stats = [];
        
        // Basic counts
        $stats['total_songs'] = Database::execute("SELECT COUNT(*) as count FROM songs")->fetch()['count'];
        $stats['active_songs'] = Database::execute("SELECT COUNT(*) as count FROM songs WHERE active = 1")->fetch()['count'];
        $stats['total_plays'] = Database::execute("SELECT SUM(play_count) as total FROM songs")->fetch()['total'] ?? 0;
        
        // Duration statistics
        $durationStats = Database::execute("
            SELECT 
                AVG(duration) as avg_duration,
                MIN(duration) as min_duration,
                MAX(duration) as max_duration,
                SUM(duration) as total_duration
            FROM songs 
            WHERE duration IS NOT NULL
        ")->fetch();
        
        $stats['duration'] = $durationStats;
        
        // File size statistics
        $sizeStats = Database::execute("
            SELECT 
                AVG(file_size) as avg_size,
                MIN(file_size) as min_size,
                MAX(file_size) as max_size,
                SUM(file_size) as total_size
            FROM songs 
            WHERE file_size IS NOT NULL
        ")->fetch();
        
        $stats['file_size'] = $sizeStats;
        
        // Top tags
        $topTags = Database::execute("
            SELECT t.name, COUNT(st.song_id) as usage_count
            FROM tags t
            JOIN song_tags st ON t.id = st.tag_id
            GROUP BY t.id
            ORDER BY usage_count DESC
            LIMIT 10
        ")->fetchAll();
        
        $stats['top_tags'] = $topTags;
        
        // Recent uploads
        $recentUploads = Database::execute("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM songs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ")->fetchAll();
        
        $stats['recent_uploads'] = $recentUploads;
        
        return $stats;
    }

    /**
     * Bulk update songs
     */
    public function bulkUpdate(array $songIds, array $updates): array
    {
        Database::beginTransaction();
        
        try {
            $updateCount = 0;
            
            foreach ($songIds as $songId) {
                $songData = [];
                
                if (isset($updates['active'])) {
                    $songData['active'] = (int)$updates['active'];
                }
                
                if (isset($updates['title_prefix'])) {
                    $currentSong = $this->getById($songId);
                    $songData['title'] = $updates['title_prefix'] . $currentSong['title'];
                }
                
                if (isset($updates['title_suffix'])) {
                    $currentSong = $this->getById($songId);
                    $songData['title'] = $currentSong['title'] . $updates['title_suffix'];
                }
                
                if (!empty($songData)) {
                    $this->update($songId, $songData);
                    $updateCount++;
                }
                
                // Handle tag operations
                if (!empty($updates['add_tags'])) {
                    $currentSong = $this->getById($songId);
                    $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                    $newTags = array_unique(array_merge($currentTags, $updates['add_tags']));
                    $this->attachTags($songId, array_map('intval', $newTags));
                }
                
                if (!empty($updates['remove_tags'])) {
                    $currentSong = $this->getById($songId);
                    $currentTags = !empty($currentSong['tag_ids']) ? explode(',', $currentSong['tag_ids']) : [];
                    $newTags = array_diff($currentTags, $updates['remove_tags']);
                    $this->attachTags($songId, array_map('intval', $newTags));
                }
                
                if (!empty($updates['replace_tags'])) {
                    $this->attachTags($songId, array_map('intval', $updates['replace_tags']));
                }
            }
            
            Database::commit();
            
            return ['updated_count' => $updateCount];
            
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Bulk delete songs
     */
    public function bulkDelete(array $songIds): array
    {
        $deleteCount = 0;
        
        foreach ($songIds as $songId) {
            if ($this->delete($songId)) {
                $deleteCount++;
            }
        }
        
        return ['deleted_count' => $deleteCount];
    }
}
