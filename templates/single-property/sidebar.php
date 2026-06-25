<?php
/**
 * Sidebar Template
 * 
 * This template displays the sidebar for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/sidebar.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/sidebar.php
 * 3. Modify the copied file to customize the sidebar
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

 if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( is_active_sidebar( 'hvnly_single_property_sidebar_widgets_area' ) ) : ?>
<div class="hvnly-property-single__widget-area">

    <?php
    /**
     * Dynamic Single property page sidebar
     */
    dynamic_sidebar( 'hvnly_single_property_sidebar_widgets_area' );

    ?>
</div>
<?php endif; ?>
