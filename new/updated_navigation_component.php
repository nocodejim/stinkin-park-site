<?php
declare(strict_types=1);

namespace StinkinPark;

/**
 * Centralized admin navigation component
 * Provides consistent navigation across all admin pages
 */
class AdminNav
{
    private string $currentPage;
    private array $stats;
    
    public function __construct(string $currentPage = '')
    {
        $this->currentPage = $currentPage;
        $this->loadStats();
    }
    
    /**
     * Load navigation statistics
     */
    private function loadStats(): void
    {
        try {
            // Get basic counts for navigation badges
            $this->stats = [
                'total_songs' => Database::execute("SELECT COUNT(*) as count FROM songs")->fetch()['count'],
                'active_songs' => Database::execute("SELECT COUNT(*) as count FROM songs WHERE active = 1")->fetch()['count'],
                'total_tags' => Database::execute("SELECT COUNT(*) as count FROM tags")->fetch()['count'],
                'total_stations' => Database::execute("SELECT COUNT(*) as count FROM stations WHERE active = 1")->fetch()['count'],
                'recent_uploads' => Database::execute("SELECT COUNT(*) as count FROM songs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['count'],
                'log_errors' => Database::execute("SELECT COUNT(*) as count FROM system_logs WHERE level = 'ERROR' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch()['count'] ?? 0
            ];
        } catch (Exception $e) {
            // Fallback to zeros if database unavailable
            $this->stats = [
                'total_songs' => 0,
                'active_songs' => 0,
                'total_tags' => 0,
                'total_stations' => 0,
                'recent_uploads' => 0,
                'log_errors' => 0
            ];
        }
    }
    
    /**
     * Render the complete admin navigation
     */
    public function render(): string
    {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        
        return '
        <div class="admin-nav-container">
            ' . $this->renderHeader($baseUrl) . '
            ' . $this->renderMainNav($baseUrl) . '
            ' . $this->renderQuickStats() . '
        </div>
        
        <style>
            .admin-nav-container {
                background: rgba(255, 255, 255, 0.95);
                border-radius: 10px;
                margin-bottom: 20px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            
            .admin-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .admin-title {
                font-size: 24px;
                font-weight: 700;
                margin: 0;
            }
            
            .admin-title .brand {
                color: #fff;
                text-decoration: none;
            }
            
            .admin-actions {
                display: flex;
                gap: 10px;
            }
            
            .admin-btn {
                background: rgba(255, 255, 255, 0.2);
                color: white;
                border: 1px solid rgba(255, 255, 255, 0.3);
                padding: 8px 16px;
                border-radius: 20px;
                text-decoration: none;
                font-size: 14px;
                transition: all 0.3s;
            }
            
            .admin-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: translateY(-1px);
            }
            
            .main-nav {
                padding: 20px;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .nav-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .nav-item {
                display: flex;
                align-items: center;
                padding: 15px;
                border-radius: 8px;
                text-decoration: none;
                color: #333;
                background: #f8f9fa;
                transition: all 0.3s;
                position: relative;
            }
            
            .nav-item:hover {
                background: #e9ecef;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            
            .nav-item.active {
                background: #667eea;
                color: white;
            }
            
            .nav-item.active:hover {
                background: #5a67d8;
            }
            
            .nav-icon {
                font-size: 24px;
                margin-right: 12px;
                width: 32px;
                text-align: center;
            }
            
            .nav-content {
                flex: 1;
            }
            
            .nav-title {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 4px;
            }
            
            .nav-description {
                font-size: 12px;
                opacity: 0.7;
                line-height: 1.3;
            }
            
            .nav-badge {
                position: absolute;
                top: 8px;
                right: 8px;
                background: #dc3545;
                color: white;
                border-radius: 10px;
                padding: 2px 8px;
                font-size: 11px;
                font-weight: 600;
                min-width: 20px;
                text-align: center;
            }
            
            .nav-badge.success {
                background: #28a745;
            }
            
            .nav-badge.warning {
                background: #ffc107;
                color: #212529;
            }
            
            .nav-badge.info {
                background: #17a2b8;
            }
            
            .quick-stats {
                padding: 15px 20px;
                background: #f8f9fa;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .stat-item {
                text-align: center;
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: 700;
                color: #667eea;
                display: block;
            }
            
            .stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            @media (max-width: 768px) {
                .admin-header {
                    flex-direction: column;
                    gap: 15px;
                }
                
                .nav-grid {
                    grid-template-columns: 1fr;
                }
                
                .stats-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>';
    }
    
    /**
     * Render admin header section
     */
    private function renderHeader(string $baseUrl): string
    {
        return "
        <div class=\"admin-header\">
            <h1 class=\"admin-title\">
                <a href=\"{$baseUrl}/\" class=\"brand\">🎵 Stinkin' Park Admin</a>
            </h1>
            <div class=\"admin-actions\">
                <a href=\"{$baseUrl}/\" class=\"admin-btn\">🏠 Public Site</a>
                <a href=\"{$baseUrl}/admin/logs.php\" class=\"admin-btn\">📊 System Logs</a>
            </div>
        </div>";
    }
    
    /**
     * Render main navigation items
     */
    private function renderMainNav(string $baseUrl): string
    {
        $navItems = [
            [
                'url' => 'upload.php',
                'icon' => '📤',
                'title' => 'Single Upload',
                'description' => 'Upload individual songs with tags',
                'page' => 'upload',
                'badge' => null
            ],
            [
                'url' => 'mass-upload.php',
                'icon' => '📦',
                'title' => 'Mass Upload',
                'description' => 'Bulk upload multiple audio files',
                'page' => 'mass-upload',
                'badge' => null
            ],
            [
                'url' => 'manage.php',
                'icon' => '🎵',
                'title' => 'Manage Songs',
                'description' => 'View and edit individual songs',
                'page' => 'manage',
                'badge' => ['number' => $this->stats['total_songs'], 'type' => 'info']
            ],
            [
                'url' => 'bulk-edit.php',
                'icon' => '📝',
                'title' => 'Bulk Editor',
                'description' => 'Edit multiple songs at once',
                'page' => 'bulk-edit',
                'badge' => null
            ],
            [
                'url' => 'tag-manager.php',
                'icon' => '🏷️',
                'title' => 'Tag Manager',
                'description' => 'Manage tags and categories',
                'page' => 'tag-manager',
                'badge' => ['number' => $this->stats['total_tags'], 'type' => 'info']
            ],
            [
                'url' => 'stations.php',
                'icon' => '📻',
                'title' => 'Stations',
                'description' => 'Create and manage music stations',
                'page' => 'stations',
                'badge' => ['number' => $this->stats['total_stations'], 'type' => 'success']
            ]
        ];
        
        if ($this->stats['recent_uploads'] > 0) {
            $navItems[0]['badge'] = ['number' => $this->stats['recent_uploads'], 'type' => 'success'];
        }
        
        if ($this->stats['log_errors'] > 0) {
            // Add errors badge to logs link in header actions
        }
        
        $navHtml = '<div class="main-nav"><div class="nav-grid">';
        
        foreach ($navItems as $item) {
            $isActive = $this->currentPage === $item['page'] ? 'active' : '';
            $badge = '';
            
            if ($item['badge']) {
                $badge = "<span class=\"nav-badge {$item['badge']['type']}\">{$item['badge']['number']}</span>";
            }
            
            $navHtml .= "
            <a href=\"{$baseUrl}/admin/{$item['url']}\" class=\"nav-item {$isActive}\">
                <div class=\"nav-icon\">{$item['icon']}</div>
                <div class=\"nav-content\">
                    <div class=\"nav-title\">{$item['title']}</div>
                    <div class=\"nav-description\">{$item['description']}</div>
                </div>
                {$badge}
            </a>";
        }
        
        $navHtml .= '</div></div>';
        
        return $navHtml;
    }
    
    /**
     * Render quick statistics section
     */
    private function renderQuickStats(): string
    {
        return "
        <div class=\"quick-stats\">
            <div class=\"stats-grid\">
                <div class=\"stat-item\">
                    <span class=\"stat-number\">{$this->stats['total_songs']}</span>
                    <span class=\"stat-label\">Total Songs</span>
                </div>
                <div class=\"stat-item\">
                    <span class=\"stat-number\">{$this->stats['active_songs']}</span>
                    <span class=\"stat-label\">Active Songs</span>
                </div>
                <div class=\"stat-item\">
                    <span class=\"stat-number\">{$this->stats['total_tags']}</span>
                    <span class=\"stat-label\">Tags</span>
                </div>
                <div class=\"stat-item\">
                    <span class=\"stat-number\">{$this->stats['total_stations']}</span>
                    <span class=\"stat-label\">Stations</span>
                </div>
                <div class=\"stat-item\">
                    <span class=\"stat-number\">{$this->stats['recent_uploads']}</span>
                    <span class=\"stat-label\">This Week</span>
                </div>
                <div class=\"stat-item\">
                    <span class=\"stat-number\" style=\"color: " . ($this->stats['log_errors'] > 0 ? '#dc3545' : '#28a745') . "\">{$this->stats['log_errors']}</span>
                    <span class=\"stat-label\">Errors (24h)</span>
                </div>
            </div>
        </div>";
    }
    
    /**
     * Static method to quickly render navigation
     */
    public static function renderNav(string $currentPage = ''): string
    {
        $nav = new self($currentPage);
        return $nav->render();
    }
}