<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Models;

class Model {

	/**
	 * The plugin ID
	 * @var int
	 */
	protected $id;

	/**
	 * The name
	 * @var mixed|null
	 */
	protected $name;

	/**
	 * The basename
	 * @var mixed|null
	 */
	protected $basename;

	/**
	 * The __FILE__ constant of the main plugin file
	 * @var mixed|string
	 */
	protected $file;

	/**
	 * The version
	 * @var mixed|null
	 */
	protected $version;

	/**
	 * Url purchase
	 * @var string
	 */
	protected $purchaseUrl;

	/**
	 * Url purchase
	 * @var string
	 */
	protected $settingsUrl;

	/**
	 * The prefix
	 * @var string
	 */
	protected $prefix;

	/**
	 * Constructor
	 *
	 * @param $id
	 */
	public function __construct( $args ) {
		$this->id          = isset( $args['id'] ) ? $args['id'] : null;
		$this->name        = isset( $args['name'] ) ? $args['name'] : null;
		$this->basename    = isset( $args['basename'] ) ? $args['basename'] : null;
		$this->file        = isset( $args['file'] ) ? $args['file'] : '';
		$this->version     = isset( $args['version'] ) ? $args['version'] : null;
		$this->purchaseUrl = isset( $args['url_settings'] ) ? $args['url_settings'] : null;
		$this->settingsUrl = isset( $args['url_purchase'] ) ? $args['url_purchase'] : null;
		$this->prefix      = isset( $args['prefix'] ) ? $args['prefix'] : 'dlm';
	}

	/**
	 * Returns the license/product key.
	 * @return string|bool
	 */
	public function getLicenseKey() {
		$key = $this->getLicenseKeyOptionName();

		return get_option( $key );
	}

	/**
	 * Returns the data option name
	 * @return string
	 */
	public function getLicenseKeyOptionName() {
		$name = sprintf( '%s_product_key_%s', $this->prefix, $this->id );

		return apply_filters( 'dlm_updater_license_key_option_name', $name, $this );
	}

	/**
	 * Set the license key
	 *
	 * @param $key
	 */
	public function setLicenseKey( $key ) {
		$name = $this->getLicenseKeyOptionName();
		update_option( $name, $key );
	}

	/**
	 * Deletes the license key
	 */
	public function deleteLicenseKey() {
		$name = $this->getLicenseKeyOptionName();
		delete_option( $name );
	}

	/**
	 * Retrieve the activation token
	 *
	 * @return mixed|void
	 */
	public function getActivationToken() {
		$name = $this->getActivationTokenOptionName();

		return get_option( $name );
	}

	/**
	 * Returns the data option name
	 * @return string
	 */
	public function getActivationTokenOptionName() {
		$name = sprintf( '%s_activation_token_%s', $this->prefix, $this->id );

		return apply_filters( 'dlm_updater_activation_token_option_name', $name, $this );
	}

	/**
	 * Set the activation token
	 *
	 * @param $token
	 */
	public function setActivationToken( $token ) {
		$name = $this->getActivationTokenOptionName( $token );
		update_option( $name, $token );
	}

	/**
	 * Delete the activation token
	 */
	public function deleteActivationToken() {
		$name = $this->getActivationTokenOptionName();
		delete_option( $name );
	}

	/**
	 * Return the ID
	 * @return int|mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Return the __FILE__
	 * @return mixed|string
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * Return the purchase url
	 * @return mixed|string
	 */
	public function getPurchaseUrl() {
		return $this->purchaseUrl;
	}

	/**
	 * Return the settings
	 * @return mixed|string
	 */
	public function getSettingsUrl() {
		return $this->settingsUrl;
	}

	/**
	 * Return the basename
	 * @return mixed|string
	 */
	public function getBasename() {
		return $this->basename;
	}

	/**
	 * Return the version
	 * @return mixed|string
	 */
	public function getVersion() {
		return $this->version;
	}

	/**
	 * Return the name
	 * @return mixed|string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Return the slug
	 *
	 * @return mixed|string|null
	 */
	public function getSlug() {
		$basename = $this->getBasename();
		$parts    = explode( '/', $basename );

		return isset( $parts[0] ) ? $parts[0] : null;
	}

}
