<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Core;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Http\Response;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Models\Plugin;

class Activator {

	/**
	 * The Application
	 * @var Configuration
	 */
	protected $configuration;

	/**
	 * Is plugin cache clear required?
	 * @var bool
	 */
	protected $clearCache = false;

	/**
	 * Is http post?
	 * @var bool
	 */
	protected $is_http_post;

	/**
	 * Activator constructor.
	 *
	 * @param Configuration $configuration
	 */
	public function __construct( $configuration ) {

		$this->configuration = $configuration;

		if ( isset( $_GET[ $this->configuration->getPrefix() . '_clear_cache' ] ) ) {
			$this->clearCache = true;
		}

		$this->is_http_post = $_SERVER['REQUEST_METHOD'] === 'POST';

		if ( is_a( $this->configuration->getEntity(), Plugin::class ) ) {
			$file = $this->configuration->getEntity()->getFile();
			register_activation_hook( $file, array( $this, 'handleAfterActivation' ) );
		} else {
			add_action( 'init', array( $this, 'handleAfterActivation' ) );
		}

		add_action( $this->getAdminPostHookName(), array( $this, 'handleLicenseActivation' ) );
		add_action( 'init', array( $this, 'handleLegacyMigration' ) );
	}

	/**
	 * Return the admin post hook name
	 * @return string
	 */
	private function getAdminPostHookName( $withHook = true ) {
		$hook = $withHook ? 'admin_post_' : '';

		return sprintf( '%sdlm_activate_license_%s', $hook, $this->configuration->getEntity()->getId() );
	}

	/**
	 * Migrate legacy license.
	 * @deprecated
	 */
	public function handleLegacyMigration() {

		// 1. Bail if POST request
		if ( $this->is_http_post ) {
			return;
		}

		// 2. Bail if not /wp-admin
		if ( ! is_admin() ) {
			return;
		}

		// 3. Bail if unprivileged
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$licenseKey      = $this->configuration->getEntity()->getLicenseKey();
		$activationToken = $this->configuration->getEntity()->getActivationToken();

		// 4. Bail if license key empty or not the XXXX-XXXX-XXXX-XXXX format.
		if ( empty( $licenseKey ) || 19 !== strlen( trim( $licenseKey ) ) ) {
			return;
		}

		// 5. Bail if activation token is set
		if ( false !== $activationToken ) {
			return;
		}

		// Finally, Call the API to obtain activation token.
		$result = $this->activateLicense( $licenseKey );

		// Set the token, if any. IF license is expired, remove license key.
		if ( ! $result->isError() ) {
			$token = $result->getData( 'token' );
			if ( ! empty( $token ) ) {
				$this->configuration->getEntity()->setActivationToken( $token );
				delete_site_transient( 'update_plugins' );
			}
		} else {
			$codes = array(
				'dlm_rest_license_expired',
				'dlm_rest_license_disabled',
				'dlm_rest_license_activation_limit_reached',
				'wlm_rest_license_expired',
				'wlm_rest_license_disabled',
				'wlm_rest_license_activation_limit_reached'
			);
			if ( in_array( $result->getCode(), $codes ) ) {
				$this->configuration->getEntity()->deleteLicenseKey();
			}
			error_log( 'CodeVerve Activation: ' . $result->getError() );
		}
	}

	/**
	 * Handle the plugin activation
	 *  - Clear cache.
	 */
	public function handleAfterActivation() {
		$this->doClearCache();
	}

	/**
	 * Handle the license activation
	 * @return void
	 */
	public function handleLicenseActivation() {

		// Verify if the request is HTTP Post
		if ( ! $this->is_http_post ) {
			return;
		}

		// Determine the action type
		$data = $this->getPostData();

		$action     = isset( $data['action'] ) ? $data['action'] : null;
		$licenseKey = isset( $data['license_key'] ) ? trim( sanitize_text_field( $data['license_key'] ) ) : null;
		$plugin_id  = isset( $data['plugin_id'] ) ? (int) $data['plugin_id'] : null;

		// Check the plugin
		if ( (int) $plugin_id !== (int) $this->configuration->getEntity()->getId() ) {
			return;
		}

		// Check the permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->setFlashMessage( 'error', __( 'Sorry, you dont have enough permissions to manage those settings.' ) );
			$this->redirectBack();

			return;
		}

		// Check the actions
		if ( ! in_array( $action, array( 'activate', 'deactivate' ) ) ) {
			$this->setFlashMessage( 'error', __( 'Invalid action.' ) );
			$this->redirectBack();

			return;
		}

