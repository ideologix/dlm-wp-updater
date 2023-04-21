<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Core;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Http\Client;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Plugin;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Theme;


class Configuration {

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
	 * The default labels
	 * @var array
	 */
	protected $labels;

	/**
	 * Whether to mask the license key input
	 * @var bool
	 */
	protected $mask_key_input;

	/**
	 * Application constructor.
	 *
	 * @param $args
	 */
	public function __construct( $args ) {

		// The context
		$context = ! empty( $args['context'] ) && in_array( $args['context'], array(
			'theme',
			'plugin'
		) ) ? $args['context'] : 'plugin';

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

		// Setup the labels
		$this->labels = $this->getDefaultLabels();
		if ( ! empty( $args['labels'] ) && is_array( $args['labels'] ) ) {
			$this->labels = array_merge( $this->labels, $args['labels'] );
		}

		//Other...
		if ( isset( $args['mask_key_input'] ) ) {
			$this->mask_key_input = (bool) $args['mask_key_input'];
		}
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

	/**
	 * Whether the license key input is masked
	 * @return bool
	 */
	public function isKeyInputMasked() {
		return $this->mask_key_input;
	}

	/**
	 * Return the default labels
	 * @return string[]
	 */
	public function getDefaultLabels() {
		return [
			'activator.no_permissions'                   => 'Sorry, you dont have enough permissions to manage those settings.',
			'activator.license_removed'                  => 'License removed.',
			'activator.invalid_action'                   => 'Invalid action.',
			'activator.invalid_license_key'              => 'Please provide valid product key.',
			'activator.license_activated'                => 'Congrats! Your key is valid and your product will receive future updates',
			'activator.license_deactivated'              => 'The license key is now deactivated.',
			'activator.activation_permanent'             => 'License :status. Activation permanent.',
			'activator.activation_expires'               => 'License :status. Expires on :expires_at (:days_remaining days remaining).',
			'activator.activation_deactivated_permanent' => 'License :status. Deactivated on :deactivated_at (Valid permanently)',
			'activator.activation_deactivated_expires'   => 'License :status. Deactivated on :deactivated_at (:days_remaining days remaining)',
			'activator.activation_expired_purchase'      => 'Your license is :status. To get regular updates and support, please <purchase_link>purchase the product</purchase_link>.',
			'activator.activation_purchase'              => 'To get regular updates and support, please <purchase_link>purchase the product</purchase_link>.',
			'activator.word_valid'                       => 'valid',
			'activator.word_expired'                     => 'expired',
			'activator.word_expired_or_invalid'          => 'expired or invalid',
			'activator.word_deactivate'                  => 'Deactivate',
			'activator.word_activate'                    => 'Activate',
			'activator.word_reactivate'                  => 'Reactivate',
			'activator.word_purchase'                    => 'Purchase',
			'activator.word_renew'                       => 'Renew',
			'activator.word_remove'                      => 'Remove',
			'activator.word_product_key'                 => 'Product Key',
			'activator.help_remove'                      => 'Remove the license key',
			'activator.help_product_key'                 => 'Enter your product key',
		];
	}

	/**
	 * Returns a label
	 *
	 * @param $key
	 *
	 * @return mixed|string
	 */
	public function label( $key ) {
		return isset( $this->labels[ $key ] ) ? $this->labels[ $key ] : $key;
	}
}
