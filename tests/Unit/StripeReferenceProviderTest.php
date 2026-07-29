<?php
/**
 * StripeReferenceProvider unit tests (example adapter).
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Http\ClientInterface;
use WCGatewayBoilerplate\Provider\Example\StripeReferenceProvider;

final class StripeReferenceProviderTest extends TestCase {

	public function test_create_payment_builds_checkout_session(): void {
		$http = new class() implements ClientInterface {
			/** @var array<string, mixed> */
			public $last = array();

			public function request( string $method, string $uri, array $options = array() ): array {
				$this->last = compact( 'method', 'uri', 'options' );
				return array(
					'status'  => 200,
					'headers' => array(),
					'body'    => wp_json_encode(
						array(
							'id'  => 'cs_test_123',
							'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
						)
					),
				);
			}
		};

		$provider = new StripeReferenceProvider(
			$http,
			array(
				'api_key'        => 'sk_test_x',
				'webhook_secret' => 'whsec_x',
			)
		);

		$result = $provider->create_payment(
			array(
				'order_id'   => 99,
				'amount'     => 10.50,
				'currency'   => 'USD',
				'return_url' => 'http://localhost/thanks',
				'cancel_url' => 'http://localhost/checkout',
				'customer'   => array( 'email' => 'a@example.com' ),
			)
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'pending', $result->get_status() );
		$this->assertSame( 'cs_test_123', $result->get_provider_payment_id() );
		$this->assertSame( 'https://checkout.stripe.com/c/pay/cs_test_123', $result->get_redirect_url() );
		$this->assertSame( 'POST', $http->last['method'] );
		$this->assertStringContainsString( '/checkout/sessions', $http->last['uri'] );
		$this->assertStringContainsString( 'Bearer sk_test_x', $http->last['options']['headers']['Authorization'] );
	}

	public function test_create_payment_requires_api_key(): void {
		$provider = new StripeReferenceProvider( null, array() );
		$result   = $provider->create_payment(
			array(
				'order_id'   => 1,
				'amount'     => 1,
				'currency'   => 'USD',
				'return_url' => 'http://localhost/thanks',
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertStringContainsString( 'API key', $result->get_message() );
	}

	public function test_refund_resolves_session_to_payment_intent(): void {
		$http = new class() implements ClientInterface {
			public function request( string $method, string $uri, array $options = array() ): array {
				if ( false !== strpos( $uri, '/checkout/sessions/' ) ) {
					return array(
						'status'  => 200,
						'headers' => array(),
						'body'    => '{"id":"cs_test_1","payment_intent":"pi_test_1"}',
					);
				}

				return array(
					'status'  => 200,
					'headers' => array(),
					'body'    => '{"id":"re_test_1","status":"succeeded"}',
				);
			}
		};

		$provider = new StripeReferenceProvider( $http, array( 'api_key' => 'sk_test_x' ) );
		$result   = $provider->refund_payment( 'cs_test_1', 5.0, 'USD' );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'refunded', $result->get_status() );
		$this->assertSame( 'pi_test_1', $result->get_provider_payment_id() );
	}

	public function test_webhook_signature_valid_and_invalid(): void {
		$secret   = 'whsec_test_secret';
		$body     = '{"id":"evt_1","type":"checkout.session.completed","data":{"object":{"id":"cs_1","payment_intent":"pi_1","client_reference_id":"42","payment_status":"paid"}}}';
		$t        = time();
		$sig      = hash_hmac( 'sha256', $t . '.' . $body, $secret );
		$header   = 't=' . $t . ',v1=' . $sig;
		$provider = new StripeReferenceProvider( null, array( 'webhook_secret' => $secret ) );

		$this->assertTrue(
			$provider->verify_webhook_signature(
				array( 'Stripe-Signature' => $header ),
				$body
			)
		);
		$this->assertFalse(
			$provider->verify_webhook_signature(
				array( 'Stripe-Signature' => 't=' . $t . ',v1=deadbeef' ),
				$body
			)
		);

		$event = $provider->parse_webhook( array(), $body );
		$this->assertSame( 'evt_1', $event->get_event_id() );
		$this->assertSame( 'paid', $event->get_status() );
		$this->assertSame( 'pi_1', $event->get_provider_payment_id() );
		$this->assertSame( 42, $event->get_order_id() );
	}
}
