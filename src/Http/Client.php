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
	 * WLM_Client constructor.
	 *
	 * @param $consumerKey
	 * @param $consumerSecret
	 * @param $baseUrl
	 */
	public function __construct( $args ) {
		$this->consumerKey    = isset( $args['consumer_key'] ) ? $args['consumer_key'] : '';
		$this->consumerSecret = isset( $args['consumer_secret'] ) ? $args['consumer_secret'] : '';
		$this->baseUrl        = isset( $args['api_url'] ) ? $args['api_url'] : '';
		$this->prefix         = isset( $args['prefix'] ) ? $args['prefix'] : 'dlm';
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
	 * @return string
	 */
	public function getUpdateCacheKey( $id ) {
		return sprintf( '%s_update_%s', $this->prefix, $id );
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
	public function deactivateLicense( $activationToken, $decode = true ) {
		$result = $this->_get( 'licenses/deactivate/' . $activationToken );

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
	 * Deactivate license
	 *
	 * @param $softwareId
	 * @param null $acitvationToken
	 * @param string $type
	 * @param bool $decode
	 *
	 * @return array|\WP_Error
	 */
	public function info( $softwareId, $acitvationToken = null, $type = 'wp', $decode = true ) {
		$params = array( 'type' => $type );
		if ( ! empty( $acitvationToken ) ) {
			$params['activation_token'] = $acitvationToken;
		}
		$result = $this->_get( 'software/' . $softwareId, $params );

		return $this->_result( $result, $decode );
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
	 * Retrieve the license
	 *
	 * @param $token
	 *
	 * @return mixed
	 * @return mixed
	 */
	public function prepareLicense( $token ) {
		$cacheKey = $this->getLicenseCacheKey( $token );
		$license  = get_transient( $cacheKey );
		if ( false === $license ) {
			$response = $this->validateLicense( $token );
			if ( ! $response->isError() ) {
				$license = $response->getData();
				set_transient( $cacheKey, $license, HOUR_IN_SECONDS );
			}
		}

		return $license;
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
		delete_transient( $this->getUpdateCacheKey( $entity->getId() ) );
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
