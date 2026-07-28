<?php
/**
 * StatusMapper tests.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

namespace WCGatewayBoilerplate\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCGatewayBoilerplate\Service\StatusMapper;

final class StatusMapperTest extends TestCase {

	public function test_default_paid_maps_to_processing(): void {
		$mapper = new StatusMapper();
		$this->assertSame( 'processing', $mapper->to_woo_status( 'paid' ) );
		$this->assertSame( 'processing', $mapper->to_woo_status( 'captured' ) );
	}

	public function test_failed_and_refunded_mapping(): void {
		$mapper = new StatusMapper();
		$this->assertSame( 'failed', $mapper->to_woo_status( 'failed' ) );
		$this->assertSame( 'refunded', $mapper->to_woo_status( 'refunded' ) );
		$this->assertSame( 'cancelled', $mapper->to_woo_status( 'canceled' ) );
	}

	public function test_unknown_status_returns_empty_string(): void {
		$mapper = new StatusMapper();
		$this->assertSame( '', $mapper->to_woo_status( 'weird-status' ) );
		$this->assertFalse( $mapper->has( 'weird-status' ) );
	}

	public function test_custom_map_override(): void {
		$mapper = new StatusMapper( array( 'paid' => 'completed' ) );
		$this->assertSame( 'completed', $mapper->to_woo_status( 'paid' ) );
	}

	public function test_status_is_normalized_case_insensitive(): void {
		$mapper = new StatusMapper();
		$this->assertSame( 'on-hold', $mapper->to_woo_status( ' PENDING ' ) );
	}
}
