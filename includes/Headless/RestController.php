<?php
/**
 * Headless REST routes for payment create / refund / status.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Headless;

use WCGatewayBoilerplate\Plugin;
use WCGatewayBoilerplate\Support\OrderHelper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Thin REST controller — delegates to PaymentService only.
 */
class RestController {

	public const NAMESPACE = 'wc-gateway-boilerplate/v1';

	/**
	 * @var PaymentResponseMapper
	 */
	private $mapper;

	/**
	 * @param PaymentResponseMapper|null $mapper Optional mapper.
	 */
	public function __construct( ?PaymentResponseMapper $mapper = null ) {
		$this->mapper = null !== $mapper ? $mapper : new PaymentResponseMapper();
	}

	/**
	 * Hook rest_api_init.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/payments',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_payment' ),
				'permission_callback' => array( $this, 'can_create_payment' ),
				'args'                => array(
					'order_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'return_url' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'cancel_url' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payments/refund',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'refund_payment' ),
				'permission_callback' => array( $this, 'can_manage_payments' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'amount'   => array(
						'required' => false,
						'type'     => 'number',
					),
					'reason'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/payments/(?P<order_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_payment' ),
				'permission_callback' => array( $this, 'can_view_payment' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * POST /payments — create via PaymentService.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_payment( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->error_response( 'order_not_found', __( 'Order not found.', 'wc-payment-gateway-boilerplate' ), 404 );
		}

		if ( ! $this->user_can_access_order( $order ) ) {
			return $this->error_response( 'forbidden', __( 'You cannot pay for this order.', 'wc-payment-gateway-boilerplate' ), 403 );
		}

		$return_url = (string) $request->get_param( 'return_url' );
		$cancel_url = (string) $request->get_param( 'cancel_url' );

		if ( '' === $return_url && method_exists( $order, 'get_checkout_order_received_url' ) ) {
			$return_url = $order->get_checkout_order_received_url();
		}
		if ( '' === $cancel_url && function_exists( 'wc_get_checkout_url' ) ) {
			$cancel_url = wc_get_checkout_url();
		}

		$extra = array(
			'return_url' => $return_url,
			'cancel_url' => $cancel_url,
		);

		try {
			$outcome = Plugin::instance()->get_payment_service()->create( $extra, $order );
		} catch ( \Throwable $e ) {
			Plugin::instance()->get_logger()->error(
				'headless create_payment exception',
				array( 'message' => $e->getMessage() )
			);
			return $this->error_response( 'payment_error', __( 'Payment processing error.', 'wc-payment-gateway-boilerplate' ), 500 );
		}

		$payload = $this->mapper->from_outcome( $outcome );
		$status  = ! empty( $payload['success'] ) ? 200 : 422;

		return new WP_REST_Response( $payload, $status );
	}

	/**
	 * POST /payments/refund.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function refund_payment( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->error_response( 'order_not_found', __( 'Order not found.', 'wc-payment-gateway-boilerplate' ), 404 );
		}

		$amount = $request->get_param( 'amount' );
		$amount = null !== $amount && '' !== $amount ? (float) $amount : (float) $order->get_total();

		if ( $amount <= 0 ) {
			return $this->error_response( 'invalid_amount', __( 'Invalid refund amount.', 'wc-payment-gateway-boilerplate' ), 400 );
		}

		try {
			$outcome = Plugin::instance()->get_payment_service()->refund(
				'',
				$amount,
				$order->get_currency(),
				$order
			);
		} catch ( \Throwable $e ) {
			Plugin::instance()->get_logger()->error(
				'headless refund exception',
				array( 'message' => $e->getMessage() )
			);
			return $this->error_response( 'refund_error', __( 'Refund processing error.', 'wc-payment-gateway-boilerplate' ), 500 );
		}

		$payload = $this->mapper->from_outcome( $outcome );
		$status  = ! empty( $payload['success'] ) ? 200 : 422;

		$reason = (string) $request->get_param( 'reason' );
		if ( $reason && ! empty( $payload['success'] ) && method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: refund reason */
					__( 'Headless refund reason: %s', 'wc-payment-gateway-boilerplate' ),
					$reason
				)
			);
		}

		return new WP_REST_Response( $payload, $status );
	}

	/**
	 * GET /payments/{order_id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_payment( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->error_response( 'order_not_found', __( 'Order not found.', 'wc-payment-gateway-boilerplate' ), 404 );
		}

		if ( ! $this->user_can_access_order( $order ) ) {
			return $this->error_response( 'forbidden', __( 'You cannot view this order payment.', 'wc-payment-gateway-boilerplate' ), 403 );
		}

		$helper  = new OrderHelper();
		$payload = $this->mapper->from_order_meta(
			$order_id,
			$helper->get_provider_payment_id( $order ),
			(string) $order->get_meta( OrderHelper::META_PROVIDER_STATUS, true ),
			method_exists( $order, 'get_status' ) ? (string) $order->get_status() : ''
		);

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Permission: create payment for an order the user can access.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_create_payment( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'wc_gateway_boilerplate_rest_unauthorized',
				__( 'Authentication required.', 'wc-payment-gateway-boilerplate' ),
				array( 'status' => 401 )
			);
		}

		$order_id = (int) $request->get_param( 'order_id' );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			// Let the callback return 404 with a consistent body.
			return true;
		}

		return $this->user_can_access_order( $order );
	}

	/**
	 * Permission: view payment status.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function can_view_payment( WP_REST_Request $request ) {
		return $this->can_create_payment( $request );
	}

	/**
	 * Permission: refund / manage.
	 *
	 * @return bool
	 */
	public function can_manage_payments() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' );
	}

	/**
	 * Whether the current user may act on the order.
	 *
	 * @param object $order WC_Order-like.
	 * @return bool
	 */
	private function user_can_access_order( $order ): bool {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		$user_id = get_current_user_id();
		if ( $user_id && method_exists( $order, 'get_user_id' ) && (int) $order->get_user_id() === $user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	private function error_response( string $code, string $message, int $status ): WP_REST_Response {
		$payload = $this->mapper->error( $code, $message, $status );
		unset( $payload['status'] );
		return new WP_REST_Response( $payload, $status );
	}
}
