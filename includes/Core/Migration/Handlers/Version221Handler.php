<?php
/**
 * Version 2.2.1 Migration Handler
 * 
 * Ensures property builder configuration is set up with UNIQUE IDs
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.2.1
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

if (!defined('ABSPATH')) {
    exit;
}

class Version221Handler implements MigrationInterface {
    
    use MigrationTrait;
    
    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';
    
    public function get_version(): string {
        return '2.2.1';
    }
    
    public function get_description(): string {
        return 'Initial property builder configuration setup with UNIQUE group IDs';
    }
    
    public function is_needed(): bool {
        if ( ! $this->builder_config_is_empty() ) {
            return false;
        }

        if ( $this->site_has_published_properties() ) {
            $this->log(
                'Skipping 2.2.1 — builder config is empty but published properties exist; refusing to overwrite.',
                'warning',
                '2.2.1'
            );
            $this->attempt_builder_config_recovery();
            return false;
        }

        return true;
    }
    
    public function up(): bool {
        $this->log('Starting Version 2.2.1 migration - Setting up fresh configuration');
        
        // Try to get configuration from UnifiedFieldGenerator
        if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
            $config = $unified->get_unified_configuration();
            update_option(self::PROPERTY_BUILDER_KEY, $config);
            $this->log('Configuration created from UnifiedFieldGenerator');
        } 
        // Fallback to DnDSections if UnifiedFieldGenerator not available
        elseif (class_exists('\HvnlyNab\Api\Type\Builders\DnDSections')) {
            $dnd = new \HvnlyNab\Api\Type\Builders\DnDSections();
            $reflection = new \ReflectionClass($dnd);
            $method = $reflection->getMethod('build_config_with_unique_ids');
            $method->setAccessible(true);
            $config = $method->invoke($dnd);
            update_option(self::PROPERTY_BUILDER_KEY, $config);
            $this->log('Configuration created from DnDSections fallback');
        }
        
        return true;
    }
    
    public function down(): bool {
        $this->log('Rolling back Version 2.2.1 migration');
        delete_option(self::PROPERTY_BUILDER_KEY);
        return true;
    }
}