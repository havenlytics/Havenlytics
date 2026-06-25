<?php
/**
 * Property Actions Template
 * 
 * This template displays action buttons (Share, Print) for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/actions.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/actions.php
 * 3. Modify the copied file to customize the action buttons
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get passed arguments
$hvnly_share_enabled = isset($args['share_enabled']) ? $args['share_enabled'] : true;
$hvnly_print_enabled = isset($args['print_enabled']) ? $args['print_enabled'] : true;

$hvnly_property_id = get_the_ID();
$hvnly_property_permalink = get_permalink($hvnly_property_id);
$hvnly_property_title = get_the_title($hvnly_property_id);
$hvnly_property_image = get_the_post_thumbnail_url($hvnly_property_id, 'large');
?>

<div class="hvnly-property-single__actions">
    <div class="hvnly-property-single__actions-wrapper">

        <?php if ($hvnly_share_enabled) : ?>
        <div class="hvnly-property-single__share-wrapper">
            <button type="button" class="hvnly-property-single__action-btn hvnly-property-single__share-btn"
                aria-label="<?php esc_attr_e('Share this property', 'havenlytics'); ?>">
                <i class="fas fa-share-alt" aria-hidden="true"></i>
                <span class="hvnly-action-text"><?php esc_html_e('Share', 'havenlytics'); ?></span>
                <i class="fas fa-chevron-down hvnly-share-chevron" aria-hidden="true"></i>
            </button>

            <!-- Share dropdown - Pure CSS, no JS -->
            <div class="hvnly-property-single__share-dropdown">
                <div class="hvnly-property-single__share-dropdown-content">
                    <?php
                        // 6 Popular social share platforms
                        $hvnly_share_platforms = array(
                            'facebook' => array(
                                'name' => 'Facebook',
                                'icon' => 'fab fa-facebook-f',
                                'url' => 'https://www.facebook.com/sharer/sharer.php?u={url}'
                            ),
                            'twitter' => array(
                                'name' => 'Twitter',
                                'icon' => 'fab fa-twitter',
                                'url' => 'https://twitter.com/intent/tweet?url={url}&text={title}'
                            ),
                            'linkedin' => array(
                                'name' => 'LinkedIn',
                                'icon' => 'fab fa-linkedin-in',
                                'url' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}'
                            ),
                            'whatsapp' => array(
                                'name' => 'WhatsApp',
                                'icon' => 'fab fa-whatsapp',
                                'url' => 'https://api.whatsapp.com/send?text={title}%20{url}'
                            ),
                            'email' => array(
                                'name' => 'Email',
                                'icon' => 'fas fa-envelope',
                                'url' => 'mailto:?subject={title}&body=Check%20out%20this%20property%3A%20{url}'
                            ),
                            'copy_link' => array(
                                'name' => 'Copy Link',
                                'icon' => 'fas fa-link',
                                'url' => '#',
                                'onclick' => 'navigator.clipboard.writeText("' . esc_js($hvnly_property_permalink) . '"); alert("Link copied to clipboard!"); return false;'
                            )
                        );
                        
                        // Build share URLs and display platforms
                        foreach ($hvnly_share_platforms as $hvnly_key => $hvnly_platform) {
                            $hvnly_share_url = $hvnly_platform['url'];
                            $hvnly_share_url = str_replace('{url}', rawurlencode($hvnly_property_permalink), $hvnly_share_url);
                            $hvnly_share_url = str_replace('{title}', rawurlencode($hvnly_property_title), $hvnly_share_url);
                            $hvnly_share_url = str_replace('{image}', rawurlencode($hvnly_property_image), $hvnly_share_url);
                            
                            $hvnly_onclick = isset($hvnly_platform['onclick']) ? $hvnly_platform['onclick'] : '';
                            ?>
                    <a href="<?php echo esc_url($hvnly_share_url); ?>"
                        class="hvnly-share-platform hvnly-share-<?php echo esc_attr($hvnly_key); ?>"
                        <?php if (!$hvnly_onclick) echo 'target="_blank" rel="noopener noreferrer"'; ?>
                        <?php if ($hvnly_onclick) echo 'onclick="' . esc_js($hvnly_onclick) . '"'; ?>
                        aria-label="<?php echo esc_attr($hvnly_platform['name']); ?>">
                        <i class="<?php echo esc_attr($hvnly_platform['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo esc_html($hvnly_platform['name']); ?></span>
                    </a>
                    <?php
                        }
                        ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($hvnly_print_enabled) : ?>
        <button type="button" class="hvnly-property-single__action-btn hvnly-property-single__print-btn"
            onclick="window.print();" aria-label="<?php esc_attr_e('Print this property', 'havenlytics'); ?>">
            <i class="fas fa-print" aria-hidden="true"></i>
            <span class="hvnly-action-text"><?php esc_html_e('Print', 'havenlytics'); ?></span>
        </button>
        <?php endif; ?>

    </div>
</div>

<style>
/* Share Dropdown Styles - Pure CSS, No JS Required */
.hvnly-property-single__share-wrapper {
    position: relative;
    display: inline-block;
}

