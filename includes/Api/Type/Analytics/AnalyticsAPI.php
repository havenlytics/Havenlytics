<?php
/**
 * Analytics REST API endpoints.
 *
 * @package HvnlyNab\Api\Type\Analytics
 * @since   3.1.6
 */

namespace HvnlyNab\Api\Type\Analytics;

use HvnlyNab\Analytics\AnalyticsDateRange;
use HvnlyNab\Analytics\AnalyticsService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for the Analytics dashboard.
 *
 * @since 3.1.6
 */
class AnalyticsAPI {

	/** @var string */
	private $namespace = 'hvnlynab/v1';

	/** @var string */
	private $route_base = 'analytics';

	/**
	 * Register REST routes.
	 */
	public function routes(): void {
		$range_args = array(
			'range' => array(
				'type'              => 'string',
				'default'           => '30d',
				'sanitize_callback' => 'sanitize_key',
			),
			'date_from' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'get_per' ),
				'args'                => $range_args,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/charts',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_charts' ),
				'permission_callback' => array( $this, 'get_per' ),
				'args'                => $range_args,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/tables',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_tables' ),
				'permission_callback' => array( $this, 'get_per' ),
				'args'                => $range_args,
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_export' ),
				'permission_callback' => array( $this, 'get_per' ),
				'args'                => array_merge(
					$range_args,
					array(
						'type' => array(
							'type'              => 'string',
							'default'           => 'properties',
							'sanitize_callback' => 'sanitize_key',
						),
					)
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_overview( WP_REST_Request $request ): WP_REST_Response {
		$service = $this->make_service( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $service->get_overview(),
				'range'   => $this->range_payload( $request ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_charts( WP_REST_Request $request ): WP_REST_Response {
		$service = $this->make_service( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $service->get_charts(),
				'range'   => $this->range_payload( $request ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_tables( WP_REST_Request $request ): WP_REST_Response {
		$service = $this->make_service( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $service->get_tables(),
				'range'   => $this->range_payload( $request ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_export( WP_REST_Request $request ): WP_REST_Response {
		$service = $this->make_service( $request );
		$type    = (string) $request->get_param( 'type' );
		$export  = $service->get_export( $type );

		return rest_ensure_response(
			array(
				'success'  => true,
				'filename' => $export['filename'],
				'headers'  => $export['headers'],
				'rows'     => $export['rows'],
			)
		);
	}

	/**
	 * @return bool
	 */
	public function get_per(): bool {
		$capability = apply_filters( 'hvnly_admin_capability', 'manage_options' );
		return current_user_can( $capability );
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return AnalyticsService
	 */
	private function make_service( WP_REST_Request $request ): AnalyticsService {
		$range = new AnalyticsDateRange(
			(string) $request->get_param( 'range' ),
			(string) $request->get_param( 'date_from' ),
			(string) $request->get_param( 'date_to' )
		);

		return new AnalyticsService( $range );
	}

	/**
	 * @param WP_REST_Request $request Request object.
	 * @return array<string, string>
	 */
	private function range_payload( WP_REST_Request $request ): array {
		$range = new AnalyticsDateRange(
			(string) $request->get_param( 'range' ),
			(string) $request->get_param( 'date_from' ),
			(string) $request->get_param( 'date_to' )
		);

		return array(
			'key'   => $range->get_key(),
			'start' => $range->get_start(),
			'end'   => $range->get_end(),
		);
	}
}
