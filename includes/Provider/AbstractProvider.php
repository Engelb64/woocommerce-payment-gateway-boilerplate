<?php
/**
 * Base provider helpers.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Provider;

use WCGatewayBoilerplate\Http\ClientInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Optional base class for real providers (HTTP client + shared helpers).
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * @var ClientInterface|null
	 */
	protected $http;

	/**
	 * @var array<string, mixed>
	 */
	protected $config;

	/**
	 * @param ClientInterface|null $http   Optional HTTP client.
	 * @param array<string, mixed> $config Provider configuration (keys, sandbox, secrets).
	 */
	public function __construct( ?ClientInterface $http = null, array $config = array() ) {
		$this->http   = $http;
		$this->config = $config;
	}

	/**
	 * Read a config value.
	 *
	 * @param string $key     Config key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_config( string $key, $default = null ) {
		return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
	}

	/**
	 * Decode JSON body safely.
	 *
	 * @param string $body Raw body.
	 * @return array<string, mixed>
	 */
	protected function decode_json( string $body ): array {
		if ( '' === $body ) {
			return array();
		}

		$decoded = json_decode( $body, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Constant-time string comparison helper.
	 *
	 * @param string $known Known value.
	 * @param string $given Provided value.
	 * @return bool
	 */
	protected function hash_equals_safe( string $known, string $given ): bool {
		if ( function_exists( 'hash_equals' ) ) {
			return hash_equals( $known, $given );
		}

		return $known === $given;
	}
}
