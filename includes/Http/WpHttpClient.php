<?php
/**
 * WordPress HTTP client (wp_remote_*).
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Default HTTP client backed by WordPress HTTP API.
 */
class WpHttpClient implements ClientInterface {

	/**
	 * {@inheritdoc}
	 */
	public function request( string $method, string $uri, array $options = array() ): array {
		$headers = isset( $options['headers'] ) && is_array( $options['headers'] ) ? $options['headers'] : array();
		$body    = $options['body'] ?? null;
		$timeout = isset( $options['timeout'] ) ? (float) $options['timeout'] : 30.0;

		if ( ! empty( $options['query'] ) && is_array( $options['query'] ) ) {
			$uri = add_query_arg( $options['query'], $uri );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( null !== $body ) {
			$args['body'] = is_array( $body ) ? wp_json_encode( $body ) : (string) $body;

			if ( is_array( $body ) && empty( $headers['Content-Type'] ) && empty( $headers['content-type'] ) ) {
				$args['headers']['Content-Type'] = 'application/json';
			}
		}

		$response = wp_remote_request( $uri, $args );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message from wp_remote_request */
					__( 'HTTP request failed: %s', 'wc-payment-gateway-boilerplate' ),
					$response->get_error_message()
				)
			);
		}

		return array(
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => wp_remote_retrieve_headers( $response )->getAll(),
			'body'    => (string) wp_remote_retrieve_body( $response ),
		);
	}
}
