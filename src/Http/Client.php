<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Http;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Model;

class Client {

	/**
	 * The consumer key
	 * @var string
	 */
	protected $consumerKey;

	/**
	 * The consumer secret
	 * @var string
	 */
	protected $consumerSecret;

	/**
	 * The base url
	 * @var string
	 */
	protected $baseUrl;

	/**
	 * The prefix
	 * @var string
	 */
	protected $prefix;

	/**
	 * The cache TTL
	 * @var float[]|int[]
	 */
	protected $cacheTTL = [
		'info'            => MINUTE_IN_SECONDS * 15,
		'validateLicense' => HOUR_IN_SECONDS * 1,
	];

	/**
	 * Client constructor.
	 *
	 * @param $args
	 */
	public function __construct( $args ) {
		$this->consumerKey    = isset( $args['consumer_key'] ) ? $args['consumer_key'] : '';
		$this->consumerSecret = isset( $args['consumer_secret'] ) ? $args['consumer_secret'] : '';
		$this->baseUrl        = isset( $args['api_url'] ) ? $args['api_url'] : '';
		$this->prefix         = isset( $args['prefix'] ) ? $args['prefix'] : 'dlm';

		foreach ( $this->cacheTTL as $key => $value ) {
			$this->cacheTTL[ $key ] = isset( $args[ 'cache_ttl_' . $key ] ) && is_numeric( $args[ 'cache_ttl_' . $key ] ) ? intval( $args[ 'cache_ttl_' . $key ] ) : $this->cacheTTL[ $key ];
		}

	}

	/**
	 * Returns the license cache key
	 *
	 * @param string $token
	 *
	 * @return string
	 */
	public function getLicenseCacheKey( $token ) {
		return sprintf( '%s_license_%s', $this->prefix, substr( md5( $token ), 0, 12 ) );
	}

	/**
	 * Returns the update cache key
	 *
	 * @param Model $entity
	 *
	 * @return string
	 */
	public function getUpdateCacheKey( $entity ) {
		return sprintf( '%s_update_%s_%s', $this->prefix, $entity->getId(), $entity->getVersion() );
	}

	/**
	 * Returns license
	 *
	 * @param $licenseKey
	 * @param bool $decode
	 *
	 * @return array|\WP_Error
	 */
	public function getLicense( $licenseKey, $decode = true ) {
		$result = $this->_get( 'licenses/' . $licenseKey );

		return $this->_result( $result, $decode );
	}

	/**
	 * Activate license
	 *
	 * @param $licenseKey
	 * @param array $params
	 * @param bool $decode
	 *
	 * @return array|\WP_Error
	 */
	public function activateLicense( $licenseKey, $params = array(), $decode = true ) {
		$result = $this->_get( 'licenses/activate/' . $licenseKey, $params );

		return $this->_result( $result, $decode );
	}

	/**
	 * Deactivate license
	 *
	 * @param $activationToken
	 * @param bool $decode
	 *
	 * @return array|\WP_Error
	 */
	public function deactivateLicense( $activationToken, $params = array(), $decode = true ) {
		$result = $this->_get( 'licenses/deactivate/' . $activationToken, $params );

		return $this->_result( $result, $decode );
	}

	/**
	 * Deactivate license
	 *
	 * @param $activationToken
	 * @param bool $decode
	 *
	 * @return array|\WP_Error
	 */
	public function validateLicense( $activationToken, $decode = true ) {
		$result = $this->_get( 'licenses/validate/' . $activationToken );

		return $this->_result( $result, $decode );
	}

	/**
	 * Find remote info
	 *
	 * @param $entity
	 * @param string $type
	 * @param bool $decode
	 *
	 * @return array|Response|\WP_Error
	 */
	private function info( $entity, $type = 'wp', $decode = true ) {
		$softwareId      = $entity->getId();
		$acitvationToken = $entity->getActivationToken();

		$params = array( 'type' => $type );
		if ( ! empty( $acitvationToken ) ) {
			$params['activation_token'] = $acitvationToken;
		}
		$result = $this->_get( 'software/' . $softwareId, $params );

		return $this->_result( $result, $decode );
	}

