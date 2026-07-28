<?php
/**
 * Orchestrates payment create / refund / reconcile.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Service;

use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Dto\WebhookEvent;
use WCGatewayBoilerplate\Provider\ProviderInterface;
use WCGatewayBoilerplate\Support\Logger;
use WCGatewayBoilerplate\Support\OrderHelper;

defined( 'ABSPATH' ) || exit;

/**
 * PaymentService — Gateway and Webhook talk to this, not directly to the provider.
 */
class PaymentService {

	/**
	 * @var ProviderInterface
	 */
	private $provider;

	/**
	 * @var StatusMapper
	 */
	private $mapper;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var OrderHelper
	 */
	private $orders;

	/**
	 * @param ProviderInterface $provider Provider adapter.
	 * @param StatusMapper      $mapper   Status mapper.
	 * @param Logger            $logger   Logger.
	 * @param OrderHelper       $orders   Order helper.
	 */
	public function __construct(
		ProviderInterface $provider,
		StatusMapper $mapper,
		Logger $logger,
		OrderHelper $orders
	) {
		$this->provider = $provider;
		$this->mapper   = $mapper;
		$this->logger   = $logger;
		$this->orders   = $orders;
	}

	/**
	 * Create a payment.
	 *
	 * @param array<string, mixed> $order_data Normalized payload (or partial; merged with WC order if given).
	 * @param object|null          $order      Optional WC_Order.
	 * @return array{result:PaymentResult,woo_status:string}
	 */
	public function create( array $order_data = array(), $order = null ): array {
		$payload = $this->orders->build_order_data( $order, $order_data );

		$this->logger->info(
			'Creating payment',
			array(
				'order_id' => $payload['order_id'] ?? null,
				'amount'   => $payload['amount'] ?? null,
				'currency' => $payload['currency'] ?? null,
			)
		);

		$result     = $this->provider->create_payment( $payload );
		$woo_status = $this->mapper->to_woo_status( $result->get_status() );

		if ( $result->get_provider_payment_id() ) {
			$this->orders->set_provider_payment_id( $order, $result->get_provider_payment_id() );
		}

		$this->orders->set_provider_status( $order, $result->get_status() );

		if ( $woo_status ) {
			$note = $result->is_success()
				? sprintf( 'Payment created via provider (%s).', $result->get_provider_payment_id() )
				: sprintf( 'Payment failed via provider: %s', $result->get_message() );
			$this->orders->apply_woo_status( $order, $woo_status, $note );
		}

		$this->logger->info(
			'Payment create finished',
			array(
				'success'             => $result->is_success(),
				'provider_payment_id' => $result->get_provider_payment_id(),
				'provider_status'     => $result->get_status(),
				'woo_status'          => $woo_status,
			)
		);

		return array(
			'result'     => $result,
			'woo_status' => $woo_status,
		);
	}

	/**
	 * Refund a payment.
	 *
	 * @param string      $provider_payment_id Provider payment id (optional if $order has meta).
	 * @param float       $amount              Amount.
	 * @param string      $currency            Currency.
	 * @param object|null $order               Optional WC_Order.
	 * @return array{result:PaymentResult,woo_status:string}
	 */
	public function refund( string $provider_payment_id, float $amount, string $currency, $order = null ): array {
		if ( '' === $provider_payment_id && null !== $order ) {
			$provider_payment_id = $this->orders->get_provider_payment_id( $order );
		}

		$this->logger->info(
			'Refunding payment',
			array(
				'provider_payment_id' => $provider_payment_id,
				'amount'              => $amount,
				'currency'            => $currency,
			)
		);

		$result     = $this->provider->refund_payment( $provider_payment_id, $amount, $currency );
		$woo_status = $this->mapper->to_woo_status( $result->get_status() );

		$this->orders->set_provider_status( $order, $result->get_status() );

		if ( $result->is_success() && $woo_status ) {
			$this->orders->apply_woo_status(
				$order,
				$woo_status,
				sprintf( 'Refunded %s %s via provider.', $amount, $currency )
			);
		}

		return array(
			'result'     => $result,
			'woo_status' => $woo_status,
		);
	}

	/**
	 * Reconcile order state from a webhook event.
	 *
	 * @param WebhookEvent $event Webhook event.
	 * @param object|null  $order Optional WC_Order (resolved from event order_id if null).
	 * @return array{handled:bool,result:?PaymentResult,woo_status:string,reason:string}
	 */
	public function reconcile( WebhookEvent $event, $order = null ): array {
		if ( null === $order && $event->get_order_id() ) {
			$order = $this->orders->get_order( (int) $event->get_order_id() );
		}

		if ( $this->orders->has_processed_event( $order, $event->get_event_id() ) ) {
			$this->logger->info(
				'Skipping duplicate webhook event',
				array( 'event_id' => $event->get_event_id() )
			);

			return array(
				'handled'    => false,
				'result'     => null,
				'woo_status' => '',
				'reason'     => 'duplicate_event',
			);
		}

		$woo_status = $this->mapper->to_woo_status( $event->get_status() );

		if ( $event->get_provider_payment_id() ) {
			$this->orders->set_provider_payment_id( $order, $event->get_provider_payment_id() );
		}

		$this->orders->set_provider_status( $order, $event->get_status() );

		if ( $woo_status ) {
			$this->orders->apply_woo_status(
				$order,
				$woo_status,
				sprintf( 'Reconciled from webhook %s (%s).', $event->get_type(), $event->get_event_id() )
			);
		}

		$this->orders->mark_event_processed( $order, $event->get_event_id() );

		$result = new PaymentResult(
			'failed' !== strtolower( $event->get_status() ),
			$event->get_provider_payment_id(),
			$event->get_status(),
			'',
			'Reconciled from webhook.',
			$event->get_raw()
		);

		$this->logger->info(
			'Webhook reconciled',
			array(
				'event_id'            => $event->get_event_id(),
				'provider_payment_id' => $event->get_provider_payment_id(),
				'provider_status'     => $event->get_status(),
				'woo_status'          => $woo_status,
			)
		);

		return array(
			'handled'    => true,
			'result'     => $result,
			'woo_status' => $woo_status,
			'reason'     => 'ok',
		);
	}

	/**
	 * @return ProviderInterface
	 */
	public function get_provider(): ProviderInterface {
		return $this->provider;
	}

	/**
	 * @return StatusMapper
	 */
	public function get_mapper(): StatusMapper {
		return $this->mapper;
	}
}
