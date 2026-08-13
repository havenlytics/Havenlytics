<?php
/**
 * Layered SSRF protection for remote CSV media URLs.
 *
 * @package HvnlyNab\CsvTransfer\Security
 * @since   3.7.1
 */

namespace HvnlyNab\CsvTransfer\Security;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * UrlGuard — scheme / host / resolved-IP checks before outbound fetches.
 *
 * Complements WordPress {@see wp_http_validate_url()} / {@see wp_safe_remote_get()}.
 *
 * @since 3.7.1
 */
final class UrlGuard {

	/** Max HTTP redirects when downloading remote media. */
	public const MAX_REDIRECTS = 3;

	/**
	 * Hostnames that must never be fetched (cloud metadata / loopback aliases).
	 *
	 * @var array<int, string>
	 */
	private const BLOCKED_HOSTS = array(
		'localhost',
		'localhost.localdomain',
		'metadata',
		'metadata.google.internal',
		'metadata.goog',
		'instance-data',
	);

	/**
	 * Whether this WordPress install is running in local development mode.
	 *
	 * Local mode alone does not weaken production SSRF rules — callers must
	 * still use {@see self::is_allowed_local_dev_host()} for host exceptions.
	 *
	 * @return bool
	 */
	public static function is_local_environment(): bool {
		if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
			return true;
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) {
			return true;
		}

