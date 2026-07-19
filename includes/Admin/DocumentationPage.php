<?php

namespace HvnlyNab\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native Havenlytics Documentation admin page.
 *
 * Read-only help hub — no telemetry, no iframe, no redirect.
 *
 * @package HvnlyNab\Admin
 */
final class DocumentationPage {

	public const PAGE_SLUG = 'hvnly_property_documentation';

	/**
	 * Register asset hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue Documentation page styles on the correct screen only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( 'hvnly_property_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '1.0.0';

		wp_enqueue_style(
			'hvnly-admin-fontawesome-all',
			HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
			array(),
			$version
		);

		$boot_path = HVNLYNAB_ASSETS_PATH . '/admin/css/hvnly-admin-boot.css';
		if ( file_exists( $boot_path ) ) {
			wp_enqueue_style(
				'hvnly-admin-boot',
				HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-boot.css',
				array(),
				$version
			);
		}

		$docs_path = HVNLYNAB_ASSETS_PATH . '/admin/css/hvnly-documentation.css';
		if ( file_exists( $docs_path ) ) {
			wp_enqueue_style(
				'hvnly-documentation',
				HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-documentation.css',
				array( 'hvnly-admin-boot', 'hvnly-admin-fontawesome-all' ),
				$version
			);
		}
	}

	/**
	 * Render the Documentation admin page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( apply_filters( 'hvnly_admin_capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'havenlytics' ) );
		}

		$system   = self::get_system_information();
		$help     = self::get_quick_help_cards();
		$videos   = self::get_video_cards();
		$support  = self::get_support_cards();
		$links    = self::get_useful_links();
		?>
		<div class="wrap hvnly-docs-wrap">
			<section class="hvnly-docs-hero" aria-labelledby="hvnly-docs-hero-title">
				<div class="hvnly-docs-hero__content">
					<p class="hvnly-docs-hero__eyebrow"><?php esc_html_e( 'Havenlytics Help Center', 'havenlytics' ); ?></p>
					<h1 id="hvnly-docs-hero-title" class="hvnly-docs-hero__title">
						<?php esc_html_e( 'Havenlytics Documentation', 'havenlytics' ); ?>
					</h1>
					<p class="hvnly-docs-hero__intro">
						<?php esc_html_e( 'Find guides, tutorials, and support resources to set up properties, builders, search, analytics, marketing, and more.', 'havenlytics' ); ?>
					</p>
					<div class="hvnly-docs-hero__actions">
						<a
							class="hvnly-docs-btn hvnly-docs-btn--primary"
							href="https://havenlytics.com/documentation/"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php esc_html_e( 'Open Documentation', 'havenlytics' ); ?>
						</a>
						<a
							class="hvnly-docs-btn hvnly-docs-btn--secondary"
							href="https://havenlytics.com/support/"
							target="_blank"
							rel="noopener noreferrer"
						>
							<?php esc_html_e( 'Contact Support', 'havenlytics' ); ?>
						</a>
					</div>
				</div>
			</section>

			<section class="hvnly-docs-section" aria-labelledby="hvnly-docs-help-title">
				<div class="hvnly-docs-section__header">
					<h2 id="hvnly-docs-help-title"><?php esc_html_e( 'Quick Help', 'havenlytics' ); ?></h2>
					<p><?php esc_html_e( 'Jump to the topic you need. Available guides open instantly; Coming Soon topics are disabled until published.', 'havenlytics' ); ?></p>
				</div>
				<div class="hvnly-docs-card-grid">
					<?php foreach ( $help as $card ) : ?>
						<?php self::render_link_card( $card ); ?>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="hvnly-docs-section" aria-labelledby="hvnly-docs-video-title">
				<div class="hvnly-docs-section__header">
					<h2 id="hvnly-docs-video-title"><?php esc_html_e( 'Video Tutorials', 'havenlytics' ); ?></h2>
					<p><?php esc_html_e( 'Watch Havenlytics walkthroughs on YouTube. Coming Soon cards stay disabled until published.', 'havenlytics' ); ?></p>
				</div>
				<div class="hvnly-docs-card-grid hvnly-docs-card-grid--videos">
					<?php foreach ( $videos as $video ) : ?>
						<?php self::render_video_card( $video ); ?>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="hvnly-docs-section" aria-labelledby="hvnly-docs-support-title">
				<div class="hvnly-docs-section__header">
					<h2 id="hvnly-docs-support-title"><?php esc_html_e( 'Support', 'havenlytics' ); ?></h2>
					<p><?php esc_html_e( 'Get help, report issues, or share ideas with the Havenlytics team.', 'havenlytics' ); ?></p>
				</div>
				<div class="hvnly-docs-card-grid hvnly-docs-card-grid--support">
					<?php foreach ( $support as $item ) : ?>
						<a
							class="hvnly-docs-card hvnly-docs-card--support"
							href="<?php echo esc_url( $item['url'] ); ?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							<span class="hvnly-docs-card__icon" aria-hidden="true">
								<i class="<?php echo esc_attr( $item['icon'] ); ?>"></i>
							</span>
							<span class="hvnly-docs-card__body">
								<span class="hvnly-docs-card__title"><?php echo esc_html( $item['title'] ); ?></span>
								<span class="hvnly-docs-card__desc"><?php echo esc_html( $item['description'] ); ?></span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>

			<section class="hvnly-docs-section" aria-labelledby="hvnly-docs-links-title">
				<div class="hvnly-docs-section__header">
					<h2 id="hvnly-docs-links-title"><?php esc_html_e( 'Useful Links', 'havenlytics' ); ?></h2>
				</div>
				<ul class="hvnly-docs-links">
					<?php foreach ( $links as $link ) : ?>
						<li>
							<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<span class="hvnly-docs-links__label"><?php echo esc_html( $link['label'] ); ?></span>
								<span class="hvnly-docs-links__url"><?php echo esc_html( $link['url'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>

			<section class="hvnly-docs-section" aria-labelledby="hvnly-docs-system-title">
				<div class="hvnly-docs-section__header">
					<h2 id="hvnly-docs-system-title"><?php esc_html_e( 'System Information', 'havenlytics' ); ?></h2>
					<p><?php esc_html_e( 'Read-only environment details for troubleshooting. Nothing is sent off-site.', 'havenlytics' ); ?></p>
				</div>
				<div class="hvnly-docs-system" role="table" aria-label="<?php esc_attr_e( 'System Information', 'havenlytics' ); ?>">
					<?php foreach ( $system as $row ) : ?>
						<div class="hvnly-docs-system__row" role="row">
							<span class="hvnly-docs-system__label" role="rowheader"><?php echo esc_html( $row['label'] ); ?></span>
							<span class="hvnly-docs-system__value" role="cell">
								<?php if ( ! empty( $row['status'] ) ) : ?>
									<span class="hvnly-docs-status hvnly-docs-status--<?php echo esc_attr( $row['status'] ); ?>">
										<?php echo esc_html( $row['value'] ); ?>
									</span>
								<?php else : ?>
									<?php echo esc_html( $row['value'] ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render a Quick Help card (link, internal, or Coming Soon).
	 *
	 * @param array{title:string,description:string,icon:string,url?:string,internal?:bool,coming_soon?:bool} $card Card data.
	 * @return void
	 */
	private static function render_link_card( array $card ): void {
		$coming_soon = ! empty( $card['coming_soon'] );
		$classes     = 'hvnly-docs-card';
		if ( $coming_soon ) {
			$classes .= ' hvnly-docs-card--disabled';
		}

		$inner  = '<span class="hvnly-docs-card__icon" aria-hidden="true"><i class="' . esc_attr( $card['icon'] ) . '"></i></span>';
		$inner .= '<span class="hvnly-docs-card__body">';
		$inner .= '<span class="hvnly-docs-card__title">' . esc_html( $card['title'] ) . '</span>';
		$inner .= '<span class="hvnly-docs-card__desc">' . esc_html( $card['description'] ) . '</span>';
		$inner .= '</span>';
		if ( $coming_soon ) {
			$inner .= '<span class="hvnly-docs-badge">' . esc_html__( 'Coming Soon', 'havenlytics' ) . '</span>';
		}

		if ( $coming_soon || empty( $card['url'] ) ) {
			printf(
				'<div class="%1$s" aria-disabled="true">%2$s</div>',
				esc_attr( $classes ),
				$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			return;
		}

		$target = empty( $card['internal'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		printf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $classes ),
			esc_url( $card['url'] ),
			$target, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string.
			$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
	}

	/**
	 * Render a video tutorial card.
	 *
	 * @param array{title:string,description:string,url?:string,coming_soon?:bool} $video Video data.
	 * @return void
	 */
	private static function render_video_card( array $video ): void {
		$coming_soon = ! empty( $video['coming_soon'] ) || empty( $video['url'] );
		$classes     = 'hvnly-docs-card hvnly-docs-card--video';
		if ( $coming_soon ) {
			$classes .= ' hvnly-docs-card--disabled';
		}

		$inner  = '<span class="hvnly-docs-card__play" aria-hidden="true">▶</span>';
		$inner .= '<span class="hvnly-docs-card__body">';
		$inner .= '<span class="hvnly-docs-card__title">' . esc_html( $video['title'] ) . '</span>';
		$inner .= '<span class="hvnly-docs-card__desc">' . esc_html( $video['description'] ) . '</span>';
		$inner .= '</span>';
		if ( $coming_soon ) {
			$inner .= '<span class="hvnly-docs-badge">' . esc_html__( 'Coming Soon', 'havenlytics' ) . '</span>';
		}

		if ( $coming_soon ) {
			printf(
				'<div class="%1$s" aria-disabled="true">%2$s</div>',
				esc_attr( $classes ),
				$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			);
			return;
		}

		printf(
			'<a class="%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			esc_attr( $classes ),
			esc_url( $video['url'] ),
			$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
	}

	/**
	 * Quick help cards.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_quick_help_cards(): array {
		$search_builder_url = admin_url(
			'edit.php?post_type=hvnly_property&page=hvnly_property_settings&tab=search-property'
		);

		return array(
			array(
				'title'       => __( 'Documentation', 'havenlytics' ),
				'description' => __( 'Browse the full online documentation hub.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/documentation/',
				'icon'        => 'fas fa-book',
			),
			array(
				'title'       => __( 'Getting Started', 'havenlytics' ),
				'description' => __( 'Install, activate, and complete first setup.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/docs/category/getting-started/',
				'icon'        => 'fas fa-rocket',
			),
			array(
				'title'       => __( 'Property Builder', 'havenlytics' ),
				'description' => __( 'Configure property forms and card layouts.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/documentation/property-builder-introduction/',
				'icon'        => 'fas fa-th-large',
			),
			array(
				'title'       => __( 'Search Builder', 'havenlytics' ),
				'description' => __( 'Open Settings → Search → Search Property.', 'havenlytics' ),
				'url'         => $search_builder_url,
				'icon'        => 'fas fa-search',
				'internal'    => true,
			),
			array(
				'title'       => __( 'Import Wizard', 'havenlytics' ),
				'description' => __( 'Import properties and media safely.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/documentation/havenlytics-setup-wizard/',
				'icon'        => 'fas fa-file-import',
			),
			array(
				'title'       => __( 'Analytics', 'havenlytics' ),
				'description' => __( 'Understand property views and insights.', 'havenlytics' ),
				'icon'        => 'fas fa-chart-line',
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Marketing', 'havenlytics' ),
				'description' => __( 'Manage inquiries and lead workflows.', 'havenlytics' ),
				'icon'        => 'fas fa-bullhorn',
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Agent Workspace', 'havenlytics' ),
				'description' => __( 'Manage your frontend Agent Workspace, registration, listings, and account.', 'havenlytics' ),
				'icon'        => 'fas fa-user-tie',
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Agencies', 'havenlytics' ),
				'description' => __( 'Organize agencies and assignments.', 'havenlytics' ),
				'icon'        => 'fas fa-building',
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Settings', 'havenlytics' ),
				'description' => __( 'Configure currency, search, maps, and more.', 'havenlytics' ),
				'icon'        => 'fas fa-cog',
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Theme Integration', 'havenlytics' ),
				'description' => __( 'Use Havenlytics with compatible themes.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/documentation/build-professional-real-estate-websites-with-havenlytics-realty/',
				'icon'        => 'fas fa-paint-brush',
			),
			array(
				'title'       => __( 'Customization', 'havenlytics' ),
				'description' => __( 'Hooks, templates, and advanced customization.', 'havenlytics' ),
				'icon'        => 'fas fa-sliders-h',
				'coming_soon' => true,
			),
		);
	}

	/**
	 * Video tutorial cards.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_video_cards(): array {
		return array(
			array(
				'title'       => __( 'Getting Started', 'havenlytics' ),
				'description' => __( 'Overview of the Havenlytics admin.', 'havenlytics' ),
				'url'         => 'https://www.youtube.com/watch?v=JU6UX3jCrhg&t=2s',
			),
			array(
				'title'       => __( 'Install Havenlytics', 'havenlytics' ),
				'description' => __( 'Install and activate the plugin.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Import Demo', 'havenlytics' ),
				'description' => __( 'Load demo content with the Import Wizard.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Property Builder', 'havenlytics' ),
				'description' => __( 'Customize property forms and cards.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Search Builder', 'havenlytics' ),
				'description' => __( 'Configure search and results.', 'havenlytics' ),
				'url'         => 'https://www.youtube.com/watch?v=d2mJra8RYM8&t=735s',
			),
			array(
				'title'       => __( 'Shortcodes', 'havenlytics' ),
				'description' => __( 'Use Havenlytics shortcodes on your site.', 'havenlytics' ),
				'url'         => 'https://www.youtube.com/watch?v=DJ2IYECJ_YA&t=44s',
			),
			array(
				'title'       => __( 'Analytics', 'havenlytics' ),
				'description' => __( 'Read views and performance reports.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Marketing', 'havenlytics' ),
				'description' => __( 'Handle inquiries and follow-ups.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Agents', 'havenlytics' ),
				'description' => __( 'Create and assign agents.', 'havenlytics' ),
				'coming_soon' => true,
			),
			array(
				'title'       => __( 'Theme Setup', 'havenlytics' ),
				'description' => __( 'Connect a compatible theme.', 'havenlytics' ),
				'url'         => 'https://www.youtube.com/watch?v=AiYDMJPgsTY&t=2s',
			),
		);
	}

	/**
	 * Support cards.
	 *
	 * @return array<int, array{title:string,description:string,url:string,icon:string}>
	 */
	private static function get_support_cards(): array {
		return array(
			array(
				'title'       => __( 'Support Center', 'havenlytics' ),
				'description' => __( 'Contact the Havenlytics support team.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/support/',
				'icon'        => 'fas fa-life-ring',
			),
			array(
				'title'       => __( 'Documentation', 'havenlytics' ),
				'description' => __( 'Full product documentation online.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/documentation/',
				'icon'        => 'fas fa-book-open',
			),
			array(
				'title'       => __( 'FAQ', 'havenlytics' ),
				'description' => __( 'Answers to common questions.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/faq/',
				'icon'        => 'fas fa-question-circle',
			),
			array(
				'title'       => __( 'Report a Bug', 'havenlytics' ),
				'description' => __( 'Open an issue on GitHub.', 'havenlytics' ),
				'url'         => 'https://github.com/havenlytics/havenlytics/issues',
				'icon'        => 'fas fa-bug',
			),
			array(
				'title'       => __( 'Feature Request', 'havenlytics' ),
				'description' => __( 'Suggest improvements for the roadmap.', 'havenlytics' ),
				'url'         => 'https://havenlytics.com/support/',
				'icon'        => 'fas fa-lightbulb',
			),
			array(
				'title'       => __( 'Community', 'havenlytics' ),
				'description' => __( 'WordPress.org support forums.', 'havenlytics' ),
				'url'         => 'https://wordpress.org/support/plugin/havenlytics/',
				'icon'        => 'fas fa-users',
			),
		);
	}

	/**
	 * Useful external links.
	 *
	 * @return array<int, array{label:string,url:string}>
	 */
	private static function get_useful_links(): array {
		return array(
			array(
				'label' => __( 'Website', 'havenlytics' ),
				'url'   => 'https://havenlytics.com',
			),
			array(
				'label' => __( 'Documentation', 'havenlytics' ),
				'url'   => 'https://havenlytics.com/documentation/',
			),
			array(
				'label' => __( 'Support', 'havenlytics' ),
				'url'   => 'https://havenlytics.com/support/',
			),
			array(
				'label' => __( 'WordPress Plugin', 'havenlytics' ),
				'url'   => 'https://wordpress.org/plugins/havenlytics/',
			),
			array(
				'label' => __( 'Theme', 'havenlytics' ),
				'url'   => 'https://wordpress.org/themes/havenlytics-realty/',
			),
			array(
				'label' => __( 'GitHub', 'havenlytics' ),
				'url'   => 'https://github.com/havenlytics/havenlytics',
			),
		);
	}

	/**
	 * Read-only system information rows.
	 *
	 * @return array<int, array{label:string,value:string,status?:string}>
	 */
	private static function get_system_information(): array {
		$theme = wp_get_theme();

		$builder_ok   = self::build_asset_ready( 'builder' );
		$analytics_ok = self::build_asset_ready( 'reports' );
		$settings_ok  = self::build_asset_ready( 'settings' );
		$rest_ok      = function_exists( 'rest_url' ) && is_string( rest_url() ) && rest_url() !== '';

		$modules = array();
		if ( post_type_exists( 'hvnly_property' ) ) {
			$modules[] = __( 'Properties', 'havenlytics' );
		}
		if ( post_type_exists( 'hvnly_agent' ) ) {
			$modules[] = __( 'Agents', 'havenlytics' );
		}
		if ( class_exists( '\HvnlyNab\ContactAgent\ContactAgentBootstrap' )
			|| class_exists( '\HvnlyNab\ContactAgent\Admin\InquiryAdminPage' ) ) {
			$modules[] = __( 'Marketing / Inquiries', 'havenlytics' );
		}
		if ( function_exists( 'hvnly_is_cache_enabled' ) && hvnly_is_cache_enabled() ) {
			$modules[] = __( 'Cache', 'havenlytics' );
		}
		$modules[] = __( 'Settings', 'havenlytics' );
		$modules[] = __( 'Property Builder', 'havenlytics' );
		$modules[] = __( 'Analytics', 'havenlytics' );
		$modules[] = __( 'Agent Workspace System', 'havenlytics' );

		return array(
			array(
				'label' => __( 'Plugin Version', 'havenlytics' ),
				'value' => defined( 'HVNLYNAB_VERSION' ) ? (string) HVNLYNAB_VERSION : '—',
			),
			array(
				'label' => __( 'WordPress Version', 'havenlytics' ),
				'value' => (string) get_bloginfo( 'version' ),
			),
			array(
				'label' => __( 'PHP Version', 'havenlytics' ),
				'value' => PHP_VERSION,
			),
			array(
				'label' => __( 'Theme', 'havenlytics' ),
				'value' => $theme->exists() ? $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) : '—',
			),
			array(
				'label' => __( 'Active Modules', 'havenlytics' ),
				'value' => implode( ', ', $modules ),
			),
			array(
				'label'  => __( 'REST Status', 'havenlytics' ),
				'value'  => $rest_ok ? __( 'Available', 'havenlytics' ) : __( 'Unavailable', 'havenlytics' ),
				'status' => $rest_ok ? 'ok' : 'missing',
			),
			array(
				'label'  => __( 'Builder Status', 'havenlytics' ),
				'value'  => $builder_ok ? '🟢 ' . __( 'Active', 'havenlytics' ) : '🔴 ' . __( 'Missing', 'havenlytics' ),
				'status' => $builder_ok ? 'ok' : 'missing',
			),
			array(
				'label'  => __( 'Analytics Status', 'havenlytics' ),
				'value'  => $analytics_ok ? '🟢 ' . __( 'Active', 'havenlytics' ) : '🔴 ' . __( 'Missing', 'havenlytics' ),
				'status' => $analytics_ok ? 'ok' : 'missing',
			),
			array(
				'label'  => __( 'Settings Status', 'havenlytics' ),
				'value'  => $settings_ok ? '🟢 ' . __( 'Active', 'havenlytics' ) : '🔴 ' . __( 'Missing', 'havenlytics' ),
				'status' => $settings_ok ? 'ok' : 'missing',
			),
			array(
				'label'  => __( 'Agent Workspace System', 'havenlytics' ),
				'value'  => '🟢 ' . __( 'Active', 'havenlytics' ),
				'status' => 'ok',
			),
		);
	}

	/**
	 * Whether a React admin build is present (reads asset manifest, never hardcodes filenames).
	 *
	 * Matches Assets.php enqueue logic: {folder}.asset.php + manifest script/style (e.g. 0.js).
	 *
	 * @param string $folder Build folder name (settings|builder|reports).
	 * @return bool
	 */
	private static function build_asset_ready( string $folder ): bool {
		$build_root = defined( 'HVNLYNAB_BUILD_PATH' ) ? HVNLYNAB_BUILD_PATH : '';
		if ( $build_root === '' || ! is_dir( $build_root ) ) {
			return false;
		}

		$build_dir  = trailingslashit( $build_root ) . $folder;
		$asset_file = $build_dir . '/' . $folder . '.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return false;
		}

		$manifest = include $asset_file;
		if ( ! is_array( $manifest ) ) {
			return false;
		}

		$script = ! empty( $manifest['script'] ) && is_string( $manifest['script'] )
			? $manifest['script']
			: $folder . '.js';
		$style  = ! empty( $manifest['style'] ) && is_string( $manifest['style'] )
			? $manifest['style']
			: $folder . '.css';

		$script_path = $build_dir . '/' . $script;
		$style_path  = $build_dir . '/' . $style;

		return is_readable( $script_path ) && is_readable( $style_path );
	}
}
