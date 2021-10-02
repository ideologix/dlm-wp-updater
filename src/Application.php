<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Utilities;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Http\Client;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Plugin;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Theme;


class Application {

	/**
	 * The HTTP Client
	 * @var Client
	 */
	protected $client;

	/**
	 * The Entity
	 * @var Plugin|Theme
	 */
	protected $entity;

	/**
	 * The app prefix
	 * @var string
	 */
	protected $prefix;

	/**
	 * Application constructor.
	 *
	 * @param $args
	 * @param string $context
	 */
	public function __construct( $args, $context = 'plugin' ) {

		// The entity params
		$entityArgs = Utilities::arrayOnly( $args, array(
			'id',
			'name',
			'basename',
			'file',
			'version',
			'url_settings',
			'url_purchase',
		) );

		// The client main params.
		$clientArgs = Utilities::arrayOnly( $args, array(
			'consumer_key',
			'consumer_secret',
			'api_url',
		) );

		// The client cache params.
		foreach ( $args as $key => $value ) {
			if ( strpos( $key, 'cache_ttl_' ) === 0 ) {
				$clientArgs[ $key ] = $value;
			}
		}

		// The Prefix
		$this->prefix         = isset( $args['prefix'] ) ? $args['prefix'] : 'dlm';
		$entityArgs['prefix'] = $this->prefix;
		$clientArgs['prefix'] = $this->prefix;

		// The Entity Object
		if ( 'plugin' === $context ) {
			$this->entity = new Plugin( $entityArgs );
		} else if ( 'theme' === $context ) {
			$this->entity = new Theme( $entityArgs );
		}

		// The Client Object
		$this->client = new Client( $clientArgs );
	}

	/**
	 * Returns the client
	 * @return Client
	 */
	public function getClient() {
		return $this->client;
	}

	/**
	 * Return the entity
	 * @return Plugin|Theme
	 */
	public function getEntity() {
		return $this->entity;
	}

	/**
	 * Returns the prefix
	 * @return mixed|string
	 */
	public function getPrefix() {
		return $this->prefix;
	}

	/**
	 * Clears the cache
	 */
	public function clearCache() {
		$this->client->clearCache( $this->entity );
	}
}