		// Validate license key
		if ( empty( $licenseKey ) ) {
			$this->setFlashMessage( 'error', __( 'Please provide valid product key.' ) );
			$this->redirectBack();

			return;
		}

		if ( 'activate' === $action ) {
			$result = $this->activateLicense( $licenseKey );
			if ( ! $result->isError() ) {
				$this->clearCache = true;
				$this->configuration->getEntity()->setLicenseKey( $licenseKey );
				$this->configuration->getEntity()->setActivationToken( $result->getData( 'token' ) );
				$this->setFlashMessage( 'success', __( 'Congrats! Your key is valid and your product will receive future updates.' ) );
			} else {
				$this->configuration->getEntity()->deleteLicenseKey();
				$this->configuration->getEntity()->deleteActivationToken();
				$message = $result->getError();
				if ( ! empty( $message ) ) {
					$this->setFlashMessage( 'error', $message );
				}
			}
		} elseif ( 'deactivate' === $action ) {
			$token  = $this->configuration->getEntity()->getActivationToken();
			$result = $this->deactivateLicense( $token );
			$this->configuration->getEntity()->deleteLicenseKey();
			$this->configuration->getEntity()->deleteActivationToken();
			if ( ! $result->isError() ) {
				$this->clearCache = true;
				$this->setFlashMessage( 'success', __( 'The license key is now deactivated and removed.' ) );
			} else {
				$message = $result->getError();
				if ( ! empty( $message ) ) {
					$this->setFlashMessage( 'error', $message );
				}
			}
		}
		$this->redirectBack();
	}

	/**
	 * Render the activation form
	 */
	public function renderActivationForm( $args = array() ) {

		/**
		 * Purges activation cache.
		 */
		if ( $this->clearCache ) {
			$this->doClearCache();
		}

		/**
		 * Print flashed messages.
		 */
		$this->printFlashedMessage();

		/**
		 * Find existing license?
		 */
		$token          = $this->configuration->getEntity()->getActivationToken();
		$license        = $this->configuration->getClient()->prepareValidateLicense( $token );
		$is_expired     = isset( $license['license']['is_expired'] ) ? (bool) $license['license']['is_expired'] : true;
		$deactivated_at = isset( $license['deactivated_at'] ) ? $license['deactivated_at'] : false;
		$expires_at     = isset( $license['license']['expires_at'] ) ? $license['license']['expires_at'] : '';
		$license_key    = isset( $license['license']['license_key'] ) ? $license['license']['license_key'] : '';
		$readonly       = ! empty( $license_key ) && ! empty( $license['token'] ) ? 'readonly' : '';

		/**
		 * Setup the activation form text
		 */
		if ( ! $is_expired ) {

			$expires_at = Utilities::getFormattedDate( $expires_at );
			if ( empty( $deactivated_at ) || is_null( $deactivated_at ) ) {
				$message = sprintf( 'License %s. Expires on %s (%s days remaining)', '<span class="dlm-success">valid</span>', $expires_at['default_format'], $expires_at['remaining_days'] );
				$button  = __( 'Deactivate' );
				$action  = 'deactivate';
			} else {
				$deactiv_date = Utilities::getFormattedDate( $deactivated_at );
				$message      = sprintf( 'License %s. Deactivated on %s (%s days remaining)', '<span class="dlm-warning">valid</span>', $deactiv_date['default_format'], $expires_at['remaining_days'] );
				$button       = __( 'Reactivate' );
				$action       = 'activate';
			}

		} else {
			if ( $token ) {
				$message = sprintf(
					__( 'Your license is %s. To get regular updates and support, please <a href="%s" target="_blank">purchase the product</a>.' ),
					'<span class="dlm-error">expired</span> or invalid',
					$this->configuration->getEntity()->getPurchaseUrl()
				);
			} else {
				$message = sprintf(
					__( 'To get regular updates and support, please <a href="%s" target="_blank">purchase the product</a>.' ),
					$this->configuration->getEntity()->getPurchaseUrl()
				);
			}
			$button = __( 'Activate' );
			$action = 'activate';
		}

		/**
		 * Print the activation and deactivation form
		 */
		echo '<form method="POST" action="' . admin_url( 'admin-post.php' ) . '">';
		echo '<input type="hidden" name="action" value="' . $this->getAdminPostHookName( false ) . '">';
		echo '<div class="dlm-activator-row">';
		echo sprintf( '<label>%s</label>', __( 'Product Key' ) );
		echo sprintf( '<input type="text" %s name="%s" value="%s" placeholder="%s">', $readonly, $this->getFieldName( 'license_key' ), $license_key, __( 'Enter your product key' ) );
		if ( ! empty( $message ) ) {
			echo sprintf( '<p class="dlm-info">%s</p>', $message );
		}
		echo '</div>';
		echo '<div class="dlm-activator-row">';
		echo sprintf( '<input type="hidden" name="%s" value="%s">', $this->getFieldName( 'action' ), $action );
		echo sprintf( '<input type="hidden" name="%s" value="%s">', $this->getFieldName( 'plugin_id' ), $this->configuration->getEntity()->getId() );
		echo sprintf( '<button type="submit" class="%s" name="cv_activator" value="1">%s</button>', $action === 'activate' ? 'button-primary' : 'button', $button );
		if ( $is_expired ) {
			echo sprintf( '&nbsp;<a href="%s" class="button-secondary" target="_blank">%s</a>', $this->configuration->getEntity()->getPurchaseUrl(), 'Purchase' );
		}
		echo '</div>';
		echo '</form>';

		/**
		 * If it is the http post, clear the window history.
		 */
		if ( $this->is_http_post ) {
			echo '<script>';
			echo 'if (window.history.replaceState) {
                        window.history.replaceState(null, null, window.location.href);
                    }';
			echo '</script>';
		}

		/**
		 * Some basic styling
		 * @TODO: Move it in a file.
		 */
		echo '<style>
                .dlm-activator-row input[type=text] {
                    width: 100%;
                }
                .dlm-activator-row label {
                    display: block;
                    width: 100%;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .dlm-activator-row {
                    width: 100%;
                    margin-bottom: 10px;
                }
                .dlm-info {
                    font-style: italic;
                }
                .dlm-error, .dlm-success {
                    font-weight: bold;
                }
                .dlm-success {
                    color: green;
                }
                .dlm-error {
                    color: red;
                }
            </style>';

	}

	/**
	 * Redirect back.
	 * @return void
	 */
	private function redirectBack() {
		header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
		exit;
	}

	/**
	 * Perform license activation
	 *
	 * @param $key
	 *
	 * @return array|Response|\WP_Error
	 */
	private function activateLicense( $key ) {

		global $wp_version;

		$params = apply_filters( 'dlm_wp_updater_activate_meta', array(
			'label'    => home_url(),
			'software' => $this->configuration->getEntity()->getId(),
			'meta'     => array(
				'wp_version'  => $wp_version,
				'php_version' => PHP_VERSION,
				'web_server'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
			)
		), $key, $this->configuration );

		return $this->configuration->getClient()->activateLicense( $key, $params );
	}

	/**
	 * Deactivate license
	 *
	 * @param $token
	 *
	 * @return array|Response|\WP_Error
	 */
	private function deactivateLicense( $token ) {

		$meta = apply_filters( 'dlm_wp_updater_deactivate_meta', array(
			'software' => $this->configuration->getEntity()->getId(),
		), $token, $this->configuration );

		return $this->configuration->getClient()->deactivateLicense( $token, $meta );
	}

	/**
	 * Return the request data.
	 */
	private function getPostData() {

		if ( $this->is_http_post ) {
			if ( isset( $_POST['dlm'][ $this->configuration->getEntity()->getId() ] ) ) {
				return $_POST['dlm'][ $this->configuration->getEntity()->getId() ];
			}
		}

		return null;
	}

	/**
	 * The field id.
	 *
	 * @param $key
	 *
	 * @return string
	 */
	private function getFieldName( $key ) {
		return 'dlm[' . $this->configuration->getEntity()->getId() . '][' . $key . ']';
	}

	/**
	 * Flash message
	 *
	 * @param $type
	 * @param $message
	 */
	private function setFlashMessage( $type, $message ) {
		set_transient( 'cv_flash', array( 'type' => $type, 'message' => $message ), 320 );
	}

	/**
	 * Retrieve flashed message
	 *
	 * @param bool $remove
	 *
	 * @return mixed
	 */
	private function getFlashedMessage( $remove = true ) {
		$message = get_transient( 'cv_flash' );

		if ( $remove ) {
			delete_transient( 'cv_flash' );
		}

		return $message;
	}

	/**
	 * Print the flashed message
	 */
	private function printFlashedMessage() {
		$message = $this->getFlashedMessage();
		if ( empty( $message ) ) {
			return;
		}
		echo sprintf( '<div class="notice notice-%s is-dismissible"><p>%s</p><button onclick="this.parentNode.parentNode.removeChild(this.parentNode);" type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>', $message['type'], $message['message'] );
	}

	/**
	 * Destroy the cached data.
	 */
	private function doClearCache() {
		$this->configuration->clearCache();
	}

}
