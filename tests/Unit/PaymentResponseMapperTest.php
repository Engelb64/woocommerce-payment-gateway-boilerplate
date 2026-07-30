<?php
/**
 * PaymentResponseMapper unit tests.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Headless\PaymentResponseMapper;

final class PaymentResponseMapperTest extends TestCase {

	public function test_from_outcome_success_omits_raw_secrets(): void {
		$mapper  = new PaymentResponseMapper();
		$payload = $mapper->from_outcome(
			array(
				'result'     => new PaymentResult(
					true,
					'stub_pay_1',
					'paid',
					'https://example.test/thanks',
					'OK',
					array( 'api_key' => 'should-not-appear' )
				),
				'woo_status' => 'processing',
			)
		);

		$this->assertTrue( $payload['success'] );
		$this->assertSame( 'stub_pay_1', $payload['provider_payment_id'] );
		$this->assertSame( 'paid', $payload['provider_status'] );
		$this->assertSame( 'processing', $payload['woo_status'] );
		$this->assertSame( 'https://example.test/thanks', $payload['redirect_url'] );
		$this->assertArrayNotHasKey( 'raw', $payload );
		$this->assertArrayNotHasKey( 'api_key', $payload );
	}

	public function test_from_order_meta_and_error(): void {
		$mapper = new PaymentResponseMapper();
		$meta   = $mapper->from_order_meta( 9, 'cs_x', 'pending', 'on-hold' );

		$this->assertSame( 9, $meta['order_id'] );
		$this->assertSame( 'cs_x', $meta['provider_payment_id'] );

		$error = $mapper->error( 'forbidden', 'No', 403 );
		$this->assertFalse( $error['success'] );
		$this->assertSame( 'forbidden', $error['code'] );
		$this->assertSame( 403, $error['status'] );
	}
}
