<?php
/**
 * WooCommerce wc-api webhook endpoint.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Webhook;

use WCGatewayBoilerplate\Gateway\AbstractGateway;
use WCGatewayBoilerplate\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookHandler — verifies signature, parses event, reconciles via PaymentService.
 *
 * Endpoint: /?wc-api=wc_gateway_boilerplate
 */
class WebhookHandler {

	/**
	 * Register the WooCommerce API action.
	 *
	 * @return void
	 */
	public function register() {
		add_action(
			'woocommerce_api_' . AbstractGateway::GATEWAY_ID,
			array( $this, 'handle' )
		);
	}

	/**
	 * Handle incoming webhook request.
	 *
	 * @return void
	 */
	public function handle() {
		$plugin  = Plugin::instance();
		$logger  = $plugin->get_logger();
		$service = $plugin->get_payment_service();
		$provider = $plugin->get_provider();

		$raw_body = $this->get_raw_body();
		$headers  = $this->get_request_headers();

		$logger->info(
			'Webhook received',
			array(
				'content_length' => strlen( $raw_body ),
			)
		);

		if ( ! $provider->verify_webhook_signature( $headers, $raw_body ) ) {
			$logger->error( 'Webhook signature verification failed' );
			$this->respond( 401, array( 'error' => 'invalid_signature' ) );
		}

		try {
			$event = $provider->parse_webhook( $headers, $raw_body );
		} catch ( \Throwable $e ) {
			$logger->error(
				'Webhook parse failed',
				array( 'message' => $e->getMessage() )
			);
			$this->respond( 400, array( 'error' => 'invalid_payload' ) );
		}

		$order = null;
		if ( $event->get_order_id() ) {
			$order = wc_get_order( (int) $event->get_order_id() );
		}

		if ( ! $order ) {
			$logger->error(
				'Webhook order not found',
				array(
					'order_id'            => $event->get_order_id(),
					'provider_payment_id' => $event->get_provider_payment_id(),
				)
			);
			$this->respond( 404, array( 'error' => 'order_not_found' ) );
		}

		try {
			$outcome = $service->reconcile( $event, $order );
		} catch ( \Throwable $e ) {
			$logger->error(
				'Webhook reconcile failed',
				array( 'message' => $e->getMessage() )
			);
			$this->respond( 500, array( 'error' => 'reconcile_failed' ) );
		}

		$this->respond(
			200,
			array(
				'ok'         => true,
				'handled'    => (bool) $outcome['handled'],
				'reason'     => $outcome['reason'],
				'woo_status' => $outcome['woo_status'],
				'event_id'   => $event->get_event_id(),
			)
		);
	}

	/**
	 * Public webhook URL for this site.
	 *
	 * @return string
	 */
	public static function get_url(): string {
		return home_url( '/?wc-api=' . AbstractGateway::GATEWAY_ID );
	}

	/**
	 * @return string
	 */
	private function get_raw_body(): string {
		$raw = file_get_contents( 'php://input' );
		return false !== $raw ? $raw : '';
	}

	/**
	 * Normalize request headers to a string map.
	 *
	 * @return array<string, string>
	 */
	private function get_request_headers(): array {
		$headers = array();

		if ( function_exists( 'getallheaders' ) ) {
			$all = getallheaders();
			if ( is_array( $all ) ) {
				foreach ( $all as $key => $value ) {
					$headers[ (string) $key ] = (string) $value;
				}
			}
		}

		foreach ( $_SERVER as $key => $value ) {
			if ( 0 === strpos( $key, 'HTTP_' ) ) {
				$name             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
				$headers[ $name ] = (string) $value;
			}
		}

		return $headers;
	}

	/**
	 * Send JSON response and exit.
	 *
	 * @param int                  $status HTTP status.
	 * @param array<string, mixed> $body   Payload.
	 * @return void
	 */
	private function respond( int $status, array $body ): void {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $body );
		exit;
	}
}
