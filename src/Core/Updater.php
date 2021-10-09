<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Core;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Configuration;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Http\Response;

class Updater {

	/**
	 * The Application
	 * @var Configuration
	 */
	protected $configuration;

	/**
	 * Updater constructor.
	 *
	 * @param Configuration $configuration
	 */
	public function __construct( $configuration ) {

		$this->configuration = $configuration;

		if ( ! function_exists( 'add_filter' ) ) {
			throw new \Exception( 'The library is not supported. Please make sure you initialize it within WordPress environment.' );
		}

		add_filter( 'plugins_api', array( $this, 'modify_plugin_details' ), 10, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_plugins_transient' ), 10, 1 );
		add_action( 'in_plugin_update_message-' . $this->configuration->getEntity()->getBasename(), array(
			$this,
			'modify_plugin_update_message'
		), 10, 2 );
	}

	/**
	 * Called when WP updates the 'update_plugins' site transient. Used to inject CV plugin update info.
	 *
	 * @param $plugin_data
	 * @param $response
	 */
	public function modify_plugin_update_message( $plugin_data, $response ) {

		$purchaseUrl = $this->configuration->getEntity()->getPurchaseUrl();
		$settingsUrl = $this->configuration->getEntity()->getSettingsUrl();

		echo '<style>.cv-indent-left {padding-left: 25px;}</style>';

		if ( ! empty( $this->configuration->getEntity()->getActivationToken() ) ) {
			$license = $this->configuration->getClient()->prepareValidateLicense( $this->configuration->getEntity()->getActivationToken() );
			$expired = isset( $license['license']['is_expired'] ) ? (bool) $license['license']['is_expired'] : true;
			if ( $expired ) {
				$expires_at = isset( $license['license']['expires_at'] ) ? $license['license']['expires_at'] : '';
				$expires_at = $expires_at ? Utilities::getFormattedDate( $expires_at ) : array();
				$expired_on = isset( $expires_at['default_format'] ) ? 'Your license expired on <u>' . $expires_at['default_format'] . '</u>' : 'Your license expired';
				echo '<br/>';
				echo sprintf(
					'<strong class="cv-indent-left">Important</strong>: %s. To continue with updates please <a target="_blank" href="%s">purchase new license key</a> and activate it in the <a href="%s">settings</a> page.',
					$expired_on,
					$purchaseUrl,
					$settingsUrl
				);
			}
		} else {
			echo '<br/>';
			echo sprintf(
				'<strong class="cv-indent-left">Important</strong>: To enable updates, please activate your license key on the <a href="%s">settings</a> page. Need license key? <a target="_blank" href="%s">Purchase one now!</a>.',
				$purchaseUrl,
				$settingsUrl
			);
		}
	}

	/**
	 *  Returns the plugin data visible in the 'View details' popup
	 *
	 * @param $transient
	 *
	 * @return mixed
	 */
	public function modify_plugins_transient( $transient ) {

		// bail early if no response (error)
		if ( ! isset( $transient->response ) ) {
			return $transient;
		}

		// force-check (only once)
		$force_check = isset( $_GET['force-check'] ) && 1 === (int) $_GET['force-check'];

		// fetch updates (this filter is called multiple times during a single page load)
		$update = $this->check_update( $force_check );

		// append
		if ( is_array( $update ) && isset( $update['new_version'] ) ) {
			$transient->response[ $this->configuration->getEntity()->getBasename() ] = (object) $update;
		}

		// return
		return $transient;
	}

	/**
	 * Gather the plugin detials
	 *
	 * @param $result
	 * @param null $action
	 * @param null $args
	 *
	 * @return object
	 */
	public function modify_plugin_details( $result, $action = null, $args = null ) {

		// Only for 'plugin_information' action
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		// Find plugin via slug
		if ( isset( $args->slug ) ) {
			$slug = $args->slug;
		} elseif ( isset( $args['slug'] ) ) {
			$slug = $args['slug'];
		} else {
			$slug = '';
		}
		if ( $this->configuration->getEntity()->getSlug() !== $slug ) {
			return $result;
		}

		// query api
		$response = $this->configuration->getClient()->prepareInfo( $this->configuration->getEntity(), 'wp' );
		if ( empty( $response ) ) {
			return $result;
		}

		$response = $this->format_plugin_details( $response );
		if ( ! $response ) {
			return $result;
		}

		return (object) $response;
	}


	/**
	 * Check for updates.
	 *
	 * @param $force
	 *
	 * @return array
	 */
	private function check_update( $force = false ) {

		$update = $this->configuration->getClient()->prepareInfo( $this->configuration->getEntity(), 'wp', true, $force );

		if ( ! empty( $update ) && is_array( $update ) ) {
			$update = $this->format_plugin_update( $update );
		}

		return $update;
	}

	/**
	 * Format site update
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private function format_plugin_update( $data ) {

		if ( empty( $data ) ) {
			return array();
		}

		if ( $data instanceof Response ) {
			$data = $data->getData();
		}

		$update      = array();
		$new_version = isset( $data['details']['stable_tag'] ) ? $data['details']['stable_tag'] : '';
		$tested      = isset( $data['details']['tested'] ) ? $data['details']['tested'] : '';

		if ( version_compare( $new_version, $this->configuration->getEntity()->getVersion() ) === 1 ) {
			$update = array(
				'slug'        => $this->configuration->getEntity()->getSlug(),
				'plugin'      => $this->configuration->getEntity()->getBasename(),
				'url'         => $this->configuration->getEntity()->getPurchaseUrl(),
				'new_version' => $new_version,
				'tested'      => $tested,
			);
			if ( isset( $data['download_url'] ) && ! empty( $data['download_url'] ) ) {
				global $wp_version;
				$params = array(
					'meta' => array(
						'wp_version'  => $wp_version,
						'php_version' => PHP_VERSION,
						'web_server'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
					)
				);

				$update['package'] = add_query_arg( $params, $data['download_url'] );
			}
		}

		return $update;
	}

	/**
	 * Format the plugin details
	 *
	 * @param array $data
	 *
	 * @return null
	 */
	private function format_plugin_details( $data ) {

		if ( empty( $data ) ) {
			return null;
		}

		if ( empty( $data['details'] ) || ! is_array( $data['details'] ) ) {
			return null;
		}

		return $data['details'];
	}
}