<?php
declare(strict_types=1);

namespace StinkinPark;

use Exception;

class FileUploader
{
    private const ALLOWED_AUDIO = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav'
    ];
    
    private const MAX_FILE_SIZE = 52428800; // 50MB
    private string $uploadPath;
    
    public function __construct(string $uploadPath = null)
    {
        $this->uploadPath = $uploadPath ?? dirname(__DIR__) . '/audio';
        
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    /**
     * Upload and validate audio file
     */
    public function uploadAudio(array $file): array
    {
        $this->validateFile($file);
        
        $mimeType = $this->getFileMimeType($file['tmp_name']);
        $extension = self::ALLOWED_AUDIO[$mimeType] ?? null;
        
        if (!$extension) {
            throw new Exception("Invalid audio format. Allowed: MP3, WAV");
        }
        
        // Generate safe filename
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $safeName = $this->sanitizeFilename($originalName);
        $timestamp = time();
        $uniqueId = uniqid();
        $newFilename = "{$safeName}_{$timestamp}_{$uniqueId}.{$extension}";
        
        $destination = $this->uploadPath . '/' . $newFilename;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception("Failed to move uploaded file");
        }
        
        // Get audio duration if possible
        $duration = $this->getAudioDuration($destination);
        
        return [
            'filename' => $newFilename,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'duration' => $duration,
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile(array $file): void
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE);
            throw new Exception("Upload failed: " . $errorMessage);
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception("File too large. Maximum size is 50MB");
        }
        
        if ($file['size'] == 0) {
            throw new Exception("File is empty");
        }
    }
    
    /**
     * Get MIME type securely
     */
    private function getFileMimeType(string $filepath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        return $mimeType ?: 'application/octet-stream';
    }
    
    /**
     * Sanitize filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove special characters, keep only alphanumeric, dash, underscore
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $safe = preg_replace('/_+/', '_', $safe); // Remove multiple underscores
        $safe = trim($safe, '_'); // Remove leading/trailing underscores
        
        // Limit length
        if (strlen($safe) > 50) {
            $safe = substr($safe, 0, 50);
        }
        
        return $safe ?: 'song';
    }
    
    /**
     * Try to get audio duration (requires ffmpeg or getID3)
     */
    private function getAudioDuration(string $filepath): ?int
    {
        // Simple method using ffmpeg if available
        if (function_exists('exec')) {
            $cmd = "ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg($filepath);
            $output = trim(shell_exec($cmd));
            
            if (is_numeric($output)) {
                return (int) round((float) $output);
            }
        }
        
        // Return null if we can't determine duration
        return null;
    }
    
    /**
     * Get human-readable upload error
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
}
