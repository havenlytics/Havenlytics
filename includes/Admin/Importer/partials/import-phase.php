<?php
/**
 * Property Import Wizard – dedicated import progress / completion phase.
 *
 * Separate from the 4-step setup wizard UI.
 *
 * @package Havenlytics
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section
    id="hvnly-import-phase"
    class="hvnly-import-phase"
    aria-label="<?php esc_attr_e('Property import progress', 'havenlytics'); ?>"
    hidden
>
    <div class="hvnly-import-phase-card">
        <div class="hvnly--property--import-import-loader" id="import-loader" hidden>
            <div class="hvnly-import-loader-card">
                <div class="hvnly-import-loader-visual" aria-hidden="true">
                    <div class="hvnly-import-loader-ring"></div>
                    <div class="hvnly-import-loader-ring hvnly-import-loader-ring--delay"></div>
                    <div class="hvnly-import-loader-core"><i class="fas fa-cloud-upload-alt"></i></div>
                </div>
                <h2 class="hvnly-import-loader-title" id="importLoaderTitle"><?php esc_html_e('Importing Properties…', 'havenlytics'); ?></h2>
                <p class="hvnly-import-loader-message" id="importMessage"><?php esc_html_e('Preparing your import. Please wait…', 'havenlytics'); ?></p>
                <ul class="hvnly-import-loader-stages" id="importStages" aria-live="polite">
                    <li class="is-active" data-stage="prepare"><?php esc_html_e('Preparing import', 'havenlytics'); ?></li>
                    <li data-stage="properties"><?php esc_html_e('Creating properties', 'havenlytics'); ?></li>
                    <li data-stage="images"><?php esc_html_e('Processing images', 'havenlytics'); ?></li>
                    <li data-stage="sections"><?php esc_html_e('Building property sections', 'havenlytics'); ?></li>
                    <li data-stage="complete"><?php esc_html_e('Completed', 'havenlytics'); ?></li>
                </ul>
                <div class="hvnly--property--import-progress-container hvnly-import-loader-progress">
                    <div class="hvnly--property--import-progress-bar">
                        <div class="hvnly--property--import-progress-fill" id="progressFill"></div>
                    </div>
                    <div class="hvnly-import-loader-meta">
                        <span class="hvnly-import-loader-elapsed" id="importElapsedTime">0:00</span>
                        <span class="hvnly--property--import-progress-text" id="progressText">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="hvnly--property--import-result-view" id="import-result-view" hidden></div>
    </div>
</section>
