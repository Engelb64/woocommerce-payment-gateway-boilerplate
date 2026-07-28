<?php
/**
 * Plugin bootstrap.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate;

use WCGatewayBoilerplate\Gateway\AbstractGateway;
use WCGatewayBoilerplate\Gateway\BlocksSupport;
use WCGatewayBoilerplate\Http\WpHttpClient;
use WCGatewayBoilerplate\Provider\ProviderInterface;
use WCGatewayBoilerplate\Provider\StubProvider;
use WCGatewayBoilerplate\Service\PaymentService;
use WCGatewayBoilerplate\Service\StatusMapper;
use WCGatewayBoilerplate\Support\Logger;
use WCGatewayBoilerplate\Support\OrderHelper;
use WCGatewayBoilerplate\Webhook\WebhookHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class (singleton).
 *
 * Wires provider, PaymentService and registers the WooCommerce gateway.
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

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );

		$webhook = new WebhookHandler();
		$webhook->register();

		$this->register_blocks_support();

		/**
		 * Fires after the boilerplate plugin has initialized.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'wc_gateway_boilerplate_init', $this );
	}

	/**
	 * Register WooCommerce Blocks payment method type.
	 *
	 * @return void
	 */
	private function register_blocks_support() {
		$callback = array( $this, 'on_blocks_loaded' );

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->on_blocks_loaded();
			return;
		}

		add_action( 'woocommerce_blocks_loaded', $callback );
	}

	/**
	 * Hook into Blocks payment method registration.
	 *
	 * @return void
	 */
	public function on_blocks_loaded() {
		if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $payment_method_registry ) {
				if ( is_object( $payment_method_registry ) && method_exists( $payment_method_registry, 'register' ) ) {
					$payment_method_registry->register( new BlocksSupport() );
				}
			}
		);
	}

	/**
	 * Register gateway class with WooCommerce.
	 *
	 * @param array<int, string> $gateways Gateway class names.
	 * @return array<int, string>
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = AbstractGateway::class;
		return $gateways;
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
	 * Read saved gateway settings from WooCommerce options.
	 *
	 * @return array<string, mixed>
	 */
	public function get_gateway_settings(): array {
		$settings = get_option( 'woocommerce_' . AbstractGateway::GATEWAY_ID . '_settings', array() );
		return is_array( $settings ) ? $settings : array();
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

		$settings = $this->get_gateway_settings();
		$logging  = ! isset( $settings['logging'] ) || 'yes' === $settings['logging'];

		$this->logger = new Logger( 'wc-gateway-boilerplate', $logging );

		$default_config = array(
			'api_key'        => isset( $settings['api_key'] ) ? (string) $settings['api_key'] : '',
			'webhook_secret' => isset( $settings['webhook_secret'] ) && '' !== $settings['webhook_secret']
				? (string) $settings['webhook_secret']
				: 'stub_secret',
			'sandbox'        => ! isset( $settings['sandbox'] ) || 'yes' === $settings['sandbox'],
		);

		/**
		 * Filter provider configuration (api keys, webhook secret, sandbox, ...).
		 *
		 * @param array<string, mixed> $config Provider config.
		 */
		$config = apply_filters( 'wc_gateway_boilerplate_provider_config', $default_config );

		$http = new WpHttpClient();

		/**
		 * Filter the active provider instance.
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
