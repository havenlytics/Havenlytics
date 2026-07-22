<?php
/**
 * HVN: Property Inquiry Form block presentation.
 *
 * Thin wrapper around the canonical Contact Agent form partial. No inquiry
 * markup fields or submit logic is duplicated — only presentation chrome and
 * optional dropdowns that still submit the existing property_id / agent_id fields.
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_a = ( isset( $args ) && is_array( $args ) ) ? $args : array();

$hvnly_wrapper            = isset( $hvnly_a['wrapper'] ) ? (string) $hvnly_a['wrapper'] : 'class="hvnly-block-inquiry"';
$hvnly_show_title         = ! empty( $hvnly_a['show_title'] );
$hvnly_form_title         = isset( $hvnly_a['form_title'] ) ? (string) $hvnly_a['form_title'] : '';
$hvnly_show_description   = ! empty( $hvnly_a['show_description'] );
$hvnly_form_description   = isset( $hvnly_a['form_description'] ) ? (string) $hvnly_a['form_description'] : '';
$hvnly_show_consent       = ! empty( $hvnly_a['show_consent'] );
$hvnly_consent_text       = isset( $hvnly_a['consent_text'] ) ? (string) $hvnly_a['consent_text'] : '';
$hvnly_form_args          = isset( $hvnly_a['form_args'] ) && is_array( $hvnly_a['form_args'] ) ? $hvnly_a['form_args'] : array();
$hvnly_property_choices   = isset( $hvnly_a['property_choices'] ) && is_array( $hvnly_a['property_choices'] ) ? $hvnly_a['property_choices'] : array();
$hvnly_property_header    = isset( $hvnly_a['property_header'] ) && is_array( $hvnly_a['property_header'] ) ? $hvnly_a['property_header'] : array();
$hvnly_show_agent_card    = ! empty( $hvnly_a['show_agent_card'] );
$hvnly_button_text        = isset( $hvnly_a['button_text'] ) ? (string) $hvnly_a['button_text'] : '';
$hvnly_button_icon        = isset( $hvnly_a['button_icon'] ) ? (string) $hvnly_a['button_icon'] : 'none';

if ( empty( $hvnly_form_args['property_id'] ) ) {
	return;
}

$hvnly_property_id = (int) $hvnly_form_args['property_id'];
$hvnly_agents      = isset( $hvnly_form_args['agents'] ) && is_array( $hvnly_form_args['agents'] ) ? $hvnly_form_args['agents'] : array();
$hvnly_primary     = ! empty( $hvnly_agents[0] ) && is_array( $hvnly_agents[0] ) ? $hvnly_agents[0] : array();

ob_start();
hvnly_get_template_part( 'single-property/partials/contact-agent-form', null, $hvnly_form_args );
$hvnly_form_html = (string) ob_get_clean();

if ( '' === trim( $hvnly_form_html ) ) {
	return;
}

// Multi-property: replace the hidden property_id with a visitor-facing select.
// Still submits the existing `property_id` field — no backend changes.
if ( count( $hvnly_property_choices ) > 1 ) {
	$hvnly_select_id = 'hvnly-block-inquiry-property-' . $hvnly_property_id;
	$hvnly_options   = '';
	foreach ( $hvnly_property_choices as $hvnly_choice ) {
		$hvnly_cid   = isset( $hvnly_choice['id'] ) ? (int) $hvnly_choice['id'] : 0;
		$hvnly_ctitle = isset( $hvnly_choice['title'] ) ? (string) $hvnly_choice['title'] : '';
		if ( $hvnly_cid <= 0 ) {
			continue;
		}
		$hvnly_options .= sprintf(
			'<option value="%1$d"%2$s>%3$s</option>',
			$hvnly_cid,
			selected( $hvnly_cid, $hvnly_property_id, false ),
			esc_html( $hvnly_ctitle !== '' ? $hvnly_ctitle : ( '#' . $hvnly_cid ) )
		);
	}

	$hvnly_property_select = sprintf(
		'<div class="hvnly-contact-agent__field hvnly-block-inquiry__property-select hvnly-inquiry-form__field">' .
		'<label for="%1$s">%2$s <span class="hvnly-contact-agent__required" aria-hidden="true">*</span></label>' .
		'<select id="%1$s" name="property_id" class="hvnly-contact-agent__select" required>%3$s</select>' .
		'</div>',
		esc_attr( $hvnly_select_id ),
		esc_html__( 'Property', 'havenlytics' ),
		$hvnly_options
	);

	$hvnly_form_html = preg_replace(
		'/<input\s+type="hidden"\s+name="property_id"[^>]*>/i',
		$hvnly_property_select,
		$hvnly_form_html,
		1
	);
}

// Optional consent — existing privacy_* selectors in Contact Agent JS.
if ( $hvnly_show_consent && '' !== trim( $hvnly_consent_text ) ) {
	$hvnly_consent_markup = sprintf(
		'<div class="hvnly-contact-agent__field hvnly-block-inquiry__consent hvnly-inquiry-form__field">' .
		'<label class="hvnly-block-inquiry__consent-label" for="hvnly-block-inquiry-consent-%1$d">' .
		'<input type="checkbox" id="hvnly-block-inquiry-consent-%1$d" name="privacy_consent" value="1" required /> ' .
		'<span>%2$s</span>' .
		'</label>' .
		'</div>',
		$hvnly_property_id,
		esc_html( $hvnly_consent_text )
	);

	$hvnly_needle = '<div class="hvnly-contact-agent__feedback';
	if ( false !== strpos( $hvnly_form_html, $hvnly_needle ) ) {
		$hvnly_form_html = str_replace( $hvnly_needle, $hvnly_consent_markup . $hvnly_needle, $hvnly_form_html );
	}
}

// Existing `source` POST field (no new server fields).
$hvnly_source_input = '<input type="hidden" name="source" value="property_inquiry_block" />';
if ( false === strpos( $hvnly_form_html, 'name="source"' ) ) {
	if ( false !== strpos( $hvnly_form_html, 'name="property_id"' ) ) {
		$hvnly_form_html = preg_replace(
			'/(name="property_id"[^>]*>)/i',
			'$1' . $hvnly_source_input,
			$hvnly_form_html,
			1
		);
	} else {
		$hvnly_form_html = preg_replace( '/<form([^>]*)>/i', '<form$1>' . $hvnly_source_input, $hvnly_form_html, 1 );
	}
}

// Optional custom submit label (still the existing submit button / flow).
if ( '' !== trim( $hvnly_button_text ) ) {
	$hvnly_form_html = preg_replace(
		'/(<button[^>]*class="[^"]*js-hvnly-contact-agent-submit[^"]*"[^>]*>)(.*?)(<\/button>)/is',
		'$1' . esc_html( $hvnly_button_text ) . '$3',
		$hvnly_form_html,
		1
	);
}

// Optional decorative icon before submit label (presentation only — Font Awesome).
$hvnly_icon_html = '';
if ( 'send' === $hvnly_button_icon ) {
	$hvnly_icon_html = '<span class="hvnly-block-inquiry__btn-icon" aria-hidden="true"><i class="fas fa-paper-plane"></i></span> ';
} elseif ( 'envelope' === $hvnly_button_icon ) {
	$hvnly_icon_html = '<span class="hvnly-block-inquiry__btn-icon" aria-hidden="true"><i class="fas fa-envelope"></i></span> ';
} elseif ( 'check' === $hvnly_button_icon ) {
	$hvnly_icon_html = '<span class="hvnly-block-inquiry__btn-icon" aria-hidden="true"><i class="fas fa-check"></i></span> ';
}
if ( '' !== $hvnly_icon_html ) {
	$hvnly_form_html = preg_replace(
		'/(<button[^>]*class="[^"]*js-hvnly-contact-agent-submit[^"]*"[^>]*>)/i',
		'$1' . $hvnly_icon_html,
		$hvnly_form_html,
		1
	);
}
?>
<div <?php echo $hvnly_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built via get_block_wrapper_attributes(). ?>>
	<?php if ( $hvnly_show_title && '' !== trim( $hvnly_form_title ) ) : ?>
		<h2 class="hvnly-block-inquiry__title"><?php echo esc_html( $hvnly_form_title ); ?></h2>
	<?php endif; ?>

	<?php if ( $hvnly_show_description && '' !== trim( $hvnly_form_description ) ) : ?>
		<p class="hvnly-block-inquiry__description"><?php echo esc_html( $hvnly_form_description ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $hvnly_property_header['show'] ) ) : ?>
		<div class="hvnly-block-inquiry__property">
			<?php if ( ! empty( $hvnly_property_header['image'] ) ) : ?>
				<div class="hvnly-block-inquiry__property-media">
					<img
						src="<?php echo esc_url( (string) $hvnly_property_header['image'] ); ?>"
						alt="<?php echo esc_attr( (string) ( $hvnly_property_header['title'] ?? '' ) ); ?>"
						loading="lazy"
						decoding="async"
					/>
				</div>
			<?php endif; ?>
			<div class="hvnly-block-inquiry__property-meta">
				<?php if ( ! empty( $hvnly_property_header['title'] ) ) : ?>
					<p class="hvnly-block-inquiry__property-title">
						<?php if ( ! empty( $hvnly_property_header['url'] ) ) : ?>
							<a href="<?php echo esc_url( (string) $hvnly_property_header['url'] ); ?>">
								<?php echo esc_html( (string) $hvnly_property_header['title'] ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( (string) $hvnly_property_header['title'] ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $hvnly_property_header['price'] ) ) : ?>
					<p class="hvnly-block-inquiry__property-price"><?php echo wp_kses_post( (string) $hvnly_property_header['price'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $hvnly_property_header['address'] ) ) : ?>
					<p class="hvnly-block-inquiry__property-address"><?php echo esc_html( (string) $hvnly_property_header['address'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $hvnly_show_agent_card && ! empty( $hvnly_primary ) ) : ?>
		<div class="hvnly-block-inquiry__agent">
			<?php
			hvnly_get_template_part(
				'partials/cards/agent-card',
				null,
				array(
					'agent'       => $hvnly_primary,
					'context'     => 'property',
					'property_id' => $hvnly_property_id,
					'is_primary'  => true,
					'instance'    => array(
						'show_phone'        => true,
						'show_email'        => true,
						'show_profile_link' => true,
					),
				)
			);
			?>
		</div>
	<?php endif; ?>

	<div class="hvnly-block-inquiry__form">
		<?php echo $hvnly_form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical form partial. ?>
	</div>
</div>
