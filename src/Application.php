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
	 * Application constructor.
	 *
	 * @param $args
	 * @param string $context
	 */
	public function __construct( $args, $context = 'plugin' ) {
		$entityArgs = Utilities::arrayOnly( $args, array(
			'id',
			'name',
			'basename',
			'file',
			'version',
			'url_settings',
			'url_purchase',
			'prefix'
		) );
		$clientArgs = Utilities::arrayOnly( $args, array(
			'consumer_key',
			'consumer_secret',
			'api_url', 'prefix'
		) );
		if ( 'plugin' === $context ) {
			$this->entity = new Plugin( $entityArgs );
		} else if ( 'theme' === $context ) {
			$this->entity = new Theme( $entityArgs );
		}
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
	 * Clears the cache
	 */
	public function clearCache() {
		$this->client->clearCache( $this->entity );
	}
}
