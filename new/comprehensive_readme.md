# 🎵 Stinkin' Park Music Platform v2.0

A comprehensive, database-driven music streaming platform built with PHP and MySQL, featuring station-based navigation, advanced tag management, bulk operations, and multimedia integration.

![Platform Version](https://img.shields.io/badge/version-2.0-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-green.svg)
![MySQL Version](https://img.shields.io/badge/mysql-%3E%3D8.0-orange.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)

## 🚀 What's New in v2.0

### Major Features Added
- **📦 Mass Upload System**: Bulk upload multiple audio files with common tagging
- **🏷️ Advanced Tag Management**: Visual CRUD interface for tags and categories  
- **📝 Bulk Song Editor**: Multi-select operations for efficient song management
- **📊 Analytics Dashboard**: Comprehensive system metrics and insights
- **🔍 Search & Discovery**: Advanced search with filtering and suggestions
- **🔧 Maintenance Tools**: Automated backup, optimization, and health monitoring
- **📈 Enhanced Logging**: Comprehensive system monitoring and error tracking
- **🎯 Metadata Extraction**: Automated metadata extraction from audio files

### Enhanced Core Features
- **Improved Admin Interface**: Centralized navigation with real-time statistics
- **RESTful APIs**: Complete API endpoints for songs, tags, and stations
- **Mobile Optimization**: Fully responsive design across all interfaces
- **Performance Improvements**: Optimized queries and caching strategies
- **Security Enhancements**: Advanced file validation and error handling

## 📋 Table of Contents

- [Features Overview](#features-overview)
- [Technical Architecture](#technical-architecture)
- [Installation Guide](#installation-guide)
- [User Guide](#user-guide)
- [Admin Guide](#admin-guide)
- [API Documentation](#api-documentation)
- [Development](#development)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## ✨ Features Overview

### 🎵 Music Management
- **Single & Mass Upload**: Upload individual songs or bulk upload multiple files
- **Intelligent Tagging**: 5-category tag system (Genre, Mood, Situational, Style, Intensity)
- **Metadata Extraction**: Automatic extraction of song metadata from audio files
- **Bulk Operations**: Edit multiple songs simultaneously with batch operations
- **File Validation**: Secure upload with comprehensive file type and size validation

### 📻 Station System
- **Dynamic Stations**: Create stations based on tag rules (include/exclude/require)
- **Smart Playlists**: Automatic playlist generation from database queries
- **Background Media**: Video and image backgrounds for enhanced visual experience
- **Auto-Play**: Seamless station entry with automatic music playback
- **Shuffle Algorithm**: Advanced randomization preventing immediate repeats

### 🔍 Discovery & Search
- **Advanced Search**: Full-text search across songs, stations, and tags
- **Smart Filtering**: Filter by category, tags, and multiple criteria
- **Popular Tags**: Trending tag suggestions based on usage statistics
- **Recent Additions**: Showcase newly uploaded content
- **Cross-References**: Easy navigation between related content

### 📊 Analytics & Monitoring
- **Play Tracking**: Monitor song popularity and listening patterns
- **Upload Statistics**: Track content growth and user activity
- **System Health**: Real-time monitoring of database and file system health
- **Performance Metrics**: Response times, error rates, and resource usage
- **Export Capabilities**: CSV export for external analysis

### 🔧 Maintenance & Admin Tools
- **Automated Backups**: Scheduled database and file system backups
- **Database Optimization**: Table optimization and index maintenance
- **File Integrity**: Check for orphaned and missing files
- **Log Management**: Comprehensive logging with rotation and cleanup
- **System Status**: Real-time health monitoring dashboard

## 🏗️ Technical Architecture

### Core Technologies
- **Backend**: PHP 8.x with modern OOP practices
- **Database**: MySQL 8.0 with optimized indexing
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Storage**: File-based audio storage with database metadata

### Database Schema
```sql
-- Core Tables
songs              # Master song registry with metadata
tags               # Hierarchical tag system with categories  
song_tags          # Many-to-many song-tag relationships
stations           # Station definitions and configurations
station_tags       # Station eligibility rules
system_logs        # Comprehensive application logging
```

### File Organization
```
stinkin-park-platform/
├── admin/                    # Administration interface
│   ├── upload.php           # Single file upload
│   ├── mass-upload.php      # Bulk upload system
│   ├── manage.php           # Song management
│   ├── bulk-edit.php        # Bulk editing interface
│   ├── tag-manager.php      # Tag management
│   ├── stations.php         # Station management
│   ├── analytics.php        # Analytics dashboard
│   ├── maintenance.php      # System maintenance
│   └── logs.php             # Log viewer
├── api/                     # RESTful API endpoints
│   ├── station.php          # Station data API
│   ├── songs.php            # Song operations API
│   ├── tags.php             # Tag management API
│   └── log.php              # Frontend logging
├── assets/                  # Frontend resources
│   ├── css/                 # Stylesheets
│   ├── js/                  # JavaScript modules
│   └── media/               # Background media
├── audio/                   # Flat audio file storage
├── stations/                # Station player pages
├── includes/                # PHP classes and utilities
├── config/                  # Configuration files
├── logs/                    # Application logs
├── backups/                 # Automated backups
└── setup/                   # Installation scripts
```

### Key Classes
- **Database**: Singleton database connection and query execution
- **Song**: Song CRUD operations and metadata management
- **Station**: Station management and playlist generation
- **FileUploader**: Secure file upload with validation
- **Logger**: Comprehensive logging system with multiple outputs
- **MetadataExtractor**: Audio file metadata extraction
- **MaintenanceSystem**: Automated maintenance and backup operations

## 🚀 Installation Guide

### Prerequisites
- **PHP 8.0+** with extensions: PDO, fileinfo, json, mbstring
- **MySQL 8.0+** or MariaDB 10.5+
- **Web Server**: Apache or Nginx
- **Storage**: 100GB+ available space
- **Memory**: 512MB+ PHP memory limit (1GB+ recommended)

### Quick Install
```bash
# 1. Clone the repository
git clone https://github.com/your-repo/stinkin-park-platform.git
cd stinkin-park-platform

# 2. Set up directories
mkdir -p audio assets/media logs backups
chmod 755 audio assets/media
chmod 766 logs backups

# 3. Create database
mysql -u root -p -e "
CREATE DATABASE stinkin_park_music CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stinkin_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON stinkin_park_music.* TO 'stinkin_user'@'localhost';
"

# 4. Import schema
mysql -u stinkin_user -p stinkin_park_music < setup/schema.sql

# 5. Configure application
cp config/database.php.example config/database.php
# Edit database.php with your credentials

# 6. Test installation
php -f test-db.php
```

### Detailed Installation
See [Deployment & Installation Guide](docs/deployment-guide.md) for comprehensive setup instructions.

## 📖 User Guide

### Finding Music
1. **Home Page**: Browse active stations from the main page
2. **Search Interface**: Use `/search.php` for advanced music discovery
3. **Tag Browsing**: Explore music by genre, mood, style, and intensity
4. **Station Navigation**: Each station provides curated playlists based on tag rules

### Playing Music
1. **Station Player**: Click any station to start listening
2. **Auto-Play**: Music starts automatically when entering a station  
3. **Player Controls**: Standard play/pause/skip/shuffle controls
4. **Background Media**: Enjoy visual backgrounds while listening
5. **Cross-Discovery**: Navigate between related songs and stations

### Search & Discovery
- **Quick Search**: Type in the search box for instant results
- **Advanced Filters**: Filter by category, tags, and sorting options
- **Popular Tags**: Click trending tags for curated content
- **Recent Additions**: Discover newly uploaded music

## 👥 Admin Guide

### Content Management

#### Single Upload
1. Navigate to `Admin Panel → Single Upload`
2. Select audio file (MP3/WAV, max 50MB)
3. Enter song title and select relevant tags
4. Choose activation status and upload

#### Mass Upload
1. Navigate to `Admin Panel → Mass Upload`
2. Drag & drop multiple audio files
3. Configure common tags for all files
4. Customize individual titles if needed
5. Review and execute bulk upload

#### Song Management
1. **View All Songs**: `Admin Panel → Manage Songs`
2. **Edit Individual Songs**: Click edit button on any song
3. **Bulk Operations**: `Admin Panel → Bulk Edit`
   - Select multiple songs
   - Apply batch changes (tags, status, titles)
   - Execute bulk operations

#### Tag Management
1. Navigate to `Admin Panel → Tag Manager`
2. **Add Tags**: Create new tags with categories
3. **Edit Tags**: Modify existing tag properties
4. **Merge Tags**: Combine duplicate or similar tags
5. **Usage Analytics**: View tag statistics and usage patterns

#### Station Management
1. Navigate to `Admin Panel → Stations`
2. **Create Stations**: Define name, description, and visual media
3. **Configure Rules**: Set tag-based eligibility (include/exclude/require)
4. **Test Stations**: Preview generated playlists
5. **Manage Active Status**: Control station visibility

### Analytics & Monitoring

#### Analytics Dashboard
- **System Health**: Monitor errors, warnings, and performance
- **Content Statistics**: Track songs, plays, and upload trends
- **Tag Analytics**: View most used tags and category breakdown
- **Station Performance**: Monitor station usage and song distribution

#### System Logs
- **Real-time Monitoring**: View system logs with filtering
- **Error Tracking**: Monitor and debug application issues
- **Performance Analysis**: Track API response times and query performance
- **Log Management**: Clean old logs and manage retention

#### Maintenance Tools
- **Database Backup**: Create compressed database backups
- **Audio Backup**: Archive all audio files
- **System Optimization**: Optimize database tables and indexes
- **File Integrity**: Check for orphaned and missing files
- **Health Monitoring**: Real-time system status dashboard

### Security Best Practices
- **File Validation**: All uploads are validated for type and size
- **Input Sanitization**: User input is properly cleaned and validated
- **Database Security**: All queries use prepared statements
- **Access Control**: Admin functions require proper authentication
- **Logging**: All actions are logged for audit trails

## 🔌 API Documentation

### Base URL
```
https://your-domain.com/music-platform/api/
```

### Authentication
Currently, the API uses simple session-based authentication. API keys and OAuth will be implemented in future versions.

### Endpoints

#### Songs API (`/api/songs.php`)

**Get Songs**
```http
GET /api/songs.php?action=list&limit=20&offset=0
```

**Search Songs**
```http
GET /api/songs.php?action=list&search=query&tag=rock&status=active
```

**Get Song Details**
```http
GET /api/songs.php?action=get&id=123
```

**Bulk Update Songs**
```http
POST /api/songs.php
Content-Type: application/json

{
  "action": "bulk_update",
  "song_ids": [1, 2, 3],
  "updates": {
    "active": 1,
    "add_tags": [4, 5],
    "title_prefix": "Remastered - "
  }
}
```

#### Tags API (`/api/tags.php`)

**Get All Tags**
```http
GET /api/tags.php?action=list&with_usage=true
```

**Get Tags by Category**
```http
GET /api/tags.php?action=list&category=genre
```

**Create Tag**
```http
POST /api/tags.php
Content-Type: application/json

{
  "action": "create",
  "name": "Heavy Metal",
  "category": "genre",
  "display_order": 10
}
```

#### Stations API (`/api/station.php`)

**Get Station Data**
```http
GET /api/station.php?slug=heavy-hitters
```

**Response Format**
```json
{
  "success": true,
  "station": {
    "id": 1,
    "name": "Heavy Hitters",
    "slug": "heavy-hitters",
    "description": "High-energy rock and metal",
    "background_video": "heavy-bg.mp4",
    "active": true
  },
  "songs": [
    {
      "id": 1,
      "title": "Thunder Strike",
      "filename": "thunder_strike_12345.mp3",
      "duration": 245,
      "play_count": 42
    }
  ],
  "metadata": {
    "total_songs": 15,
    "execution_time_ms": 23.45
  }
}
```

### Error Handling
```json
{
  "success": false,
  "error": "Song not found",
  "error_code": 404
}
```

### Rate Limiting
- Current: No rate limiting implemented
- Planned: 100 requests per minute per IP address
- Admin operations: 20 requests per minute

## 💻 Development

### Setting Up Development Environment
```bash
# Clone and setup
git clone https://github.com/your-repo/stinkin-park-platform.git
cd stinkin-park-platform

# Install development dependencies (if using Composer)
composer install --dev

# Copy environment configuration
cp config/database.php.example config/database.php
cp .env.example .env

# Set up local database
mysql -u root -p < setup/schema.sql

# Run development server
php -S localhost:8000
```

### Development Standards
- **PSR-12**: PHP coding standards compliance
- **Semantic HTML**: Accessibility-first markup
- **Mobile-First**: Responsive design approach
- **Progressive Enhancement**: Core functionality without JavaScript
- **Security**: Input validation and prepared statements

### Testing
```bash
# Run test suite
./test-suite-bash.sh all

# Test specific components
./test-suite-bash.sh db           # Database connectivity
./test-suite-bash.sh station slug # Specific station
./test-suite-bash.sh monitor      # Real-time monitoring
```

### Code Quality Tools
- **PHP_CodeSniffer**: Coding standard validation
- **PHPStan**: Static analysis (planned)
- **ESLint**: JavaScript linting (planned)
- **Lighthouse**: Performance auditing

### Contributing Guidelines
1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Follow** coding standards and document changes
4. **Test** thoroughly with the test suite
5. **Commit** with descriptive messages
6. **Submit** a pull request with detailed description

### Database Migrations
```bash
# Create migration
php scripts/create-migration.php "add_user_preferences_table"

# Run migrations
php scripts/migrate.php

# Rollback migration
php scripts/rollback.php
```

## 🚀 Deployment

### Production Checklist
- [ ] **Database**: Secure credentials and regular backups
- [ ] **Files**: Proper permissions and ownership
- [ ] **Security**: HTTPS, security headers, input validation
- [ ] **Performance**: Caching, compression, CDN integration
- [ ] **Monitoring**: Error logging, performance tracking
- [ ] **Backups**: Automated database and file backups

### Environment Configuration
```php
// Production settings in config/database.php
$isProduction = $_SERVER['SERVER_NAME'] === 'your-domain.com';

if ($isProduction) {
    error_reporting(E_ERROR | E_WARNING);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}
```

### Performance Optimization
- **Database**: Optimized indexes and query caching
- **Files**: Compressed audio and image assets
- **Caching**: Browser caching headers and static asset optimization
- **CDN**: Content delivery network for media files (planned)

### Monitoring & Maintenance
- **Health Checks**: Automated system health monitoring
- **Log Rotation**: Automatic log cleanup and archiving
- **Database Maintenance**: Regular optimization and backup
- **Security Updates**: Keep system dependencies updated

## 🔧 Troubleshooting

### Common Issues

#### Upload Failures
**Problem**: Files not uploading or failing validation
**Solutions**:
- Check PHP upload limits (`upload_max_filesize`, `post_max_size`)
- Verify directory permissions (755 for directories, 644 for files)
- Ensure audio directory is writable
- Check file type and size restrictions

#### Database Connection Errors
**Problem**: Unable to connect to database
**Solutions**:
- Verify credentials in `config/database.php`
- Check MySQL service status
- Confirm database and user exist
- Test connection with command line client

#### Performance Issues
**Problem**: Slow page loading or audio streaming
**Solutions**:
- Check database indexes with `EXPLAIN` queries
- Optimize audio file sizes and bitrates
- Enable gzip compression
- Monitor server resource usage

#### Permission Errors
**Problem**: File system permission denied
**Solutions**:
```bash
# Fix permissions
sudo chown -R www-data:www-data /path/to/platform
sudo chmod -R 755 /path/to/platform
sudo chmod -R 766 /path/to/platform/logs
sudo chmod -R 755 /path/to/platform/audio
```

### Debug Mode
```php
// Enable debug mode in config/database.php
define('DEBUG_MODE', true);

// This enables:
// - Detailed error messages
// - SQL query logging  
// - Performance metrics
// - Browser console logging
```

### Log Analysis
```bash
# View recent errors
tail -f logs/error.log

# Search for specific issues
grep "Upload failed" logs/app.log

# Monitor real-time activity
tail -f logs/app.log | grep "INFO"
```

### Performance Monitoring
- **Database**: Monitor slow queries and connection counts
- **Files**: Track storage usage and file operations
- **Memory**: Monitor PHP memory usage and limits
- **Network**: Check bandwidth usage for audio streaming

## 📈 Roadmap

### v2.1 (Next Release)
- [ ] **User Accounts**: Multi-user support with preferences
- [ ] **Playlists**: Custom user playlists and favorites
- [ ] **Mobile App**: React Native companion app
- [ ] **API Authentication**: JWT-based API security
- [ ] **Real-time Updates**: WebSocket integration for live updates

### v2.2 (Future)
- [ ] **Social Features**: Sharing and collaborative playlists
- [ ] **Advanced Analytics**: Machine learning insights
- [ ] **Multi-language**: Internationalization support
- [ ] **Cloud Storage**: Amazon S3/Google Cloud integration
- [ ] **Advanced Player**: Equalizer and audio effects

### v3.0 (Major Update)
- [ ] **Microservices Architecture**: Service-oriented design
- [ ] **GraphQL API**: Modern API with flexible queries
- [ ] **Progressive Web App**: Offline capabilities
- [ ] **AI Recommendations**: Personalized music discovery
- [ ] **Live Streaming**: Real-time audio streaming

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details on:
- Code of conduct
- Development process
- Submitting issues and pull requests
- Coding standards and best practices

## 📞 Support

- **Documentation**: Check the [docs/](docs/) directory
- **Issues**: Report bugs on [GitHub Issues](https://github.com/your-repo/stinkin-park-platform/issues)
- **Discussions**: Join [GitHub Discussions](https://github.com/your-repo/stinkin-park-platform/discussions)
- **Email**: Contact the development team at dev@stinkinpark.com

## 🙏 Acknowledgments

- **Audio Processing**: Thanks to the getID3 and FFmpeg communities
- **Frontend Design**: Inspired by modern music streaming platforms  
- **Database Design**: Following MySQL best practices and optimization guides
- **Security**: Based on OWASP security guidelines
- **Testing**: Comprehensive test suite for reliability

---

**Built with ❤️ for the Stinkin' Park music collection**

*Last updated: August 2025 | Version 2.0*