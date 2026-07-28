<?php
/**
 * Logger scrub + PaymentService orchestration tests.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Provider\StubProvider;
use WCGatewayBoilerplate\Service\PaymentService;
use WCGatewayBoilerplate\Service\StatusMapper;
use WCGatewayBoilerplate\Support\Logger;
use WCGatewayBoilerplate\Support\OrderHelper;

final class PaymentServiceTest extends TestCase {

	/** @var PaymentService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->service = new PaymentService(
			new StubProvider( null, array( 'webhook_secret' => 'stub_secret' ) ),
			new StatusMapper(),
			new Logger( 'test', false ),
			new OrderHelper()
		);
	}

	public function test_create_maps_paid_to_processing(): void {
		$outcome = $this->service->create(
			array(
				'order_id' => 10,
				'amount'   => 20.0,
				'currency' => 'USD',
			)
		);

		$this->assertTrue( $outcome['result']->is_success() );
		$this->assertSame( 'processing', $outcome['woo_status'] );
		$this->assertSame( 'stub_pay_10', $outcome['result']->get_provider_payment_id() );
	}

	public function test_create_force_fail_maps_to_failed(): void {
		$outcome = $this->service->create(
			array(
				'order_id'   => 11,
				'force_fail' => true,
			)
		);

		$this->assertFalse( $outcome['result']->is_success() );
		$this->assertSame( 'failed', $outcome['woo_status'] );
	}

	public function test_refund_maps_to_refunded(): void {
		$outcome = $this->service->refund( 'stub_pay_10', 5.0, 'USD' );
		$this->assertTrue( $outcome['result']->is_success() );
		$this->assertSame( 'refunded', $outcome['woo_status'] );
	}

	public function test_reconcile_without_order_still_returns_mapped_status(): void {
		$body  = '{"event_id":"evt_svc","type":"payment.paid","provider_payment_id":"stub_pay_10","status":"paid","order_id":10}';
		$event = $this->service->get_provider()->parse_webhook( array(), $body );
		$rec   = $this->service->reconcile( $event, null );

		$this->assertTrue( $rec['handled'] );
		$this->assertSame( 'processing', $rec['woo_status'] );
	}

	public function test_logger_scrub_redacts_secrets(): void {
		$scrubbed = Logger::scrub(
			array(
				'order_id' => 1,
				'api_key'  => 'secret-value',
				'nested'   => array( 'webhook_secret' => 'x' ),
			)
		);

		$this->assertSame( 1, $scrubbed['order_id'] );
		$this->assertSame( '[redacted]', $scrubbed['api_key'] );
		$this->assertSame( '[redacted]', $scrubbed['nested']['webhook_secret'] );
	}
}
