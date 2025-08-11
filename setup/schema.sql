-- Stinkin' Park Music Platform Database Schema
-- Version 1.0 - MVP Foundation


-- Core song registry
CREATE TABLE songs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) UNIQUE NOT NULL,
    duration INT UNSIGNED DEFAULT NULL COMMENT 'Duration in seconds',
    file_size INT UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
    play_count INT UNSIGNED DEFAULT 0,
    active BOOLEAN DEFAULT 1,
    background_image VARCHAR(255) DEFAULT NULL,
    background_video VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (active),
    INDEX idx_play_count (play_count),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Tag categories for organization
CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('genre', 'mood', 'situational', 'style', 'intensity') NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_category (category),
    INDEX idx_slug (slug)
) ENGINE=InnoDB;

-- Many-to-many relationship
CREATE TABLE song_tags (
    song_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (song_id, tag_id),
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_tag_song (tag_id, song_id)
) ENGINE=InnoDB;

-- Station definitions
CREATE TABLE stations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    background_image VARCHAR(255) DEFAULT NULL,
    background_video VARCHAR(255) DEFAULT NULL,
    display_order INT DEFAULT 0,
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_active_order (active, display_order)
) ENGINE=InnoDB;

-- Station eligibility rules (which tags qualify for this station)
CREATE TABLE station_tags (
    station_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    rule_type ENUM('include', 'exclude', 'require') DEFAULT 'include',
    PRIMARY KEY (station_id, tag_id),
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    INDEX idx_tag_station (tag_id, station_id)
) ENGINE=InnoDB;

-- Play history for analytics (optional for MVP, but lightweight)
CREATE TABLE play_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    song_id INT UNSIGNED NOT NULL,
    station_id INT UNSIGNED DEFAULT NULL,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL,
    INDEX idx_song_played (song_id, played_at),
    INDEX idx_played_at (played_at)
) ENGINE=InnoDB;

-- Insert initial tags
INSERT INTO tags (name, category, slug, display_order) VALUES
-- Genre tags
('Hard Rock', 'genre', 'hard-rock', 1),
('Industrial', 'genre', 'industrial', 2),
('Metal', 'genre', 'metal', 3),
('Country', 'genre', 'country', 4),
('Country Metal', 'genre', 'country-metal', 5),
('Pop', 'genre', 'pop', 6),
('Unplugged', 'genre', 'unplugged', 7),
('Acoustic', 'genre', 'acoustic', 8),

-- Mood tags
('Highs', 'mood', 'highs', 10),
('Lows', 'mood', 'lows', 11),
('Chill', 'mood', 'chill', 12),
('Energetic', 'mood', 'energetic', 13),
('Mellow', 'mood', 'mellow', 14),
('Aggressive', 'mood', 'aggressive', 15),
('Dark', 'mood', 'dark', 16),
('Uplifting', 'mood', 'uplifting', 17),
('Melancholy', 'mood', 'melancholy', 18),
('Triumphant', 'mood', 'triumphant', 19),
('Nostalgic', 'mood', 'nostalgic', 20),
('Rebellious', 'mood', 'rebellious', 21),

-- Situational tags
('Late Night', 'situational', 'late-night', 30),
('Morning Coffee', 'situational', 'morning-coffee', 31),
('Workout', 'situational', 'workout', 32),
('Road Trip', 'situational', 'road-trip', 33),
('Focus', 'situational', 'focus', 34),
('Party', 'situational', 'party', 35),
('Background', 'situational', 'background', 36),
('Showcase', 'situational', 'showcase', 37),

-- Style tags
('Remixes', 'style', 'remixes', 40),
('Reimaginations', 'style', 'reimaginations', 41),
('Original', 'style', 'original', 42),
('Live', 'style', 'live', 43),
('Studio', 'style', 'studio', 44),
('Demo', 'style', 'demo', 45),
('A-Version', 'style', 'a-version', 46),
('B-Version', 'style', 'b-version', 47),

-- Intensity tags
('Soft', 'intensity', 'soft', 50),
('Medium', 'intensity', 'medium', 51),
('Hard', 'intensity', 'hard', 52),
('Brutal', 'intensity', 'brutal', 53);
