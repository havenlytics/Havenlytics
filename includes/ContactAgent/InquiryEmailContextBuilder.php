<?php
/**
 * Builds shared email context and merge-tag replacements for Contact Agent mail.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Central context builder for all Contact Agent transactional emails.
 *
 * @since 3.0.2
 */
final class InquiryEmailContextBuilder {

	/**
	 * Build template context for any Contact Agent email type.
	 *
	 * @param int                  $inquiry_id Inquiry ID.
	 * @param array<string, mixed> $inquiry    Inquiry row.
	 * @param array<string, mixed> $agent      Agent profile.
	 * @return array<string, mixed>
	 */
	public static function build( int $inquiry_id, array $inquiry, array $agent ): array {
		$property_id = isset( $inquiry['property_id'] ) ? absint( $inquiry['property_id'] ) : 0;
		$agent       = is_array( $agent ) ? $agent : array();

		$context = array(
			'inquiry_id'     => $inquiry_id,
			'inquiry'        => $inquiry,
			'agent'          => $agent,
			'property_id'    => $property_id,
			'property_id_display' => $property_id > 0 ? (string) $property_id : '',
			'property_title' => $property_id > 0 ? get_the_title( $property_id ) : '',
			'property_url'   => $property_id > 0 ? get_permalink( $property_id ) : '',
			'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'       => home_url( '/' ),
			'sender_name'    => isset( $inquiry['sender_name'] ) ? sanitize_text_field( (string) $inquiry['sender_name'] ) : '',
			'sender_email'   => isset( $inquiry['sender_email'] ) ? sanitize_email( (string) $inquiry['sender_email'] ) : '',
			'sender_phone'   => isset( $inquiry['sender_phone'] ) ? sanitize_text_field( (string) $inquiry['sender_phone'] ) : '',
			'message'        => isset( $inquiry['message'] ) ? sanitize_textarea_field( (string) $inquiry['message'] ) : '',
			'agent_name'     => isset( $agent['name'] ) ? sanitize_text_field( (string) $agent['name'] ) : '',
			'agent_email'    => isset( $agent['email'] ) ? sanitize_email( (string) $agent['email'] ) : '',
			'agent_phone'    => isset( $agent['phone'] ) ? sanitize_text_field( (string) $agent['phone'] ) : '',
		);

		if ( '' === trim( $context['agent_name'] ) ) {
			$context['agent_name'] = __( 'our team', 'havenlytics' );
		}

		if ( '' === trim( $context['property_title'] ) ) {
			$context['property_title'] = __( 'your selected listing', 'havenlytics' );
		}

		$context = array_merge( $context, self::property_details( $property_id ) );

		$context['sender_intro'] = self::resolve_sender_intro( $context );

		/**
		 * Filter Contact Agent email context before templates or merge tags run.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $context    Email context.
		 * @param int                  $inquiry_id Inquiry ID.
		 * @param array<string, mixed> $inquiry    Inquiry row.
		 * @param array<string, mixed> $agent      Agent profile.
		 */
		return apply_filters( 'hvnly_contact_agent_email_context', $context, $inquiry_id, $inquiry, $agent );
	}

	/**
	 * Build email context for an admin reply to an inquiry submitter.
	 *
	 * @param int                  $inquiry_id    Inquiry ID.
	 * @param array<string, mixed> $inquiry       Inquiry row.
	 * @param array<string, mixed> $agent         Agent profile.
	 * @param string               $reply_message Reply body.
	 * @param \WP_User             $admin_user    Admin who sent the reply.
	 * @return array<string, mixed>
	 */
	public static function build_for_reply( int $inquiry_id, array $inquiry, array $agent, string $reply_message, \WP_User $admin_user ): array {
		$context = self::build( $inquiry_id, $inquiry, $agent );

		$context['reply_message']     = sanitize_textarea_field( $reply_message );
		$context['admin_name']        = sanitize_text_field( $admin_user->display_name );
		$context['admin_email']       = sanitize_email( $admin_user->user_email );
		$context['original_message']  = (string) ( $context['message'] ?? '' );

		/**
		 * Filter Contact Agent admin reply email context.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $context       Email context.
		 * @param int                  $inquiry_id    Inquiry ID.
		 * @param array<string, mixed> $inquiry       Inquiry row.
		 * @param string               $reply_message Reply body.
		 * @param \WP_User             $admin_user    Admin user.
		 */
		return apply_filters( 'hvnly_contact_agent_reply_email_context', $context, $inquiry_id, $inquiry, $reply_message, $admin_user );
	}

	/**
	 * Replace {{merge_tags}} in a string using email context.
	 *
	 * @param string               $text    Input string.
	 * @param array<string, mixed> $context Email context from build().
	 * @return string
	 */
	public static function replace_merge_tags( string $text, array $context ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$tags = self::merge_tag_map( $context );

		$replaced = preg_replace_callback(
			'/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
			static function ( array $matches ) use ( $tags ) {
				$key = strtolower( $matches[1] );

				return array_key_exists( $key, $tags ) ? (string) $tags[ $key ] : '';
			},
			$text
		);

		return is_string( $replaced ) ? trim( $replaced ) : trim( $text );
	}

