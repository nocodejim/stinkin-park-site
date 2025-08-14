# Mass Upload System Documentation

## Overview

The Mass Upload System allows administrators to upload multiple audio files simultaneously with bulk tagging and processing capabilities. This system dramatically improves efficiency when adding large numbers of songs to the Stinkin' Park platform.

## Features

### 🎯 Core Capabilities
- **Multiple File Selection**: Upload 1-50+ audio files in a single operation
- **Drag & Drop Interface**: Modern HTML5 file selection with visual feedback
- **Common Tag Application**: Apply tags to all uploaded files simultaneously
- **Individual Customization**: Override titles and add specific tags per file
- **Real-time Validation**: Immediate feedback on file compatibility
- **Progress Tracking**: Visual indication of upload status and completion
- **Error Handling**: Comprehensive error reporting and recovery options

### 🔒 Security Features
- **File Type Validation**: Only MP3 and WAV files accepted
- **MIME Type Verification**: Deep inspection beyond file extensions
- **File Size Limits**: Maximum 50MB per file with validation
- **Secure File Storage**: Unique filename generation prevents conflicts
- **Input Sanitization**: All user input properly validated and cleaned
- **Upload Directory Protection**: Files stored outside web-accessible areas

### 📱 User Experience
- **Mobile Responsive**: Full functionality on tablets and phones
- **Intuitive Interface**: Clear visual hierarchy and workflow
- **Keyboard Navigation**: Accessible via keyboard shortcuts
- **Progress Feedback**: Real-time upload status and completion rates
- **Error Recovery**: Clear instructions for resolving upload issues

## Technical Implementation

### File Upload Processing

```php
// Mass upload processing workflow
class MassUploadProcessor {
    public function processUpload(array $files, array $options): array {
        $results = [];
        $uploader = new FileUploader();
        $song = new Song();
        
        Database::beginTransaction();
        
        try {
            foreach ($files as $index => $file) {
                // Upload and validate file
                $uploadResult = $uploader->uploadAudio($file);
                
                // Create song record
                $songId = $song->create([
                    'title' => $this->determineTitle($file, $options, $index),
                    'filename' => $uploadResult['filename'],
                    'duration' => $uploadResult['duration'],
                    'file_size' => $uploadResult['file_size'],
                    'active' => $options['active'] ?? 1
                ]);
                
                // Apply tags
                $this->applyTags($songId, $options, $index);
                
                $results[] = ['success' => true, 'song_id' => $songId];
            }
            
            Database::commit();
            return $results;
            
        } catch (Exception $e) {
            Database::rollback();
            throw new MassUploadException("Bulk upload failed", 0, $e);
        }
    }
}
```

### Frontend JavaScript Implementation

```javascript
class MassUploadInterface {
    constructor() {
        this.selectedFiles = [];
        this.initializeDropZone();
        this.setupFileValidation();
        this.bindEventHandlers();
    }
    
    handleFiles(files) {
        // Filter valid audio files
        this.selectedFiles = Array.from(files).filter(file => {
            const validTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav'];
            const maxSize = 50 * 1024 * 1024; // 50MB
            
            return validTypes.includes(file.type) && file.size <= maxSize;
        });
        
        this.updatePreview();
        this.validateForm();
    }
    
    updatePreview() {
        // Dynamic preview generation with editable titles
        const preview = this.selectedFiles.map((file, index) => {
            return this.createFilePreviewElement(file, index);
        }).join('');
        
        document.getElementById('file-preview').innerHTML = preview;
    }
}
```

## User Interface Guide

### Accessing Mass Upload

1. **Navigation**: Admin Panel → Mass Upload
2. **URL**: `/admin/mass-upload.php`
3. **Permissions**: Admin access required

### Upload Workflow

#### Step 1: File Selection
- **Drag & Drop**: Drag audio files directly onto the upload zone
- **Browse**: Click the upload zone to open file browser
- **Multiple Selection**: Hold Ctrl/Cmd to select multiple files
- **Validation**: Invalid files are automatically filtered out

