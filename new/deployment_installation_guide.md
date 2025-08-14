# Stinkin' Park Platform v2.0 - Deployment & Installation Guide

## Overview

This guide covers both fresh installations and upgrades from v1.x to v2.0 of the Stinkin' Park Music Platform. Version 2.0 introduces mass upload capabilities, advanced tag management, bulk editing, and enhanced monitoring systems.

## 🆕 What's New in v2.0

### Major Features Added
- **Mass Upload System**: Bulk upload multiple audio files with common tagging
- **Advanced Tag Management**: Visual CRUD interface for tags and categories
- **Bulk Song Editor**: Multi-select operations for song management
- **Enhanced Logging**: Comprehensive system monitoring and error tracking
- **Improved Admin Interface**: Centralized navigation and statistics dashboard
- **RESTful APIs**: Enhanced API endpoints for songs and tags

### Database Changes
- New `system_logs` table for comprehensive logging
- Enhanced tag usage tracking and statistics
- Improved indexing for performance optimization

## Prerequisites

### Server Requirements
- **PHP**: 8.0 or higher with extensions:
  - PDO with MySQL support
  - fileinfo
  - json
  - mbstring
  - curl (optional, for external integrations)
- **MySQL**: 8.0 or higher
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Storage**: 100GB+ available space
- **Memory**: 512MB+ PHP memory limit (1GB+ recommended for mass uploads)

### Development Tools (Optional)
- **Git**: For version control and updates
- **Composer**: For dependency management (future versions)
- **SSH Access**: For command-line operations

## Fresh Installation

### Step 1: Download and Extract Files

```bash
# Clone the repository or download the source code
git clone https://github.com/your-repo/stinkin-park-platform.git
cd stinkin-park-platform

# Or if downloading a ZIP file
unzip stinkin-park-v2.0.zip
cd stinkin-park-platform
```

### Step 2: Set Up Directory Structure

```bash
# Create necessary directories
mkdir -p audio
mkdir -p assets/media
mkdir -p logs
mkdir -p config

# Set proper permissions
chmod 755 audio assets/media
chmod 766 logs
chmod 644 config
```

### Step 3: Configure Database

#### Create Database and User

```sql
-- Connect to MySQL as root
CREATE DATABASE stinkin_park_music CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user (replace with secure password)
CREATE USER 'stinkin_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT ALL PRIVILEGES ON stinkin_park_music.* TO 'stinkin_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Import Database Schema

```bash
# Import the complete v2.0 schema
mysql -u stinkin_user -p stinkin_park_music < setup/schema.sql

# Or run the schema manually
mysql -u stinkin_user -p
```

```sql
USE stinkin_park_music;
source setup/schema.sql;
```

### Step 4: Configure Application

Create `config/database.php`:

```php
<?php
declare(strict_types=1);

// Environment detection
$isProduction = $_SERVER['SERVER_NAME'] === 'your-domain.com';

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'database' => 'stinkin_park_music',
    'username' => 'stinkin_user',
    'password' => 'your_secure_password_here'
];

// Application settings
require_once __DIR__ . '/app.php';

// Initialize database
require_once __DIR__ . '/../includes/Database.php';
\StinkinPark\Database::init($dbConfig);

