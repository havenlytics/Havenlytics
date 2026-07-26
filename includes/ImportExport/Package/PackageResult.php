<?php
/**
 * Structured result for package I/O and validation.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Success/failure carrier — failures are never silent.
 *
 * @since 3.6.0
 */
final class PackageResult {

	/**
	 * @var bool
	 */
	private $ok;

	/**
	 * @var array<int, array{code:string,message:string,context:array<string,mixed>}>
	 */
	private $errors;

	/**
	 * @var array<int, array{code:string,message:string,context:array<string,mixed>}>
	 */
	private $warnings;

	/**
	 * @var mixed
	 */
	private $data;

	/**
	 * @param bool  $ok       Success flag.
	 * @param mixed $data     Payload on success (or partial context).
	 * @param array $errors   Error list.
	 * @param array $warnings Warning list.
	 */
	private function __construct( bool $ok, $data = null, array $errors = array(), array $warnings = array() ) {
		$this->ok       = $ok;
		$this->data     = $data;
		$this->errors   = $errors;
		$this->warnings = $warnings;
	}

	/**
	 * @param mixed $data Payload.
	 * @param array $warnings Optional warnings.
	 * @return self
	 */
	public static function success( $data = null, array $warnings = array() ): self {
		return new self( true, $data, array(), $warnings );
	}

	/**
	 * @param string               $code    Machine code.
	 * @param string               $message Human message.
	 * @param array<string, mixed> $context Extra context.
	 * @return self
	 */
	public static function failure( string $code, string $message, array $context = array() ): self {
		return new self(
			false,
			null,
			array(
				array(
					'code'    => $code,
					'message' => $message,
					'context' => $context,
				),
			)
		);
	}

	/**
	 * @param array $errors Error list.
	 * @param array $warnings Warning list.
	 * @param mixed $data Optional data.
	 * @return self
	 */
	public static function failures( array $errors, array $warnings = array(), $data = null ): self {
		$normalized = array();
		foreach ( $errors as $error ) {
			if ( ! is_array( $error ) || empty( $error['code'] ) || empty( $error['message'] ) ) {
				continue;
			}
			$normalized[] = array(
				'code'    => (string) $error['code'],
				'message' => (string) $error['message'],
				'context' => isset( $error['context'] ) && is_array( $error['context'] ) ? $error['context'] : array(),
			);
		}

		if ( empty( $normalized ) ) {
			$normalized[] = array(
				'code'    => 'hvnly_ie_unknown_error',
				'message' => 'Unknown package error.',
				'context' => array(),
			);
		}

		return new self( false, $data, $normalized, $warnings );
	}

	/**
	 * @return bool
	 */
	public function ok(): bool {
		return $this->ok;
	}

	/**
	 * @return mixed
	 */
	public function data() {
		return $this->data;
	}

	/**
	 * @return array<int, array{code:string,message:string,context:array<string,mixed>}>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * @return array<int, array{code:string,message:string,context:array<string,mixed>}>
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * First error code, or empty string.
	 *
	 * @return string
	 */
	public function first_code(): string {
		return isset( $this->errors[0]['code'] ) ? (string) $this->errors[0]['code'] : '';
	}

	/**
	 * Array form for logging / future AJAX.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'ok'       => $this->ok,
			'errors'   => $this->errors,
			'warnings' => $this->warnings,
			'data'     => $this->data,
		);
	}
}
