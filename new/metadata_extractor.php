<?php
declare(strict_types=1);

namespace StinkinPark;

/**
 * Audio Metadata Extractor
 * Extracts metadata from audio files using multiple methods
 */
class MetadataExtractor
{
    private Logger $logger;
    private array $supportedFormats = ['mp3', 'wav', 'flac', 'ogg'];
    
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Extract comprehensive metadata from audio file
     */
    public function extractMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: $filePath");
        }
        
        $fileInfo = pathinfo($filePath);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        if (!in_array($extension, $this->supportedFormats)) {
            throw new \Exception("Unsupported file format: $extension");
        }
        
        $metadata = [
            'file_info' => $this->getBasicFileInfo($filePath),
            'audio_properties' => [],
            'tags' => [],
            'technical_info' => [],
            'suggested_tags' => []
        ];
        
        // Try different extraction methods
        try {
            // Method 1: getID3 library (if available)
            if (class_exists('getID3')) {
                $metadata = array_merge_recursive($metadata, $this->extractWithGetID3($filePath));
            }
            
            // Method 2: FFmpeg/FFprobe (if available)
            $ffprobeData = $this->extractWithFFprobe($filePath);
            if (!empty($ffprobeData)) {
                $metadata = array_merge_recursive($metadata, $ffprobeData);
            }
            
            // Method 3: Basic PHP analysis
            $basicData = $this->extractBasicMetadata($filePath);
            $metadata = array_merge_recursive($metadata, $basicData);
            
            // Generate suggested tags based on metadata
            $metadata['suggested_tags'] = $this->generateSuggestedTags($metadata);
            
            $this->logger->info("Metadata extracted successfully", [
                'file' => basename($filePath),
                'methods_used' => $this->getUsedMethods($metadata)
            ], 'METADATA');
            
        } catch (\Exception $e) {
            $this->logger->error("Metadata extraction failed", [
                'file' => basename($filePath),
                'error' => $e->getMessage()
            ], 'METADATA');
            
            // Return basic file info even if metadata extraction fails
            $metadata['extraction_error'] = $e->getMessage();
        }
        
        return $metadata;
    }
    
    /**
     * Get basic file information
     */
    private function getBasicFileInfo(string $filePath): array
    {
        $stat = stat($filePath);
        $fileInfo = pathinfo($filePath);
        
        return [
            'filename' => $fileInfo['basename'],
            'name' => $fileInfo['filename'],
            'extension' => strtolower($fileInfo['extension'] ?? ''),
            'size_bytes' => $stat['size'],
            'size_mb' => round($stat['size'] / 1048576, 2),
            'created_time' => $stat['ctime'],
            'modified_time' => $stat['mtime'],
            'mime_type' => $this->getMimeType($filePath)
        ];
    }
    
    /**
     * Extract metadata using getID3 library
     */
    private function extractWithGetID3(string $filePath): array
    {
        try {
            $getID3 = new \getID3();
            $fileInfo = $getID3->analyze($filePath);
            
            $metadata = [
                'audio_properties' => [
                    'duration' => $fileInfo['playtime_seconds'] ?? null,
                    'bitrate' => $fileInfo['audio']['bitrate'] ?? null,
                    'sample_rate' => $fileInfo['audio']['sample_rate'] ?? null,
                    'channels' => $fileInfo['audio']['channels'] ?? null,
                    'encoding' => $fileInfo['audio']['encoding'] ?? null,
                    'lossless' => $fileInfo['audio']['lossless'] ?? false
                ],
                'tags' => [
                    'title' => $fileInfo['tags']['id3v2']['title'][0] ?? 
                              $fileInfo['tags']['id3v1']['title'][0] ?? 
                              null,
                    'artist' => $fileInfo['tags']['id3v2']['artist'][0] ?? 
                               $fileInfo['tags']['id3v1']['artist'][0] ?? 
                               null,
                    'album' => $fileInfo['tags']['id3v2']['album'][0] ?? 
                              $fileInfo['tags']['id3v1']['album'][0] ?? 
                              null,
                    'year' => $fileInfo['tags']['id3v2']['year'][0] ?? 
                             $fileInfo['tags']['id3v1']['year'][0] ?? 
                             null,
                    'genre' => $fileInfo['tags']['id3v2']['genre'][0] ?? 
                              $fileInfo['tags']['id3v1']['genre'][0] ?? 
                              null,
                    'track_number' => $fileInfo['tags']['id3v2']['track_number'][0] ?? null,
                    'comment' => $fileInfo['tags']['id3v2']['comment'][0] ?? 
                                $fileInfo['tags']['id3v1']['comment'][0] ?? 
                                null
                ],
                'technical_info' => [
                    'format' => $fileInfo['fileformat'] ?? null,
                    'encoder' => $fileInfo['audio']['encoder'] ?? null,
                    'encoding_settings' => $fileInfo['audio']['encoding_settings'] ?? null
                ]
            ];
            
            // Clean up null values
            $metadata = $this->removeNullValues($metadata);
            
            return $metadata;
            
        } catch (\Exception $e) {
            $this->logger->warning("getID3 extraction failed", ['error' => $e->getMessage()], 'METADATA');
            return [];
        }
    }
    
    /**
     * Extract metadata using FFprobe
     */
    private function extractWithFFprobe(string $filePath): array
    {
        if (!$this->isFFprobeAvailable()) {
            return [];
        }
        
        try {
            $escapedPath = escapeshellarg($filePath);
            
            // Get format information
            $formatCmd = "ffprobe -v quiet -print_format json -show_format $escapedPath 2>/dev/null";
            $formatOutput = shell_exec($formatCmd);
            
            // Get stream information
            $streamCmd = "ffprobe -v quiet -print_format json -show_streams $escapedPath 2>/dev/null";
            $streamOutput = shell_exec($streamCmd);
            
            $formatData = json_decode($formatOutput, true);
            $streamData = json_decode($streamOutput, true);
            
            if (!$formatData || !$streamData) {
                return [];
            }
            
            $audioStream = null;
            foreach ($streamData['streams'] as $stream) {
                if ($stream['codec_type'] === 'audio') {
                    $audioStream = $stream;
                    break;
                }
            }
            
            $metadata = [
                'audio_properties' => [
                    'duration' => isset($formatData['format']['duration']) ? 
                                 (float)$formatData['format']['duration'] : null,
                    'bitrate' => isset($formatData['format']['bit_rate']) ? 
                                (int)$formatData['format']['bit_rate'] : null,
                    'sample_rate' => $audioStream['sample_rate'] ?? null,
                    'channels' => $audioStream['channels'] ?? null,
                    'codec' => $audioStream['codec_name'] ?? null,
                    'codec_long_name' => $audioStream['codec_long_name'] ?? null
                ],
                'tags' => $formatData['format']['tags'] ?? [],
                'technical_info' => [
                    'format_name' => $formatData['format']['format_name'] ?? null,
                    'format_long_name' => $formatData['format']['format_long_name'] ?? null
                ]
            ];
            
            // Normalize tag names (FFprobe uses different casing)
            if (!empty($metadata['tags'])) {
                $normalizedTags = [];
                foreach ($metadata['tags'] as $key => $value) {
                    $normalizedKey = strtolower($key);
                    $normalizedTags[$normalizedKey] = $value;
                }
                $metadata['tags'] = $normalizedTags;
            }
            
            return $this->removeNullValues($metadata);
            
        } catch (\Exception $e) {
            $this->logger->warning("FFprobe extraction failed", ['error' => $e->getMessage()], 'METADATA');
            return [];
        }
    }
    
    /**
     * Basic metadata extraction using PHP functions
     */
    private function extractBasicMetadata(string $filePath): array
    {
        $metadata = [];
        
        // Try to get duration using getimagesize (works for some audio formats)
        try {
            if (function_exists('getimagesize')) {
                $info = getimagesize($filePath);
                if ($info && isset($info['channels'])) {
                    $metadata['audio_properties']['channels'] = $info['channels'];
                }
            }
        } catch (\Exception $e) {
            // Ignore errors from getimagesize
        }
        
        // Estimate bitrate from file size and duration if we have duration
        if (isset($metadata['audio_properties']['duration']) && 
            $metadata['audio_properties']['duration'] > 0) {
            
            $fileSize = filesize($filePath);
            $estimatedBitrate = ($fileSize * 8) / $metadata['audio_properties']['duration'];
            $metadata['audio_properties']['estimated_bitrate'] = round($estimatedBitrate);
        }
        
        // Extract information from filename
        $filename = pathinfo($filePath, PATHINFO_FILENAME);
        $filenameInfo = $this->parseFilename($filename);
        
        if (!empty($filenameInfo)) {
            $metadata['filename_parsed'] = $filenameInfo;
        }
        
        return $metadata;
    }
    
    /**
     * Parse filename to extract potential metadata
     */
    private function parseFilename(string $filename): array
    {
        $info = [];
        
        // Common patterns for song filenames
        $patterns = [
            // "Artist - Title"
            '/^(.+?)\s*-\s*(.+)$/' => ['artist', 'title'],
            // "01. Title" or "01 Title"
            '/^(\d+)[\.\s]+(.+)$/' => ['track_number', 'title'],
            // "Title (Year)" or "Title [Year]"
            '/^(.+?)\s*[\(\[](\d{4})[\)\]]/' => ['title', 'year'],
            // "Title (feat. Artist)" or "Title ft. Artist"
            '/^(.+?)\s*\((?:feat\.|ft\.|featuring)\s*(.+?)\)/' => ['title', 'featured_artist'],
            // "Title - Remix" or "Title (Remix)"
            '/^(.+?)\s*[\-\(]\s*(remix|mix|version|edit)/' => ['title', 'version_type']
        ];
        
        foreach ($patterns as $pattern => $fields) {
            if (preg_match($pattern, $filename, $matches)) {
                for ($i = 1; $i < count($matches); $i++) {
                    if (isset($fields[$i - 1])) {
                        $info[$fields[$i - 1]] = trim($matches[$i]);
                    }
                }
                break; // Use first matching pattern
            }
        }
        
        // If no pattern matched, use the whole filename as title
        if (empty($info)) {
            $info['title'] = $filename;
        }
        
        return $info;
    }
    
    /**
     * Generate suggested tags based on extracted metadata
     */
    private function generateSuggestedTags(array $metadata): array
    {
        $suggestions = [];
        
        // Suggest tags based on genre
        if (!empty($metadata['tags']['genre'])) {
            $genre = strtolower($metadata['tags']['genre']);
            $genreMap = [
                'rock' => ['Hard Rock', 'Energetic'],
                'metal' => ['Metal', 'Aggressive', 'Hard'],
                'pop' => ['Pop', 'Uplifting'],
                'acoustic' => ['Acoustic', 'Unplugged', 'Mellow'],
                'electronic' => ['Industrial', 'Energetic'],
                'country' => ['Country', 'Nostalgic'],
                'blues' => ['Mellow', 'Soulful'],
                'jazz' => ['Chill', 'Background'],
                'classical' => ['Background', 'Elegant']
            ];
            
            foreach ($genreMap as $key => $tags) {
                if (strpos($genre, $key) !== false) {
                    $suggestions = array_merge($suggestions, $tags);
                }
            }
        }
        
        // Suggest tags based on tempo/energy (estimated from bitrate and other factors)
        $bitrate = $metadata['audio_properties']['bitrate'] ?? 0;
        $duration = $metadata['audio_properties']['duration'] ?? 0;
        
        if ($bitrate > 256000) {
            $suggestions[] = 'High Quality';
        }
        
        if ($duration > 0) {
            if ($duration < 180) { // Less than 3 minutes
                $suggestions[] = 'Short';
            } elseif ($duration > 360) { // More than 6 minutes
                $suggestions[] = 'Extended';
            }
        }
        
        // Suggest tags based on filename analysis
        if (!empty($metadata['filename_parsed'])) {
            $filename = strtolower(implode(' ', $metadata['filename_parsed']));
            
            $keywordMap = [
                'remix' => ['Remixes', 'B-Version'],
                'acoustic' => ['Acoustic', 'Unplugged'],
                'live' => ['Live'],
                'demo' => ['Demo'],
                'instrumental' => ['Background'],
                'cover' => ['Reimaginations'],
                'unplugged' => ['Unplugged', 'Acoustic'],
                'radio' => ['Radio Edit'],
                'extended' => ['Extended'],
                'mix' => ['Remixes']
            ];
            
            foreach ($keywordMap as $keyword => $tags) {
                if (strpos($filename, $keyword) !== false) {
                    $suggestions = array_merge($suggestions, $tags);
                }
            }
        }
        
        // Remove duplicates and return
        return array_unique($suggestions);
    }
    
    /**
     * Check if FFprobe is available
     */
    private function isFFprobeAvailable(): bool
    {
        $output = shell_exec('ffprobe -version 2>/dev/null');
        return !empty($output) && strpos($output, 'ffprobe') !== false;
    }
    
    /**
     * Get MIME type of file
     */
    private function getMimeType(string $filePath): string
    {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType ?: 'application/octet-stream';
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Remove null values from nested array
     */
    private function removeNullValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeNullValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif (is_null($value) || $value === '') {
                unset($array[$key]);
            }
        }
        
        return $array;
    }
    
    /**
     * Get list of methods used for extraction
     */
    private function getUsedMethods(array $metadata): array
    {
        $methods = [];
        
        if (!empty($metadata['audio_properties']['encoding'])) {
            $methods[] = 'getID3';
        }
        
        if (!empty($metadata['audio_properties']['codec'])) {
            $methods[] = 'ffprobe';
        }
        
        if (!empty($metadata['filename_parsed'])) {
            $methods[] = 'filename_parsing';
        }
        
        return $methods;
    }
    
    /**
     * Batch process multiple files
     */
    public function batchExtractMetadata(array $filePaths, callable $progressCallback = null): array
    {
        $results = [];
        $total = count($filePaths);
        
        foreach ($filePaths as $index => $filePath) {
            try {
                $results[basename($filePath)] = $this->extractMetadata($filePath);
                
                if ($progressCallback) {
                    $progressCallback($index + 1, $total, basename($filePath));
                }
                
            } catch (\Exception $e) {
                $results[basename($filePath)] = [
                    'error' => $e->getMessage(),
                    'file_info' => $this->getBasicFileInfo($filePath)
                ];
                
                $this->logger->error("Batch metadata extraction failed for file", [
                    'file' => basename($filePath),
                    'error' => $e->getMessage()
                ], 'METADATA');
            }
        }
        
        return $results;
    }
    
    /**
     * Get metadata summary for a collection of files
     */
    public function getCollectionSummary(array $metadataResults): array
    {
        $summary = [
            'total_files' => count($metadataResults),
            'successful_extractions' => 0,
            'failed_extractions' => 0,
            'total_duration' => 0,
            'total_size_mb' => 0,
            'formats' => [],
            'genres' => [],
            'average_bitrate' => 0,
            'quality_distribution' => [
                'high' => 0,    // >256kbps
                'medium' => 0,  // 128-256kbps
                'low' => 0      // <128kbps
            ]
        ];
        
        $bitrates = [];
        
        foreach ($metadataResults as $filename => $metadata) {
            if (isset($metadata['error'])) {
                $summary['failed_extractions']++;
                continue;
            }
            
            $summary['successful_extractions']++;
            
            // Duration
            if (!empty($metadata['audio_properties']['duration'])) {
                $summary['total_duration'] += $metadata['audio_properties']['duration'];
            }
            
            // File size
            if (!empty($metadata['file_info']['size_mb'])) {
                $summary['total_size_mb'] += $metadata['file_info']['size_mb'];
            }
            
            // Format
            if (!empty($metadata['file_info']['extension'])) {
                $format = $metadata['file_info']['extension'];
                $summary['formats'][$format] = ($summary['formats'][$format] ?? 0) + 1;
            }
            
            // Genre
            if (!empty($metadata['tags']['genre'])) {
                $genre = $metadata['tags']['genre'];
                $summary['genres'][$genre] = ($summary['genres'][$genre] ?? 0) + 1;
            }
            
            // Bitrate analysis
            if (!empty($metadata['audio_properties']['bitrate'])) {
                $bitrate = $metadata['audio_properties']['bitrate'];
                $bitrates[] = $bitrate;
                
                if ($bitrate > 256000) {
                    $summary['quality_distribution']['high']++;
                } elseif ($bitrate >= 128000) {
                    $summary['quality_distribution']['medium']++;
                } else {
                    $summary['quality_distribution']['low']++;
                }
            }
        }
        
        // Calculate average bitrate
        if (!empty($bitrates)) {
            $summary['average_bitrate'] = round(array_sum($bitrates) / count($bitrates));
        }
        
        // Convert total duration to human readable
        $summary['total_duration_formatted'] = gmdate("H:i:s", $summary['total_duration']);
        
        return $summary;
    }
}