// Error reporting
if (!$isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ERROR | E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

date_default_timezone_set('America/New_York');
```

Create `config/app.php`:

```php
<?php
declare(strict_types=1);

// Set your base URL path
define('BASE_URL', '/music-platform'); // Adjust this to your setup
```

### Step 5: Set Up Web Server

#### Apache Configuration

Create/update `.htaccess` in the project root:

```apache
RewriteEngine On

# Redirect to HTTPS (if using SSL)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Block access to sensitive files
<Files "*.md">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>

<FilesMatch "^(config|includes|setup)/">
    Require all denied
</FilesMatch>

# Set proper MIME types for audio
AddType audio/mpeg .mp3
AddType audio/wav .wav

# Enable gzip compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Cache static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType audio/mpeg "access plus 1 month"
    ExpiresByType audio/wav "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType video/mp4 "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>
```

#### Nginx Configuration (Alternative)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/stinkin-park-platform;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Block sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ \.(md|log)$ {
        deny all;
    }

    location ~* ^/(config|includes|setup)/ {
        deny all;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static asset caching
    location ~* \.(mp3|wav|mp4|jpg|jpeg|png|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Clean URLs for stations
    location /stations/ {
        try_files $uri $uri/ /stations/player.php;
    }
}
```

### Step 6: Test Installation

Visit your installation URL and run the test script:

```
http://your-domain.com/music-platform/test-db.php
```

You should see:
```
✓ Database connected successfully
✓ Found 8 tables
✓ Loaded 53 tags
Database ready for use!
```

### Step 7: Access Admin Interface

Navigate to the admin panel:

```
http://your-domain.com/music-platform/admin/upload.php
```

## Upgrading from v1.x to v2.0

### Pre-Upgrade Checklist

1. **Backup Everything**:
   ```bash
   # Backup database
   mysqldump -u stinkin_user -p stinkin_park_music > backup_v1_$(date +%Y%m%d).sql
   
   # Backup files
   tar -czf backup_files_v1_$(date +%Y%m%d).tar.gz audio/ assets/ config/
   ```

2. **Check Current Version**: Ensure you're running v1.x with the basic schema

3. **Test Environment**: Perform upgrade on staging environment first

### Step 1: Update Files

```bash
# Download v2.0 files (keep existing audio/ and config/)
git pull origin main
# Or manually replace files, preserving:
# - config/database.php
# - audio/ directory
# - assets/media/ directory (if you have custom backgrounds)
```

### Step 2: Database Migration

Run the v2.0 migration script:

```sql
-- Add new system_logs table
CREATE TABLE IF NOT EXISTS system_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    category VARCHAR(50) DEFAULT NULL,
    message TEXT NOT NULL,
    context JSON DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    request_uri VARCHAR(255) DEFAULT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_level (level),
    INDEX idx_category (category),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Add any missing indexes for performance
ALTER TABLE songs ADD INDEX idx_active (active);
ALTER TABLE songs ADD INDEX idx_play_count (play_count);
ALTER TABLE songs ADD INDEX idx_created (created_at);

ALTER TABLE tags ADD INDEX idx_category (category);
ALTER TABLE tags ADD INDEX idx_slug (slug);

ALTER TABLE song_tags ADD INDEX idx_tag_song (tag_id, song_id);
ALTER TABLE station_tags ADD INDEX idx_tag_station (tag_id, station_id);

-- Update any missing tag slugs
UPDATE tags SET slug = LOWER(REPLACE(name, ' ', '-')) WHERE slug IS NULL OR slug = '';
```

### Step 3: Update Configuration

Add new configuration options to `config/database.php`:

```php
// Add after existing configuration
require_once __DIR__ . '/../includes/Logger.php';

// Initialize logging system
$logger = \StinkinPark\Logger::getInstance();
$logger->info("Application started", ['version' => '2.0'], 'SYSTEM');
```

### Step 4: Test Upgrade

1. **Test Database Connection**:
   ```
   http://your-domain.com/music-platform/test-db.php
   ```

2. **Verify Existing Songs**: Check that all your existing songs are still accessible

3. **Test New Features**: Try the mass upload and tag management interfaces

4. **Check Logs**: Verify that the logging system is working

### Step 5: Update Permissions

```bash
# Ensure new directories have proper permissions
chmod 766 logs/
chmod 755 admin/
chmod 644 admin/*.php
```

## Configuration Options

### Upload Settings

Modify `includes/FileUploader.php` for custom upload limits:

```php
class FileUploader
{
    // Customize these values
    private const MAX_FILE_SIZE = 52428800; // 50MB
    private const ALLOWED_AUDIO = [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav'
    ];
    
    // Add additional formats if needed
    // 'audio/flac' => 'flac',
    // 'audio/ogg' => 'ogg'
}
```

### PHP Settings

Recommended `php.ini` settings for optimal performance:

```ini
# File uploads
upload_max_filesize = 50M
post_max_size = 500M
max_file_uploads = 50

# Memory and execution
memory_limit = 1G
max_execution_time = 300
max_input_time = 300

# Sessions
session.gc_maxlifetime = 7200

# Error reporting (production)
display_errors = Off
log_errors = On
error_log = /path/to/stinkin-park-platform/logs/php_errors.log
```

### Database Optimization

For better performance with large collections:

```sql
-- Add more indexes if you have many songs
ALTER TABLE songs ADD INDEX idx_title (title);
ALTER TABLE songs ADD INDEX idx_filename (filename);

-- Optimize tables periodically
OPTIMIZE TABLE songs, tags, song_tags, stations, station_tags, system_logs;

-- Configure MySQL for better performance
# Add to my.cnf:
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 128M
query_cache_type = 1
```

## Security Hardening

### File System Security

```bash
# Set restrictive permissions
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Secure sensitive directories
chmod 700 config/
chmod 600 config/*.php

# Make logs writable but not readable by web
chmod 766 logs/
```

### Database Security

```sql
-- Create read-only user for reporting (optional)
CREATE USER 'stinkin_readonly'@'localhost' IDENTIFIED BY 'readonly_password';
GRANT SELECT ON stinkin_park_music.* TO 'stinkin_readonly'@'localhost';

-- Remove unnecessary privileges
REVOKE FILE ON *.* FROM 'stinkin_user'@'localhost';
REVOKE PROCESS ON *.* FROM 'stinkin_user'@'localhost';
```

### Web Server Security

Add security headers:

```apache
# Add to .htaccess
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set X-Content-Type-Options "nosniff"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
```

## Monitoring and Maintenance

### Log Monitoring

Set up log rotation:

```bash
# Create /etc/logrotate.d/stinkin-park
/path/to/stinkin-park-platform/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    sharedscripts
    postrotate
        /bin/systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

### Database Maintenance

Create a maintenance script:

```bash
#!/bin/bash
# maintenance.sh

# Clean old logs (keep 30 days)
mysql -u stinkin_user -p stinkin_park_music -e "
DELETE FROM system_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"

# Optimize tables
mysql -u stinkin_user -p stinkin_park_music -e "
OPTIMIZE TABLE songs, tags, song_tags, stations, station_tags, system_logs"

# Update tag usage counts
mysql -u stinkin_user -p stinkin_park_music -e "
UPDATE tags SET usage_count = (
    SELECT COUNT(*) FROM song_tags WHERE tag_id = tags.id
)"

echo "Maintenance completed at $(date)"
```

### Backup Strategy

Automated backup script:

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backups/stinkin-park"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Database backup
mysqldump -u stinkin_user -p stinkin_park_music | \
    gzip > "$BACKUP_DIR/database_$DATE.sql.gz"

# File backup (audio and media)
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" \
    audio/ assets/media/ config/database.php

# Keep only last 30 days of backups
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

## Troubleshooting

### Common Issues

#### Upload Failures
```
Error: File too large
Solution: Increase upload_max_filesize and post_max_size in php.ini
```

#### Database Connection Errors
```
Error: Unable to connect to database
Solution: Check credentials in config/database.php and MySQL service status
```

#### Permission Errors
```
Error: Failed to write file to disk
Solution: Check directory permissions on audio/ and logs/ directories
```

#### Memory Errors
```
Error: Fatal error: Allowed memory size exhausted
Solution: Increase memory_limit in php.ini or reduce batch upload size
```

### Performance Issues

#### Slow Page Loading
1. Check database indexes: `EXPLAIN SELECT * FROM songs WHERE title LIKE '%search%'`
2. Optimize images and videos
3. Enable gzip compression
4. Use browser caching headers

#### Slow Audio Streaming
1. Check audio file bitrates (recommend 320kbps max)
2. Verify web server configuration for audio MIME types
3. Consider CDN for large audio files

### Debug Mode

Enable debug mode for troubleshooting:

```php
// Add to config/database.php
define('DEBUG_MODE', true);

// This will enable:
// - Detailed error messages
// - SQL query logging
// - Performance metrics
// - Browser console logging
```

## Support and Updates

### Getting Help

1. **Documentation**: Check project documentation and README files
2. **Logs**: Review system logs at `/admin/logs.php`
3. **Database**: Use test scripts to verify database connectivity
4. **Community**: Check project repository for issues and discussions

### Staying Updated

```bash
# Check for updates
git fetch origin
git log --oneline HEAD..origin/main

# Update to latest version
git pull origin main

# Run any new migrations
# Check CHANGELOG.md for migration instructions
```

### Health Monitoring

Use the built-in test suite:

```bash
# Run comprehensive tests
chmod +x test-suite-bash.sh
./test-suite-bash.sh all

# Monitor specific components
./test-suite-bash.sh db
./test-suite-bash.sh station test-station
```

---

*This deployment guide covers installation and upgrade procedures for Stinkin' Park Platform v2.0. For additional support, consult the project documentation or contact the development team.*