	/**
	 * Find remote info (Cached)
	 *
	 * @param Model $entity
	 * @param string $type
	 * @param bool $decode
	 *
	 * @return array
	 */
	public function prepareInfo( $entity, $type = 'wp', $decode = true, $force = false ) {

		$cacheTTL  = $this->cacheTTL['info'] ?? 0;
		$transient = $this->getUpdateCacheKey( $entity );
		
		// Return an unexpired cached response.
		if ( false === $force ) {
			$responseData = get_transient( $transient );
			if ( false !== $responseData ) {
				return $responseData;
			}
		}
		
		// Get a fresh response.
		$response = $this->info( $entity, $type, $decode );

		// If the response is an error, it should be cached only long enough to avoid
		// additional requests during the lifetime of the current page request.
		if ( $response->isError() ) {
			$cacheTTL = 1;
		}
		
		$responseData = is_a( $response, Response::class ) ? $response->getData() : array();
		set_transient( $transient, $responseData, $cacheTTL );

		return $responseData;
	}

	/**
	 * Retrieve the license
	 *
	 * @param $token
	 * @param bool $decode
	 * @param bool $force
	 *
	 * @return array
	 */
	public function prepareValidateLicense( $token, $decode = true, $force = false ) {

		$cacheTTL  = $this->cacheTTL['validateLicense'] ?? 0;
		$transient = $this->getLicenseCacheKey( $token );
		
		// Return an unexpired cached response.
		if ( false === $force ) {
			$responseData = get_transient( $transient );
			if ( false !== $responseData ) {
				return $responseData;
			}
		}
		
		// Get a fresh response.
		$response = $this->validateLicense( $token, $decode );

		// If the response is an error, it should be cached only long enough to avoid
		// additional requests during the lifetime of the current page request.
		if ( $response->isError() ) {
			$cacheTTL = 1;
		}
		
		$responseData = is_a( $response, Response::class ) ? $response->getData() : array();
		set_transient( $transient, $responseData, $cacheTTL );

		return $responseData;
	}


	/**
	 * The result
	 *
	 * @param array|\WP_Error $response
	 * @param $decode
	 *
	 * @return Response|array|\WP_Error
	 */
	protected function _result( $response, $decode ) {

		if ( $decode ) {
			$newResponse = new Response();
			if ( is_wp_error( $response ) ) {
				$newResponse->setCode( 'http_error' );
				$newResponse->setError( $response->get_error_message() );
			} else {
				$responseData = @json_decode( $response['body'], true );
				if ( ! is_array( $responseData ) ) {
					$newResponse->setError( 'Unable to decode response. (1)' );
				} else if ( isset( $responseData['message'] ) && isset( $responseData['data']['status'] ) ) {
					$newResponse->setError( $responseData['message'] );
					$newResponse->setCode( $responseData['code'] );
				} else {
					if ( ! isset( $responseData['data'] ) ) {
						$newResponse->setError( 'Unable to decode response. (2)' );
					} else {
						$newResponse->setData( $responseData['data'] );
					}
				}
			}
			$newResponse->setRaw( $response );
			$response = $newResponse;
		}

		return $response;
	}

	/**
	 * Performs HTTP GET request
	 *
	 * @param $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return array|\WP_Error
	 */
	protected function _get( $path, $params = array(), $headers = array() ) {

		$url     = add_query_arg( $params, trailingslashit( $this->baseUrl ) . ltrim( $path, '/' ) );
		$headers = array_merge( $this->authHeaders(), $headers );
		$params  = apply_filters( 'wlm_http_get_params', array( 'timeout' => 5, 'headers' => $headers ), $this );

		return wp_remote_get( $url, $params );
	}

	/**
	 * Performs HTTP POST request
	 *
	 * @param $path
	 * @param array $params
	 * @param array $headers
	 *
	 * @return array|\WP_Error
	 */
	protected function _post( $path, $params = array(), $headers = array() ) {

		$url     = trailingslashit( $this->baseUrl ) . ltrim( $path, '/' );
		$headers = array_merge( $this->authHeaders(), $headers );
		$params  = apply_filters( 'wlm_http_post_params', array(
			'timeout' => 5,
			'headers' => $headers,
			'body'    => $params
		), $this );

		return wp_remote_post( $url, $params );
	}

	/**
	 * Clear the client cache
	 *
	 * @param Model $entity
	 */
	public function clearCache( $entity ) {
		// Clear license cache
		$token = $entity->getActivationToken();
		if ( $token ) {
			$licenseCacheKey = $this->getLicenseCacheKey( $token );
			delete_transient( $licenseCacheKey );
		}
		// Clear update cache
		delete_transient( $this->getUpdateCacheKey( $entity ) );
		// Force update check
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Get authorization headers
	 * @return mixed|void
	 */
	protected function authHeaders() {
		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $this->consumerKey . ':' . $this->consumerSecret ),
		);

		return apply_filters( 'dlm_http_auth', $headers );
	}
}
