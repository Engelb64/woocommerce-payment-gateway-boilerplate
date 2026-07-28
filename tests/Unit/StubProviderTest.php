<?php
/**
 * StubProvider tests.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Provider\StubProvider;

final class StubProviderTest extends TestCase {

	/** @var StubProvider */
	private $provider;

	protected function setUp(): void {
		parent::setUp();
		$this->provider = new StubProvider( null, array( 'webhook_secret' => 'stub_secret' ) );
	}

	public function test_create_payment_success(): void {
		$result = $this->provider->create_payment(
			array(
				'order_id' => 42,
				'amount'   => 10.0,
				'currency' => 'USD',
			)
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'paid', $result->get_status() );
		$this->assertSame( 'stub_pay_42', $result->get_provider_payment_id() );
	}

	public function test_create_payment_force_fail(): void {
		$result = $this->provider->create_payment(
			array(
				'order_id'   => 43,
				'force_fail' => true,
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'failed', $result->get_status() );
	}

	public function test_refund_payment(): void {
		$result = $this->provider->refund_payment( 'stub_pay_42', 5.0, 'USD' );
		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'refunded', $result->get_status() );
	}

	public function test_refund_rejects_invalid_amount(): void {
		$result = $this->provider->refund_payment( 'stub_pay_42', 0, 'USD' );
		$this->assertFalse( $result->is_success() );
	}

	public function test_get_payment_known_and_unknown(): void {
		$ok = $this->provider->get_payment( 'stub_pay_1' );
		$this->assertTrue( $ok->is_success() );

		$missing = $this->provider->get_payment( 'other_1' );
		$this->assertFalse( $missing->is_success() );
	}

	public function test_webhook_signature_and_parse(): void {
		$body = '{"event_id":"evt_1","type":"payment.paid","provider_payment_id":"stub_pay_42","status":"paid","order_id":42}';
		$sig  = hash_hmac( 'sha256', $body, 'stub_secret' );

		$this->assertTrue(
			$this->provider->verify_webhook_signature(
				array( 'X-Stub-Signature' => $sig ),
				$body
			)
		);
		$this->assertFalse(
			$this->provider->verify_webhook_signature(
				array( 'X-Stub-Signature' => 'bad' ),
				$body
			)
		);

		$event = $this->provider->parse_webhook( array(), $body );
		$this->assertSame( 'evt_1', $event->get_event_id() );
		$this->assertSame( 42, $event->get_order_id() );
		$this->assertSame( 'paid', $event->get_status() );
	}
}
