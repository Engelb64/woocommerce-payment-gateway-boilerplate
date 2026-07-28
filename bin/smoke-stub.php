<?php
/**
 * Manual smoke checks for StubProvider (no WordPress bootstrap required for the stub itself).
 *
 * Run from repo root:
 *   php bin/smoke-stub.php
 *
 * Exit code 0 = all checks passed.
 *
 * @package WCGatewayBoilerplate
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		$prefix = 'WCGatewayBoilerplate\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file     = $root . '/includes/' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// ABSPATH guard in includes expects the constant; define a dummy for CLI smoke.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

use WCGatewayBoilerplate\Provider\StubProvider;

$failures = 0;
$provider = new StubProvider( null, array( 'webhook_secret' => 'stub_secret' ) );

$ok = $provider->create_payment(
	array(
		'order_id' => 42,
		'amount'   => 10.5,
		'currency' => 'USD',
	)
);

if ( ! $ok->is_success() || 'paid' !== $ok->get_status() || 'stub_pay_42' !== $ok->get_provider_payment_id() ) {
	fwrite( STDERR, "FAIL: create_payment success case\n" );
	++$failures;
} else {
	echo "OK: create_payment success\n";
}

$fail = $provider->create_payment(
	array(
		'order_id'   => 43,
		'force_fail' => true,
	)
);

if ( $fail->is_success() || 'failed' !== $fail->get_status() ) {
	fwrite( STDERR, "FAIL: create_payment force_fail case\n" );
	++$failures;
} else {
	echo "OK: create_payment force_fail\n";
}

$refund = $provider->refund_payment( 'stub_pay_42', 5.0, 'USD' );
if ( ! $refund->is_success() || 'refunded' !== $refund->get_status() ) {
	fwrite( STDERR, "FAIL: refund_payment\n" );
	++$failures;
} else {
	echo "OK: refund_payment\n";
}

$body    = '{"event_id":"evt_1","type":"payment.paid","provider_payment_id":"stub_pay_42","status":"paid","order_id":42}';
$sig     = hash_hmac( 'sha256', $body, 'stub_secret' );
$valid   = $provider->verify_webhook_signature( array( 'X-Stub-Signature' => $sig ), $body );
$invalid = $provider->verify_webhook_signature( array( 'X-Stub-Signature' => 'bad' ), $body );
$event   = $provider->parse_webhook( array(), $body );

if ( ! $valid || $invalid || 'evt_1' !== $event->get_event_id() || 42 !== $event->get_order_id() ) {
	fwrite( STDERR, "FAIL: webhook verify/parse\n" );
	++$failures;
} else {
	echo "OK: webhook verify/parse\n";
}

if ( $failures > 0 ) {
	fwrite( STDERR, "Smoke failed: {$failures} check(s)\n" );
	exit( 1 );
}

echo "Smoke passed.\n";
exit( 0 );