/**
 * Utility functions for metadata operations
 */
class MetadataUtils
{
    /**
     * Suggest song title from metadata
     */
    public static function suggestTitle(array $metadata): string
    {
        // Priority order for title suggestion
        $titleSources = [
            $metadata['tags']['title'] ?? null,
            $metadata['filename_parsed']['title'] ?? null,
            $metadata['file_info']['name'] ?? null
        ];
        
        foreach ($titleSources as $title) {
            if (!empty($title)) {
                return self::cleanTitle($title);
            }
        }
        
        return 'Untitled Song';
    }
    
    /**
     * Clean and normalize song title
     */
    public static function cleanTitle(string $title): string
    {
        // Remove common file naming artifacts
        $title = preg_replace('/\s*\([^)]*\)\s*$/', '', $title); // Remove trailing parentheses
        $title = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $title); // Remove trailing brackets
        $title = preg_replace('/\s*-\s*(remix|mix|edit|version)\s*$/i', '', $title); // Remove version info
        $title = trim($title);
        
        // Capitalize properly
        $title = ucwords(strtolower($title));
        
        // Handle common abbreviations
        $title = str_replace([' Feat ', ' Ft '], [' feat. ', ' ft. '], $title);
        
        return $title;
    }
    
    /**
     * Map extracted genre to system tags
     */
    public static function mapGenreToTags(string $genre): array
    {
        $genreMappings = [
            'rock' => ['Hard Rock'],
            'hard rock' => ['Hard Rock'],
            'metal' => ['Metal'],
            'heavy metal' => ['Metal', 'Hard'],
            'pop' => ['Pop'],
            'country' => ['Country'],
            'acoustic' => ['Acoustic', 'Unplugged'],
            'electronic' => ['Industrial'],
            'industrial' => ['Industrial'],
            'alternative' => ['Hard Rock'],
            'indie' => ['Alternative'],
            'folk' => ['Acoustic', 'Mellow'],
            'blues' => ['Mellow'],
            'jazz' => ['Chill', 'Background']
        ];
        
        $genre = strtolower(trim($genre));
        return $genreMappings[$genre] ?? [];
    }
    
    /**
     * Determine intensity level from metadata
     */
    public static function determineIntensity(array $metadata): string
    {
        $bitrate = $metadata['audio_properties']['bitrate'] ?? 0;
        $genre = strtolower($metadata['tags']['genre'] ?? '');
        
        // Genre-based intensity
        $highIntensityGenres = ['metal', 'hard rock', 'punk', 'industrial'];
        $lowIntensityGenres = ['acoustic', 'folk', 'ambient', 'classical'];
        
        foreach ($highIntensityGenres as $highGenre) {
            if (strpos($genre, $highGenre) !== false) {
                return 'Hard';
            }
        }
        
        foreach ($lowIntensityGenres as $lowGenre) {
            if (strpos($genre, $lowGenre) !== false) {
                return 'Soft';
            }
        }
        
        // Bitrate-based fallback
        if ($bitrate > 320000) {
            return 'Hard';
        } elseif ($bitrate < 128000) {
            return 'Soft';
        }
        
        return 'Medium';
    }
}
?>