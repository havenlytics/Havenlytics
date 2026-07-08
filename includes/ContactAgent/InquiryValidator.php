<?php
/**
 * Inquiry input validator (Phase 3).
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and sanitizes contact agent inquiry payloads.
 *
 * @since 3.0.2
 */
class InquiryValidator {

	/** @var int */
	private const NAME_MIN_LENGTH = 2;

	/** @var int */
	private const NAME_MAX_LENGTH = 100;

	/** @var int */
	private const EMAIL_MAX_LENGTH = 100;

	/** @var int */
	private const PHONE_MAX_LENGTH = 30;

	/** @var int */
	private const MESSAGE_MIN_LENGTH = 10;

	/** @var int */
	private const MESSAGE_MAX_LENGTH = 5000;

	/**
	 * Validate raw request payload and return sanitized fields.
	 *
	 * @param array<string, mixed> $payload Raw input.
	 * @return array<string, mixed>|\WP_Error Sanitized payload or error.
	 */
	public function validate( array $payload ) {
		$property_id        = isset( $payload['property_id'] ) ? absint( $payload['property_id'] ) : 0;
		$requested_agent_id = isset( $payload['agent_id'] ) ? absint( $payload['agent_id'] ) : 0;
		$source             = isset( $payload['source'] ) ? sanitize_key( (string) $payload['source'] ) : '';
		$is_agent_profile   = ContactAgentConstants::SOURCE_AGENT_PROFILE === $source;

		if ( $property_id <= 0 ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_property',
				__( 'Please select a valid property.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$property = get_post( $property_id );
		if (
			! $property
			|| 'hvnly_property' !== $property->post_type
			|| 'publish' !== $property->post_status
			|| ! empty( $property->post_password )
		) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_property',
				__( 'This property is not available for inquiries.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( $is_agent_profile ) {
			if (
				$requested_agent_id <= 0
				|| ! function_exists( 'hvnly_is_valid_agent' )
				|| ! hvnly_is_valid_agent( $requested_agent_id )
			) {
				return new \WP_Error(
					'hvnly_contact_agent_invalid_agent',
					__( 'This agent is not available for inquiries.', 'havenlytics' ),
					array( 'status' => 400 )
				);
			}

			if ( class_exists( '\HvnlyNab\Agent\AgentPropertiesQuery' ) ) {
				$assigned_ids = \HvnlyNab\Agent\AgentPropertiesQuery::get_assigned_property_ids( $requested_agent_id );
				if ( empty( $assigned_ids ) || ! in_array( $property_id, array_map( 'absint', $assigned_ids ), true ) ) {
					return new \WP_Error(
						'hvnly_contact_agent_invalid_property',
						__( 'This property is not available for this agent.', 'havenlytics' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		$name = isset( $payload['sender_name'] ) ? sanitize_text_field( (string) $payload['sender_name'] ) : '';
		$name = trim( $name );

		if ( strlen( $name ) < self::NAME_MIN_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_name',
				__( 'Please enter your name.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $name ) > self::NAME_MAX_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_name',
				__( 'Your name is too long.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$email = isset( $payload['sender_email'] ) ? sanitize_email( (string) $payload['sender_email'] ) : '';
		if ( ! is_email( $email ) || strlen( $email ) > self::EMAIL_MAX_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_email',
				__( 'Please enter a valid email address.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$phone = isset( $payload['sender_phone'] ) ? sanitize_text_field( (string) $payload['sender_phone'] ) : '';
		$phone = trim( $phone );
		if ( strlen( $phone ) > self::PHONE_MAX_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_phone',
				__( 'Your phone number is too long.', 'havenlytics' ),
				array( 'status'  => 400 )
			);
		}

		$message = isset( $payload['message'] ) ? sanitize_textarea_field( (string) $payload['message'] ) : '';
		$message = trim( $message );

		if ( strlen( $message ) < self::MESSAGE_MIN_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_message',
				__( 'Please enter a longer message.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $message ) > self::MESSAGE_MAX_LENGTH ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_message',
				__( 'Your message is too long.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$requested_agent_id = isset( $payload['agent_id'] ) ? absint( $payload['agent_id'] ) : 0;

		if ( $is_agent_profile ) {
			$agent_resolution = InquiryAgentResolver::resolve_for_agent_profile( $requested_agent_id );
		} else {
			$agent_resolution = InquiryAgentResolver::resolve_for_submission( $property_id, $requested_agent_id );
		}

		if ( is_wp_error( $agent_resolution ) ) {
			return $agent_resolution;
		}

		$resolved_agent_id = isset( $agent_resolution['agent_id'] ) ? absint( $agent_resolution['agent_id'] ) : 0;
		$resolved_type     = isset( $agent_resolution['agent_type'] ) ? sanitize_key( (string) $agent_resolution['agent_type'] ) : InquiryAgentResolver::TYPE_USER;

		if ( $resolved_agent_id > 0 && InquiryAgentResolver::TYPE_CPT === $resolved_type && function_exists( 'hvnly_agent_accepts_inquiries' ) ) {
			if ( ! hvnly_agent_accepts_inquiries( $resolved_agent_id ) ) {
				return new \WP_Error(
					'hvnly_contact_agent_agent_offline',
					function_exists( 'hvnly_get_agent_availability_notice' )
						? hvnly_get_agent_availability_notice( 'offline' )
						: __( 'This agent is not accepting inquiries at this time.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
		}

		$sanitized = array(
			'property_id'  => $property_id,
			'agent_id'     => isset( $agent_resolution['agent_id'] ) ? absint( $agent_resolution['agent_id'] ) : 0,
			'agent_type'   => isset( $agent_resolution['agent_type'] ) ? sanitize_key( (string) $agent_resolution['agent_type'] ) : InquiryAgentResolver::TYPE_USER,
			'sender_name'  => $name,
			'sender_email' => $email,
			'sender_phone' => $phone,
			'message'      => $message,
			'source'       => $is_agent_profile ? ContactAgentConstants::SOURCE_AGENT_PROFILE : ( $source ? $source : ContactAgentConstants::DEFAULT_SOURCE ),
		);

		/**
		 * Filter sanitized inquiry payload after validation.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $sanitized Validated payload.
		 * @param array<string, mixed> $payload   Raw input.
		 */
		return apply_filters( 'hvnly_contact_agent_validated_payload', $sanitized, $payload );
	}
}
