<?php
/**
 * DTO tests.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Dto\PaymentResult;
use WCGatewayBoilerplate\Dto\WebhookEvent;

final class DtoTest extends TestCase {

	public function test_payment_result_to_array(): void {
		$result = new PaymentResult( true, 'pay_1', 'paid', 'https://example.test', 'ok', array( 'a' => 1 ) );

		$this->assertTrue( $result->is_success() );
		$this->assertSame(
			array(
				'success'             => true,
				'provider_payment_id' => 'pay_1',
				'status'              => 'paid',
				'redirect_url'        => 'https://example.test',
				'message'             => 'ok',
				'raw'                 => array( 'a' => 1 ),
			),
			$result->to_array()
		);
	}

	public function test_webhook_event_to_array(): void {
		$event = new WebhookEvent( 'evt_1', 'payment.paid', 'pay_1', 'paid', 9, array( 'x' => true ) );

		$this->assertSame( 'evt_1', $event->get_event_id() );
		$this->assertSame(
			array(
				'event_id'            => 'evt_1',
				'type'                => 'payment.paid',
				'provider_payment_id' => 'pay_1',
				'status'              => 'paid',
				'order_id'            => 9,
				'raw'                 => array( 'x' => true ),
			),
			$event->to_array()
		);
	}
}
