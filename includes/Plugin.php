<?php
/**
 * Plugin bootstrap.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate;

use WCGatewayBoilerplate\Http\WpHttpClient;
use WCGatewayBoilerplate\Provider\ProviderInterface;
use WCGatewayBoilerplate\Provider\StubProvider;
use WCGatewayBoilerplate\Service\PaymentService;
use WCGatewayBoilerplate\Service\StatusMapper;
use WCGatewayBoilerplate\Support\Logger;
use WCGatewayBoilerplate\Support\OrderHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton).
 *
 * Wires provider + PaymentService. Gateway registration comes in v0.4.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var ProviderInterface|null
	 */
	private $provider = null;

	/**
	 * @var PaymentService|null
	 */
	private $payment_service = null;

	/**
	 * @var Logger|null
	 */
	private $logger = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Prevent direct construction.
	 */
	private function __construct() {}

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_textdomain();
		$this->boot_services();

		/**
		 * Fires after the boilerplate plugin has initialized.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wc_gateway_boilerplate_init', $this );
	}

	/**
	 * @return ProviderInterface
	 */
	public function get_provider(): ProviderInterface {
		if ( null === $this->provider ) {
			$this->boot_services();
		}

		return $this->provider;
	}

	/**
	 * @return PaymentService
	 */
	public function get_payment_service(): PaymentService {
		if ( null === $this->payment_service ) {
			$this->boot_services();
		}

		return $this->payment_service;
	}

	/**
	 * @return Logger
	 */
	public function get_logger(): Logger {
		if ( null === $this->logger ) {
			$this->boot_services();
		}

		return $this->logger;
	}

	/**
	 * Build provider + payment service (injectable via filters).
	 *
	 * @return void
	 */
	private function boot_services() {
		if ( null !== $this->payment_service ) {
			return;
		}

		$this->logger = new Logger( 'wc-gateway-boilerplate', true );

		/**
		 * Filter provider configuration (api keys, webhook secret, sandbox, ...).
		 *
		 * @param array<string, mixed> $config Provider config.
		 */
		$config = apply_filters(
			'wc_gateway_boilerplate_provider_config',
			array(
				'webhook_secret' => 'stub_secret',
				'sandbox'        => true,
			)
		);

		$http = new WpHttpClient();

		/**
		 * Filter the active provider instance.
		 *
		 * Swap StubProvider for a real adapter without touching PaymentService.
		 *
		 * @param ProviderInterface $provider Provider.
		 * @param array             $config   Config.
		 */
		$this->provider = apply_filters(
			'wc_gateway_boilerplate_provider',
			new StubProvider( $http, is_array( $config ) ? $config : array() ),
			$config
		);

		$mapper = new StatusMapper(
			/**
			 * Filter status map (provider → Woo).
			 *
			 * @param array<string, string> $map Status map.
			 */
			apply_filters( 'wc_gateway_boilerplate_status_map', StatusMapper::default_map() )
		);

		$this->payment_service = new PaymentService(
			$this->provider,
			$mapper,
			$this->logger,
			new OrderHelper()
		);
	}

	/**
	 * Load plugin translations based on the site locale.
	 *
	 * @return void
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'wc-payment-gateway-boilerplate',
			false,
			dirname( plugin_basename( WC_GATEWAY_BOILERPLATE_FILE ) ) . '/languages'
		);
	}
}
