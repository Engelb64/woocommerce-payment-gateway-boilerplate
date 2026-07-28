<?php
/**
 * Smoke checks for StatusMapper + PaymentService (array-only, no Woo order required).
 *
 * Run:
 *   php bin/smoke-service.php
 * Or via Docker:
 *   docker compose run --rm --no-deps --entrypoint php wordpress /var/www/html/wp-content/plugins/woocommerce-payment-gateway-boilerplate/bin/smoke-service.php
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

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

// Minimal stubs so Plugin filters / i18n are not required in CLI.
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

use WCGatewayBoilerplate\Provider\StubProvider;
use WCGatewayBoilerplate\Service\PaymentService;
use WCGatewayBoilerplate\Service\StatusMapper;
use WCGatewayBoilerplate\Support\Logger;
use WCGatewayBoilerplate\Support\OrderHelper;

$failures = 0;

$mapper = new StatusMapper();
if ( 'processing' !== $mapper->to_woo_status( 'paid' ) || 'failed' !== $mapper->to_woo_status( 'failed' ) ) {
	fwrite( STDERR, "FAIL: StatusMapper mapping\n" );
	++$failures;
} else {
	echo "OK: StatusMapper\n";
}

$logger  = new Logger( 'smoke-service', false );
$service = new PaymentService(
	new StubProvider( null, array( 'webhook_secret' => 'stub_secret' ) ),
	$mapper,
	$logger,
	new OrderHelper()
);

$created = $service->create(
	array(
		'order_id' => 100,
		'amount'   => 25.0,
		'currency' => 'USD',
	)
);

if (
	! $created['result']->is_success()
	|| 'processing' !== $created['woo_status']
	|| 'stub_pay_100' !== $created['result']->get_provider_payment_id()
) {
	fwrite( STDERR, "FAIL: PaymentService::create\n" );
	++$failures;
} else {
	echo "OK: PaymentService::create\n";
}

$failed = $service->create(
	array(
		'order_id'   => 101,
		'force_fail' => true,
	)
);

if ( $failed['result']->is_success() || 'failed' !== $failed['woo_status'] ) {
	fwrite( STDERR, "FAIL: PaymentService::create force_fail\n" );
	++$failures;
} else {
	echo "OK: PaymentService::create force_fail\n";
}

$refunded = $service->refund( 'stub_pay_100', 5.0, 'USD' );
if ( ! $refunded['result']->is_success() || 'refunded' !== $refunded['woo_status'] ) {
	fwrite( STDERR, "FAIL: PaymentService::refund\n" );
	++$failures;
} else {
	echo "OK: PaymentService::refund\n";
}

$body  = '{"event_id":"evt_svc_1","type":"payment.paid","provider_payment_id":"stub_pay_100","status":"paid","order_id":100}';
$event = $service->get_provider()->parse_webhook( array(), $body );
$rec   = $service->reconcile( $event, null );

if ( ! $rec['handled'] || 'processing' !== $rec['woo_status'] ) {
	fwrite( STDERR, "FAIL: PaymentService::reconcile\n" );
	++$failures;
} else {
	echo "OK: PaymentService::reconcile\n";
}

$scrubbed = Logger::scrub(
	array(
		'order_id' => 1,
		'api_key'  => 'super-secret',
		'nested'   => array( 'webhook_secret' => 'x' ),
	)
);

if ( '[redacted]' !== $scrubbed['api_key'] || '[redacted]' !== $scrubbed['nested']['webhook_secret'] || 1 !== $scrubbed['order_id'] ) {
	fwrite( STDERR, "FAIL: Logger::scrub\n" );
	++$failures;
} else {
	echo "OK: Logger::scrub\n";
}

if ( $failures > 0 ) {
	fwrite( STDERR, "Smoke failed: {$failures} check(s)\n" );
	exit( 1 );
}

echo "Smoke passed.\n";
exit( 0 );
