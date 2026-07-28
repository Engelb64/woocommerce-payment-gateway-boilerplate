<?php
/**
 * WpHttpClient tests with Brain Monkey.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Http\WpHttpClient;

final class WpHttpClientTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	public function test_request_returns_normalized_response(): void {
		Functions\expect( 'wp_remote_request' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'headers'  => array( 'Content-Type' => 'application/json' ),
					'body'     => '{"ok":true}',
				)
			);

		Functions\expect( 'is_wp_error' )->andReturn( false );
		Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
		Functions\expect( 'wp_remote_retrieve_body' )->andReturn( '{"ok":true}' );
		Functions\expect( 'wp_remote_retrieve_headers' )->andReturn(
			new class() {
				/**
				 * @return array<string, string>
				 */
				public function getAll() {
					return array( 'content-type' => 'application/json' );
				}
			}
		);

		$client   = new WpHttpClient();
		$response = $client->request( 'GET', 'https://example.test/pay' );

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( '{"ok":true}', $response['body'] );
		$this->assertArrayHasKey( 'headers', $response );
	}

	public function test_request_throws_on_wp_error(): void {
		$error = Mockery::mock( 'WP_Error' );
		$error->shouldReceive( 'get_error_message' )->andReturn( 'connection failed' );

		Functions\expect( 'wp_remote_request' )->once()->andReturn( $error );
		Functions\expect( 'is_wp_error' )->once()->with( $error )->andReturn( true );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'connection failed' );

		( new WpHttpClient() )->request( 'POST', 'https://example.test/pay', array( 'body' => array( 'a' => 1 ) ) );
	}
}
