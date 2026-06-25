<?php
/**
 * Property Query Manager - Advanced Query Isolation
 * 
 * Prevents content bleeding by properly isolating property queries
 * 
 * @package     Havenlytics
 * @subpackage  Frontend\Query
 * @since       2.2.1
 */

namespace HvnlyNab\Frontend\Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Property Query Manager Class
 * 
 * Manages property queries with proper isolation to prevent content bleeding
 * when rendering widgets in Elementor or other page builders.
 * 
 * @since 2.2.1
 */
class PropertyQueryManager {
    
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * Stack of queries for nested rendering
     *
     * @var array
     */
    private $query_stack = [];
    
    /**
     * Whether we're in a property query context
     *
     * @var bool
     */
    private $is_in_property_query = false;
    
    /**
     * Original WP Query backup
     *
     * @var \WP_Query|null
     */
    private $backup_wp_query = null;
    
    /**
     * Original post backup
     *
     * @var \WP_Post|null
     */
    private $backup_post = null;
    
    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Add WordPress shutdown hook to ensure cleanup
        add_action('shutdown', [$this, 'emergency_cleanup']);
    }
    
    /**
     * Execute a property query with proper isolation
     *
     * @param array    $args     Query arguments
     * @param callable $callback Callback to execute with the query results
     * @return mixed Result of the callback
     */
    public function isolated_property_query(array $args, callable $callback) {
        global $wp_query, $post;
        
        // Save current state to stack
        $this->query_stack[] = [
            'wp_query' => $wp_query,
            'post' => $post,
            'in_property_query' => $this->is_in_property_query,
        ];
        
        // Set isolation flags
        $this->is_in_property_query = true;
        
        // Backup current globals
        $this->backup_wp_query = $wp_query;
        $this->backup_post = $post;
        
        // Execute the query
        $property_query = new \WP_Query($args);
        
        // Temporarily replace global query
        $wp_query = $property_query;
        
        // Execute callback
        $result = null;
        try {
            $result = $callback($property_query);
        } catch (\Throwable $e) {
            // Log error but don't break the site
            if ( function_exists( 'hvnly_debug_log' ) ) {
                hvnly_debug_log( 'PropertyQueryManager error: ' . $e->getMessage() );
            }
        }
        
        // Restore original global state
        $wp_query = $this->backup_wp_query;
        $GLOBALS['post'] = $this->backup_post;
        
        // Reset post data
        wp_reset_postdata();
        
        // Restore previous state from stack
        $previous = array_pop($this->query_stack);
        if ($previous) {
            $wp_query = $previous['wp_query'];
            $GLOBALS['post'] = $previous['post'];
            $this->is_in_property_query = $previous['in_property_query'];
        } else {
            $this->is_in_property_query = false;
            $this->backup_wp_query = null;
            $this->backup_post = null;
        }
        
        return $result;
    }
    
    /**
     * Execute a property loop with isolation
     *
     * @param \WP_Query $query    Property query
     * @param callable  $callback Callback for each post
     * @return void
     */
    public function isolated_property_loop(\WP_Query $query, callable $callback): void {
        global $post;
        
        // Save original post
        $original_post = $post;
        
        // Set up the loop
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                
                try {
                    $callback(get_the_ID());
                } catch (\Throwable $e) {
                    if ( function_exists( 'hvnly_debug_log' ) ) {
                        hvnly_debug_log( 'PropertyQueryManager loop error: ' . $e->getMessage() );
                    }
                }
            }
        }
        
        // Restore original post
        $post = $original_post;
        wp_reset_postdata();
    }
    
    /**
     * Check if we're currently in a property query context
     *
     * @return bool
     */
    public function is_in_property_query(): bool {
        return $this->is_in_property_query;
    }
    
    /**
     * Get the current query stack depth
     *
     * @return int
     */
    public function get_query_depth(): int {
        return count($this->query_stack);
    }
    
    /**
     * Reset all query state (used for debugging/emergency)
     *
     * @return void
     */
    public function reset(): void {
        $this->query_stack = [];
        $this->is_in_property_query = false;
        $this->backup_wp_query = null;
        $this->backup_post = null;
        
        wp_reset_postdata();
        wp_reset_query();
    }
    
    /**
     * Emergency cleanup on shutdown
     * Ensures WordPress state is restored even if errors occur
     *
     * @return void
     */
    public function emergency_cleanup(): void {
        if ($this->is_in_property_query || !empty($this->query_stack)) {
            $this->reset();
        }
    }
}