#### Step 2: File Preview & Customization
```html
<!-- File preview interface -->
<div class="file-item">
    <div class="file-info">
        <div class="file-name">original-filename.mp3</div>
        <div class="file-size">4.2 MB</div>
    </div>
    <div class="file-controls">
        <input type="text" name="custom_titles[]" value="Song Title" />
        <button class="remove-file">Remove</button>
    </div>
</div>
```

- **Title Editing**: Click on title field to customize song names
- **File Removal**: Remove individual files from upload queue
- **Size Validation**: Visual indication of file size compliance

#### Step 3: Common Tag Selection
- **Category-based Tags**: Tags organized by genre, mood, style, etc.
- **Bulk Selection**: "Select All" and "Clear All" options per category
- **Required Minimum**: At least one common tag must be selected
- **Visual Grouping**: Tags grouped by category for easy navigation

#### Step 4: Upload Options
```html
<!-- Upload options interface -->
<div class="options-section">
    <div class="option-group">
        <label>
            <input type="checkbox" name="use_filename_as_title" checked>
            Use filename as song title
        </label>
    </div>
    <div class="option-group">
        <label>
            <input type="checkbox" name="active" checked>
            Make all songs active immediately
        </label>
    </div>
</div>
```

#### Step 5: Upload Execution
- **Progress Indication**: Real-time feedback during upload
- **Error Handling**: Individual file error reporting
- **Success Confirmation**: Summary of successful/failed uploads
- **Post-Upload Actions**: Links to edit uploaded songs

### Upload Results Interface

```html
<!-- Results display -->
<div class="results">
    <h2>Upload Results</h2>
    
    <!-- Successful uploads -->
    <div class="result-item result-success">
        ✓ song1.mp3 → "Amazing Track" (ID: 123)
    </div>
    
    <!-- Failed uploads -->
    <div class="result-item result-error">
        ✗ invalid-file.txt → File type not supported
    </div>
</div>
```

## Configuration Options

### File Upload Limits

```php
// Configuration constants in FileUploader class
private const MAX_FILE_SIZE = 52428800; // 50MB in bytes
private const ALLOWED_AUDIO = [
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/wave' => 'wav'
];
```

### Upload Directory Structure

```
audio/
├── amazing_track_1634567890_abc123.mp3
├── rock_anthem_1634567891_def456.mp3
└── ballad_song_1634567892_ghi789.mp3
```

**Filename Format**: `{sanitized_title}_{timestamp}_{unique_id}.{extension}`

## Error Handling

### Common Upload Errors

| Error Type | Cause | Resolution |
|------------|-------|------------|
| `File too large` | File exceeds 50MB limit | Compress audio or use different format |
| `Invalid file type` | Non-audio file selected | Select only MP3 or WAV files |
| `Upload failed` | Server or permission error | Check server logs, retry upload |
| `Duplicate filename` | Filename conflict | System auto-generates unique names |
| `Database error` | Database connection issue | Check database connectivity |
| `No tags selected` | Missing required tags | Select at least one common tag |

### Error Recovery Process

1. **Automatic Retry**: System attempts failed uploads once more
2. **Individual Processing**: Failed files don't affect successful ones
3. **Detailed Reporting**: Specific error messages for each failure
4. **Partial Success**: Successful uploads are preserved even if some fail
5. **Manual Retry**: Users can retry failed uploads individually

## Performance Considerations

### Upload Optimization

- **File Streaming**: Large files processed in chunks to prevent memory issues
- **Concurrent Processing**: Multiple files processed simultaneously when possible
- **Database Transactions**: Atomic operations ensure data consistency
- **Memory Management**: Efficient handling of large file sets

### Recommended Limits

- **Maximum Files**: 50 files per upload session
- **Total Size**: 1GB total upload size recommended
- **Concurrent Users**: System supports multiple simultaneous uploads
- **Session Timeout**: 30-minute timeout for large uploads

