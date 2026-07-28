<?php
/**
 * Lightweight logger wrapper.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Logger — uses WC_Logger when available, otherwise error_log.
 *
 * Never pass raw secrets into context; use scrub() for associative arrays.
 */
class Logger {

	/**
	 * Log source / handle.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Whether logging is enabled.
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Keys that must never appear in logs.
	 *
	 * @var string[]
	 */
	private static $sensitive_keys = array(
		'api_key',
		'apikey',
		'secret',
		'webhook_secret',
		'password',
		'token',
		'authorization',
		'card_number',
		'cvv',
		'cvc',
		'pan',
	);

	/**
	 * @param string $source  Log source handle.
	 * @param bool   $enabled Whether to write logs.
	 */
	public function __construct( string $source = 'wc-gateway-boilerplate', bool $enabled = true ) {
		$this->source  = $source;
		$this->enabled = $enabled;
	}

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Optional context (will be scrubbed).
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Optional context (will be scrubbed).
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Optional context (will be scrubbed).
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Remove sensitive values from a context array.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public static function scrub( array $context ): array {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$key_str = strtolower( (string) $key );

			foreach ( self::$sensitive_keys as $sensitive ) {
				if ( false !== strpos( $key_str, $sensitive ) ) {
					$clean[ $key ] = '[redacted]';
					continue 2;
				}
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = self::scrub( $value );
			} else {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * @param string               $level   Log level.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! $this->enabled ) {
			return;
		}

		$context = self::scrub( $context );
		$line    = $message;

		if ( ! empty( $context ) ) {
			if ( function_exists( 'wp_json_encode' ) ) {
				$encoded = wp_json_encode( $context );
			} else {
				$encoded = json_encode( $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			}

			$line .= ' ' . ( false !== $encoded ? $encoded : '' );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $line, array( 'source' => $this->source ) );
			return;
		}

		// Fallback outside Woo (CLI smoke / early boot).
		error_log( sprintf( '[%s][%s] %s', $this->source, $level, $line ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