	/**
	 * @param array<string, mixed> $context Email context.
	 * @return array<string, string>
	 */
	public static function merge_tag_map( array $context ): array {
		$map = array(
			'sender_name'    => (string) ( $context['sender_name'] ?? '' ),
			'sender_email'   => (string) ( $context['sender_email'] ?? '' ),
			'sender_phone'   => (string) ( $context['sender_phone'] ?? '' ),
			'message'        => (string) ( $context['message'] ?? '' ),
			'agent_name'     => (string) ( $context['agent_name'] ?? '' ),
			'agent_email'    => (string) ( $context['agent_email'] ?? '' ),
			'agent_phone'    => (string) ( $context['agent_phone'] ?? '' ),
			'property_id'    => (string) ( $context['property_id_display'] ?? '' ),
			'property_status' => (string) ( $context['property_status'] ?? '' ),
			'property_type'   => (string) ( $context['property_type'] ?? '' ),
			'property_title' => (string) ( $context['property_title'] ?? '' ),
			'property_url'   => (string) ( $context['property_url'] ?? '' ),
			'property_image' => (string) ( $context['property_image'] ?? '' ),
			'property_price' => (string) ( $context['property_price'] ?? '' ),
			'property_address' => (string) ( $context['property_address'] ?? '' ),
			'property_location' => (string) ( $context['property_location'] ?? '' ),
			'property_bedrooms' => (string) ( $context['property_bedrooms'] ?? '' ),
			'property_bathrooms' => (string) ( $context['property_bathrooms'] ?? '' ),
			'property_area' => (string) ( $context['property_area'] ?? '' ),
			'site_name'      => (string) ( $context['site_name'] ?? '' ),
			'site_url'       => (string) ( $context['site_url'] ?? '' ),
			'inquiry_id'     => isset( $context['inquiry_id'] ) ? (string) absint( $context['inquiry_id'] ) : '',
		);

		/**
		 * Filter merge-tag map used in Contact Agent email subjects and messages.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, string> $map     Tag key => replacement value.
		 * @param array<string, mixed>  $context Email context.
		 */
		return apply_filters( 'hvnly_contact_agent_email_merge_tags', $map, $context );
	}

	/**
	 * Load property listing details for email templates.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<string, string>
	 */
	private static function property_details( int $property_id ): array {
		if ( $property_id <= 0 ) {
			return array(
				'property_image'     => '',
				'property_price'     => '',
				'property_address'   => '',
				'property_location'  => '',
				'property_bedrooms'  => '',
				'property_bathrooms' => '',
				'property_area'      => '',
				'property_status'    => '',
				'property_type'      => '',
			);
		}

		$image_url = get_the_post_thumbnail_url( $property_id, 'medium_large' );
		if ( ! $image_url ) {
			$image_url = (string) apply_filters(
				'hvnly_property_placeholder_url',
				( defined( 'HVNLYNAB_ASSETS_URL' ) ? HVNLYNAB_ASSETS_URL : '' ) . 'images/property-placeholder.jpg',
				'email'
			);
		}

		$price = '';
		if ( function_exists( 'hvnly_get_property_price' ) ) {
			$price = (string) hvnly_get_property_price( $property_id, true );
		} else {
			$raw_price = get_post_meta( $property_id, '_hvnly_property_price', true );
			$price     = is_scalar( $raw_price ) ? (string) $raw_price : '';
		}

		$address = (string) get_post_meta( $property_id, '_hvnly_property_address_line_1', true );
		if ( '' === trim( $address ) ) {
			$address = (string) get_post_meta( $property_id, '_hvnly_property_street', true );
		}

		$location = '';
		$terms    = get_the_terms( $property_id, 'property-location' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$location = implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}

		$status = '';
		$status_terms = get_the_terms( $property_id, 'hvnly_prop_status' );
		if ( $status_terms && ! is_wp_error( $status_terms ) ) {
			$status = implode( ', ', wp_list_pluck( $status_terms, 'name' ) );
		}

		$type = '';
		$type_terms = get_the_terms( $property_id, 'hvnly_prop_types' );
		if ( $type_terms && ! is_wp_error( $type_terms ) ) {
			$type = implode( ', ', wp_list_pluck( $type_terms, 'name' ) );
		}

		$bedrooms  = (string) get_post_meta( $property_id, '_hvnly_property_bedrooms', true );
		$bathrooms = (string) get_post_meta( $property_id, '_hvnly_property_bathrooms', true );
		$area      = (string) get_post_meta( $property_id, '_hvnly_property_sqft', true );

		return array(
			'property_image'     => esc_url( $image_url ),
			'property_price'     => sanitize_text_field( $price ),
			'property_address'   => sanitize_text_field( $address ),
			'property_location'  => sanitize_text_field( $location ),
			'property_bedrooms'  => sanitize_text_field( $bedrooms ),
			'property_bathrooms' => sanitize_text_field( $bathrooms ),
			'property_area'      => sanitize_text_field( $area ),
			'property_status'    => sanitize_text_field( $status ),
			'property_type'      => sanitize_text_field( $type ),
		);
	}

	/**
	 * @param array<string, mixed> $context Email context.
	 * @return string
	 */
	private static function resolve_sender_intro( array $context ): string {
		$custom = '';
		if ( class_exists( ContactAgentSettings::class ) ) {
			$custom = ContactAgentSettings::get_sender_intro();
		}

		if ( '' !== trim( $custom ) ) {
			return self::replace_merge_tags( $custom, $context );
		}

		return sprintf(
			/* translators: 1: property title, 2: agent name */
			__( 'Thank you for your interest in %1$s. %2$s will review your message and contact you as soon as possible.', 'havenlytics' ),
			(string) ( $context['property_title'] ?? '' ),
			(string) ( $context['agent_name'] ?? '' )
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