## API Integration

### Mass Upload Endpoint

```php
// POST /admin/mass-upload.php
{
    "files": ["file1.mp3", "file2.mp3"],
    "common_tags": [1, 2, 3],
    "options": {
        "active": true,
        "use_filename_as_title": true
    },
    "custom_titles": ["Song One", "Song Two"]
}

// Response
{
    "success": true,
    "results": [
        {"success": true, "song_id": 123, "filename": "song1.mp3"},
        {"success": false, "error": "File too large", "filename": "song2.mp3"}
    ],
    "summary": {
        "total": 2,
        "successful": 1,
        "failed": 1
    }
}
```

## Logging and Monitoring

### Upload Tracking

```php
// Comprehensive logging for mass uploads
$logger->info("Mass upload initiated", [
    'file_count' => count($files),
    'user_id' => $userId,
    'session_id' => session_id()
], 'MASS_UPLOAD');

$logger->info("Song uploaded successfully", [
    'song_id' => $songId,
    'title' => $title,
    'filename' => $filename,
    'file_size' => $fileSize
], 'MASS_UPLOAD');

$logger->error("Song upload failed", [
    'filename' => $originalFilename,
    'error' => $e->getMessage(),
    'file_size' => $fileSize
], 'MASS_UPLOAD');
```

### Performance Metrics

- **Upload Success Rate**: Percentage of successful uploads
- **Average Upload Time**: Time per file and per batch
- **Error Frequency**: Common error types and frequencies
- **Resource Usage**: CPU and memory usage during uploads
- **User Behavior**: Most common batch sizes and patterns

## Troubleshooting Guide

### Upload Issues

**Problem**: Files not appearing in preview
- **Cause**: Invalid file types or JavaScript errors
- **Solution**: Check browser console, ensure files are MP3/WAV

**Problem**: Upload button disabled
- **Cause**: No files selected or no tags chosen
- **Solution**: Select files and at least one common tag

**Problem**: Partial upload failures
- **Cause**: Individual file issues or server limitations
- **Solution**: Check error messages, retry failed files individually

### Performance Issues

**Problem**: Slow upload speeds
- **Cause**: Large files, server limitations, or network issues
- **Solution**: Reduce file sizes, upload in smaller batches

**Problem**: Browser freezing
- **Cause**: Too many files or memory limitations
- **Solution**: Reduce batch size, refresh browser

### Server-Side Issues

**Problem**: Database connection errors
- **Cause**: Database server issues or connection limits
- **Solution**: Check database status, restart connections

**Problem**: File permission errors
- **Cause**: Incorrect directory permissions
- **Solution**: Verify audio directory permissions (755)

## Best Practices

### For Administrators

1. **Batch Size**: Upload 10-20 files at a time for optimal performance
2. **File Preparation**: Prepare and validate files before upload
3. **Tag Strategy**: Use consistent tagging strategies across uploads
4. **Quality Control**: Review upload results and fix any issues immediately
5. **Backup**: Maintain backups before large upload operations

### For Developers

1. **Error Handling**: Always implement comprehensive error handling
2. **Transaction Safety**: Use database transactions for data consistency
3. **Resource Management**: Monitor memory and CPU usage during uploads
4. **Security**: Validate all inputs and file types thoroughly
5. **Logging**: Log all operations for debugging and monitoring

## Integration with Existing Systems

### Song Management Integration

- Uploaded songs immediately appear in the song management interface
- All standard editing and tagging features available
- Station assignment works normally with uploaded songs

### Tag System Integration

- Common tags applied during upload
- Individual tag assignments preserved
- Tag statistics updated automatically
- Tag management interface reflects new usage

### Player Integration

- Active songs immediately available in stations
- Shuffle algorithms include new songs
- Background media assignments work normally
- API endpoints return new songs in playlists

---

*This documentation covers the complete Mass Upload System implementation. For technical support or feature requests, refer to the main project documentation or contact the development team.*