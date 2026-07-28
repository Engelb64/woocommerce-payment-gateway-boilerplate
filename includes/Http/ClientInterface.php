<?php
/**
 * HTTP client contract.
 *
 * @package WCGatewayBoilerplate
 */

namespace WCGatewayBoilerplate\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal HTTP client used by providers.
 */
interface ClientInterface {

	/**
	 * Perform an HTTP request.
	 *
	 * @param string               $method  HTTP method (GET, POST, ...).
	 * @param string               $uri     Absolute URL or path the implementation understands.
	 * @param array<string, mixed> $options Optional keys: headers, body, query, timeout.
	 * @return array{status:int,headers:array<string,mixed>,body:string} Normalized response.
	 *
	 * @throws \RuntimeException When the transport fails.
	 */
	public function request( string $method, string $uri, array $options = array() ): array;
}