		$app_env = getenv( 'APP_ENV' );
		if ( is_string( $app_env ) && 'local' === strtolower( trim( $app_env ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Hostnames allowed for outbound CSV media fetches only in local development.
	 *
	 * Allowed: localhost, 127.0.0.1, ::1, and *.local
	 * Never allowed here: *.internal, cloud metadata hosts.
	 *
	 * @param string $host Hostname (lowercase, brackets stripped).
	 * @return bool
	 */
	public static function is_allowed_local_dev_host( string $host ): bool {
		if ( ! self::is_local_environment() ) {
			return false;
		}

		$host = strtolower( trim( $host ) );
		if ( '' === $host ) {
			return false;
		}

		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}

		// Explicit *.local only — not *.localhost / *.internal.
		return self::ends_with( $host, '.local' );
	}

	/**
	 * Validate that a URL is safe to fetch from this server.
	 *
	 * @param string $url Candidate URL.
	 * @return true|WP_Error
	 */
	public static function assert_public_http_url( string $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return new WP_Error( 'hvnly_csv_ssrf_empty', __( 'Media URL is empty.', 'havenlytics' ) );
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_parse', __( 'Could not parse media URL.', 'havenlytics' ) );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_scheme', __( 'Only http and https media URLs are allowed.', 'havenlytics' ) );
		}

		$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		if ( '' === $host ) {
			return new WP_Error( 'hvnly_csv_ssrf_host', __( 'Media URL is missing a hostname.', 'havenlytics' ) );
		}

		// Strip IPv6 brackets for comparisons.
		$host_cmp = $host;
		if ( '[' === $host_cmp[0] && ']' === substr( $host_cmp, -1 ) ) {
			$host_cmp = substr( $host_cmp, 1, -1 );
		}

		// Local development only: allow loopback / *.local before WordPress's
		// wp_http_validate_url() (which rejects private/loopback hosts).
		if ( self::is_allowed_local_dev_host( $host_cmp ) ) {
			return true;
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_invalid', __( 'Invalid media URL.', 'havenlytics' ) );
		}

		if ( in_array( $host_cmp, self::BLOCKED_HOSTS, true ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_blocked_host', __( 'Media URL host is not allowed.', 'havenlytics' ) );
		}

		if ( self::ends_with( $host_cmp, '.localhost' ) || self::ends_with( $host_cmp, '.local' ) || self::ends_with( $host_cmp, '.internal' ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_blocked_tld', __( 'Media URL host is not allowed.', 'havenlytics' ) );
		}

		// Literal IP in the hostname.
		if ( filter_var( $host_cmp, FILTER_VALIDATE_IP ) ) {
			if ( self::is_disallowed_ip( $host_cmp ) ) {
				return new WP_Error( 'hvnly_csv_ssrf_private_ip', __( 'Media URL resolves to a private or reserved address.', 'havenlytics' ) );
			}
			return true;
		}

		$ips = self::resolve_host_ips( $host_cmp );
		if ( empty( $ips ) ) {
			return new WP_Error( 'hvnly_csv_ssrf_dns', __( 'Could not resolve media URL hostname.', 'havenlytics' ) );
		}

		foreach ( $ips as $ip ) {
			if ( self::is_disallowed_ip( $ip ) ) {
				return new WP_Error( 'hvnly_csv_ssrf_private_ip', __( 'Media URL resolves to a private or reserved address.', 'havenlytics' ) );
			}
		}

		return true;
	}

	/**
	 * HTTP request args for outbound media downloads (redirect cap).
	 *
	 * @param array<string, mixed> $args Existing args.
	 * @return array<string, mixed>
	 */
	public static function safe_request_args( array $args = array() ): array {
		$args['timeout']     = isset( $args['timeout'] ) ? min( 30, (int) $args['timeout'] ) : 30;
		$args['redirection'] = self::MAX_REDIRECTS;
		$args['user-agent']  = isset( $args['user-agent'] ) ? $args['user-agent'] : 'Havenlytics-CSV-Media/1.0; ' . home_url( '/' );
		return $args;
	}

	/**
	 * Whether an IP address is private, loopback, link-local, multicast, or reserved.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return bool
	 */
	public static function is_disallowed_ip( string $ip ): bool {
		$ip = strtolower( trim( $ip ) );
		if ( '' === $ip ) {
			return true;
		}

		// Explicit cloud metadata / link-local gateway.
		if ( '169.254.169.254' === $ip || '::1' === $ip || '0.0.0.0' === $ip ) {
			return true;
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			// FILTER_FLAG_NO_PRIV_RANGE | NO_RES_RANGE covers RFC1918, loopback, link-local, etc.
			$public = filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
			if ( false === $public ) {
				return true;
			}
			// Carrier-grade NAT / documentation / benchmark ranges sometimes missed.
			if ( self::ipv4_in_cidr( $ip, '100.64.0.0/10' ) || self::ipv4_in_cidr( $ip, '192.0.0.0/24' ) ) {
				return true;
			}
			return false;
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$public = filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
			if ( false === $public ) {
				return true;
			}
			// Unique local addresses (fc00::/7) and link-local (fe80::/10).
			if ( 0 === strpos( $ip, 'fc' ) || 0 === strpos( $ip, 'fd' ) || 0 === strpos( $ip, 'fe80' ) ) {
				return true;
			}
			return false;
		}

		return true;
	}

	/**
	 * @param string $host Hostname.
	 * @return array<int, string>
	 */
	private static function resolve_host_ips( string $host ): array {
		$ips = array();

		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A + DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ip'] ) ) {
						$ips[] = (string) $record['ip'];
					}
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = (string) $record['ipv6'];
					}
				}
			}
		}

		if ( empty( $ips ) ) {
			$a = gethostbynamel( $host );
			if ( is_array( $a ) ) {
				$ips = array_merge( $ips, $a );
			}
		}

		return array_values( array_unique( array_filter( array_map( 'strval', $ips ) ) ) );
	}

	/**
	 * @param string $ip IPv4.
	 * @param string $cidr CIDR notation.
	 * @return bool
	 */
	private static function ipv4_in_cidr( string $ip, string $cidr ): bool {
		if ( ! preg_match( '/^(\d+\.\d+\.\d+\.\d+)\/(\d+)$/', $cidr, $m ) ) {
			return false;
		}
		$mask = (int) $m[2];
		if ( $mask < 0 || $mask > 32 ) {
			return false;
		}
		$ip_long  = ip2long( $ip );
		$net_long = ip2long( $m[1] );
		if ( false === $ip_long || false === $net_long ) {
			return false;
		}
		$mask_long = $mask === 0 ? 0 : ( ~0 << ( 32 - $mask ) );
		return ( $ip_long & $mask_long ) === ( $net_long & $mask_long );
	}

	/**
	 * @param string $value Haystack.
	 * @param string $suffix Needle.
	 * @return bool
	 */
	private static function ends_with( string $value, string $suffix ): bool {
		$len = strlen( $suffix );
		if ( 0 === $len ) {
			return true;
		}
		return substr( $value, -$len ) === $suffix;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
