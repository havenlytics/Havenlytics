<?php
/**
 * Content None Template
 * 
 * This template displays the content when no properties are found.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/content-none.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/content-none.php
 * 3. Modify the copied file to customize the content
 * 
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       2.0.0
 */


 

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $wp;

do_action('hvnly_before_property_taxonomy');
?>

<div class="hvnly-content-none-wrapper">
    <div class="hvnly-content-none">
        <!-- Decorative Elements -->
        <div class="hvnly-content-none__decor hvnly-content-none__decor--1"></div>
        <div class="hvnly-content-none__decor hvnly-content-none__decor--2"></div>
        <div class="hvnly-content-none__decor hvnly-content-none__decor--3"></div>
        
        <!-- Icon/Image Section -->
        <div class="hvnly-content-none__icon-wrapper">
            <div class="hvnly-content-none__icon-circle">
                <?php if (!empty(get_option('hvnly_content_not_found_image'))): ?>
                    <img src="<?php echo esc_url(get_option('hvnly_content_not_found_image')); ?>"
                        alt="<?php echo esc_attr__('Not Found', 'havenlytics'); ?>"
                        class="hvnly-content-none__custom-image">
                <?php else: ?>
                    <svg class="hvnly-content-none__icon" width="120" height="120" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M21 21L16.65 16.65M19 11C19 15.4183 15.4183 19 11 19C6.58172 19 3 15.4183 3 11C3 6.58172 6.58172 3 11 3C15.4183 3 19 6.58172 19 11Z" 
                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 8V11M11 14H11.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                <?php endif; ?>
            </div>
        </div>

        <?php
        if (!empty(get_option('hvnly_content_not_found')) && (get_option('hvnly_content_not_found') != '')):
            echo wp_kses_post(get_option('hvnly_content_not_found'));
        else:
        ?>
            <!-- Content Section -->
            <div class="hvnly-content-none__content">
                <h2 class="hvnly-content-none__title">
                    <?php echo esc_html__('No Properties Found', 'havenlytics'); ?>
                </h2>
                
                <p class="hvnly-content-none__message">
                    <?php echo esc_html__('We couldn\'t find any properties matching your criteria. Try adjusting your filters or browse all available listings.', 'havenlytics'); ?>
                </p>

                <!-- Search Suggestions -->
                <div class="hvnly-content-none__suggestions">
                    <h3 class="hvnly-content-none__suggestions-title">
                        <?php echo esc_html__('You might want to:', 'havenlytics'); ?>
                    </h3>
                    
                    <ul class="hvnly-content-none__suggestions-list">
                        <li class="hvnly-content-none__suggestions-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M5 12H19M19 12L15 8M19 12L15 16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php echo esc_html__('Check your spelling', 'havenlytics'); ?>
                        </li>
                        <li class="hvnly-content-none__suggestions-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M12 6V12L15 15M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <?php echo esc_html__('Try more general keywords', 'havenlytics'); ?>
                        </li>
                        <li class="hvnly-content-none__suggestions-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M9 20L3 17V7L9 4L15 7L21 4V14M9 20V10M9 20L15 17M15 17V7M15 17L21 20M21 14V4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php echo esc_html__('Expand your search area', 'havenlytics'); ?>
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="hvnly-content-none__actions">
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="hvnly-content-none__btn hvnly-content-none__btn--home">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 12L5 10M5 10L12 3L19 10M5 10V20C5 20.5523 5.44772 21 6 21H9M19 10L21 12M19 10V20C19 20.5523 18.5523 21 18 21H15M9 21C9.55228 21 10 20.5523 10 20V16C10 15.4477 10.4477 15 11 15H13C13.5523 15 14 15.4477 14 16V20C14 20.5523 14.4477 21 15 21M9 21H15" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        <span><?php echo esc_html__('Go to Homepage', 'havenlytics'); ?></span>
                    </a>
                    
                    <?php
                    // Sprint 31D: was a hardcoded "/property" (broken on
                    // subdirectory installs and custom slugs) with an inline style.
                    $hvnly_archive_link = get_post_type_archive_link( 'hvnly_property' );
                    ?>
                    <a href="<?php echo esc_url( $hvnly_archive_link ? $hvnly_archive_link : home_url( '/' ) ); ?>" class="hvnly-content-none__btn hvnly-content-none__btn--primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M3 10H21M21 10L15 4M21 10L15 16M7 14L3 10L7 6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?php echo esc_html__('View All Properties', 'havenlytics'); ?></span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
do_action('hvnly_after_property_taxonomy');
?>