<?php
namespace PluginClassName\Classes;


class PluginMenuBuilder
{
    private static $instance = null;
    private $menu_items = [];
    private $plugin_settings = [];
    private $required_settings = ['page_title', 'menu_title', 'menu_slug', 'callback'];
    
    private function __construct()
    {
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setPluginSettings(array $settings): self
    {
        // Validate required settings
        foreach ($this->required_settings as $required) {
            if (!isset($settings[$required])) {
                throw new \InvalidArgumentException("Missing required setting: {$required}");
            }
        }
        
        // Set defaults with proper validation
        $this->plugin_settings = [
            'page_title' => sanitize_text_field($settings['page_title']),
            'menu_title' => sanitize_text_field($settings['menu_title']),
            'capability' => isset($settings['capability']) ? sanitize_text_field($settings['capability']) : 'manage_options',
            'menu_slug'  => sanitize_key($settings['menu_slug']),
            'callback'   => $settings['callback'],
            'icon'       => isset($settings['icon']) ? sanitize_text_field($settings['icon']) : 'dashicons-admin-generic',
            'position'   => isset($settings['position']) ? (int)$settings['position'] : 25
        ];
        
        return $this;
    }
    
    public function addMenuItem(string $id, array $config): self
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('Menu item ID cannot be empty');
        }
        
        // Validate and sanitize config
        $default_config = [
            'title'      => '',
            'capability' => 'manage_options',
            'slug'       => '',
            'order'      => 10
        ];
        
        $config = array_merge($default_config, $config);
        
        $this->menu_items[$id] = [
            'title'      => sanitize_text_field($config['title']),
            'capability' => sanitize_text_field($config['capability']),
            'slug'       => sanitize_key($config['slug']),
            'order'      => (int)$config['order']
        ];
        
        return $this;
    }
    
    public function removeMenuItem(string $id): self
    {
        if (isset($this->menu_items[$id])) {
            unset($this->menu_items[$id]);
        }
        return $this;
    }
    
    public function buildMenu(): void
    {
        // Validate if settings are set before building
        if (empty($this->plugin_settings)) {
            throw new \RuntimeException('Plugin settings must be set before building menu');
        }
        
        add_action('admin_menu', function () {
            if (!current_user_can($this->plugin_settings['capability'])) {
                return;
            }
            
            global $submenu;
            
            try {
                // Add main menu page
                $page = add_menu_page(
                    $this->plugin_settings['page_title'],
                    $this->plugin_settings['menu_title'],
                    $this->plugin_settings['capability'],
                    $this->plugin_settings['menu_slug'],
                    $this->plugin_settings['callback'],
                    $this->plugin_settings['icon'],
                    $this->plugin_settings['position']
                );
                
                if (!$page) {
                    throw new \RuntimeException('Failed to add menu page');
                }
                
                // Sort menu items by order
                uasort($this->menu_items, function ($a, $b) {
                    return $a['order'] <=> $b['order'];
                });
                
                // Build submenu with proper array key handling
                foreach ($this->menu_items as $id => $item) {
                    $submenu[$this->plugin_settings['menu_slug']][] = [
                        $item['title'],
                        $item['capability'],
                        'admin.php?page=' . $this->plugin_settings['menu_slug'] . '#/' . $item['slug']
                    ];
                }
            } catch (\Exception $e) {
                // Log error appropriately
                error_log('PluginMenuBuilder Error: ' . $e->getMessage());
            }
        });
    }
    
    // Prevent cloning of the instance
    private function __clone()
    {
    }
    
    // Prevent unserializing of the instance
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