.hvnly-property-single__share-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--hvnly-space-sm);
    cursor: pointer;
}

.hvnly-share-chevron {
    font-size: 11px;
    transition: transform var(--hvnly-transition-time);
}

/* Show dropdown on hover - Pure CSS */
.hvnly-property-single__share-wrapper:hover .hvnly-share-chevron {
    transform: rotate(180deg);
}

.hvnly-property-single__share-wrapper:hover .hvnly-property-single__share-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateX(0);
}

/* Dropdown container - Compact size */
.hvnly-property-single__share-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    min-width: 160px;
    background: var(--hvnly-card-bg);
    border-radius: var(--hvnly-border-radius-md);
    box-shadow: var(--hvnly-shadow-card);
    border: var(--hvnly-card-border);
    opacity: 0;
    visibility: hidden;
    transform: translateX(15px);
    transition: opacity 0.25s ease,
        visibility 0.25s ease,
        transform 0.3s ease;
    z-index: 1000;
    overflow: hidden;
}

/* Dropdown arrow */
.hvnly-property-single__share-dropdown::before {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 15px;
    border-width: 7px;
    border-style: solid;
    border-color: transparent transparent var(--hvnly-card-bg) transparent;
}

.hvnly-property-single__share-dropdown::after {
    content: '';
    position: absolute;
    bottom: 100%;
    right: 15px;
    border-width: 8px;
    border-style: solid;
    border-color: transparent transparent var(--hvnly-border-color) transparent;
    z-index: -1;
}

/* Dropdown content - Compact */
.hvnly-property-single__share-dropdown-content {
    padding: 6px 0;
}

/* Share platform items - Compact professional design */
.hvnly-share-platform {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    text-decoration: none;
    color: var(--hvnly-text-primary);
    font-size: 13px;
    font-weight: var(--hvnly-font-weight-medium);
    transition: all 0.25s ease;
}

.hvnly-share-platform:hover {
    background: rgba(var(--hvnly-primary-rgb), 0.08);
    padding-left: 20px;
}

/* Icon styling */
.hvnly-share-platform i {
    width: 18px;
    font-size: 14px;
    text-align: center;
    color: var(--hvnly-brand-primary);
    transition: all 0.25s ease;
}

.hvnly-share-platform:hover i {
    color: var(--hvnly-brand-secondary);
    transform: scale(1.1);
}

/* Platform name */
.hvnly-share-platform span {
    flex: 1;
}

/* Staggered animation for dropdown items on hover */
.hvnly-property-single__share-wrapper:hover .hvnly-share-platform {
    animation: hvnly-fadeInRight 0.25s ease forwards;
    opacity: 0;
}

@keyframes hvnly-fadeInRight {
    from {
        opacity: 0;
        transform: translateX(10px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Stagger delays */
.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(1) {
    animation-delay: 0.02s;
}

.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(2) {
    animation-delay: 0.04s;
}

.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(3) {
    animation-delay: 0.06s;
}

.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(4) {
    animation-delay: 0.08s;
}

.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(5) {
    animation-delay: 0.10s;
}

.hvnly-property-single__share-wrapper:hover .hvnly-share-platform:nth-child(6) {
    animation-delay: 0.12s;
}

/* Responsive */
@media (max-width: 768px) {
    .hvnly-property-single__share-dropdown {
        right: -10px;
        min-width: 150px;
    }

    .hvnly-property-single__share-dropdown::before,
    .hvnly-property-single__share-dropdown::after {
        right: 12px;
    }

    .hvnly-share-platform {
        padding: 6px 14px;
        gap: 10px;
    }

    .hvnly-share-platform i {
        width: 16px;
        font-size: 13px;
    }
}
</style>