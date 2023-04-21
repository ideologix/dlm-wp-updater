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
			add_action( 'after_switch_theme', array( $this, 'handleAfterActivation' ) );
		}

		add_action( $this->getAdminPostHookName(), array( $this, 'handleLicenseActivation' ) );
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
		$is_remove  = isset( $data['remove'] ) ? (bool) $data['remove'] : false;

		// Check the plugin
		if ( (int) $plugin_id !== (int) $this->configuration->getEntity()->getId() ) {
			return;
		}

		// Check the permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->setFlashMessage( 'error', $this->configuration->label( 'activator.no_permissions' ) );
			$this->redirectBack();

			return;
		}

		if ( $is_remove ) {
			$this->configuration->getEntity()->deleteLicenseKey();
			$this->configuration->getEntity()->deleteActivationToken();
			$this->setFlashMessage( 'success', $this->configuration->label( 'activator.license_removed' ) );
			$this->redirectBack();

			return;
		}

		// Check the actions
		if ( ! in_array( $action, array( 'activate', 'deactivate', 'reactivate' ) ) ) {
			$this->setFlashMessage( 'error', $this->configuration->label( 'activator.invalid_action' ) );
			$this->redirectBack();

			return;
		}

		// Validate license key
		if ( empty( $licenseKey ) ) {
			$this->setFlashMessage( 'error', $this->configuration->label( 'activator.invalid_license_key' ) );
			$this->redirectBack();

			return;
		}

		if ( 'activate' === $action ) {
			$result = $this->activateLicense( $licenseKey );
			if ( ! $result->isError() ) {
				$this->clearCache = true;
				$this->configuration->getEntity()->setLicenseKey( $licenseKey );
				$this->configuration->getEntity()->setActivationToken( $result->getData( 'token' ) );
				$this->setFlashMessage( 'success', $this->configuration->label( 'activator.license_activated' ) );
			} else {
				$this->configuration->getEntity()->deleteLicenseKey();
				$this->configuration->getEntity()->deleteActivationToken();
				$message = $result->getError();
				if ( ! empty( $message ) ) {
					$this->setFlashMessage( 'error', $message );
				}
			}
		} elseif ( 'reactivate' === $action ) {
			$token  = $this->configuration->getEntity()->getActivationToken();
			$result = $this->activateLicense( $licenseKey, $token );
			if ( ! $result->isError() ) {
				$this->clearCache = true;
				$this->configuration->getEntity()->setLicenseKey( $licenseKey );
				$this->configuration->getEntity()->setActivationToken( $result->getData( 'token' ) );
				$this->setFlashMessage( 'success', $this->configuration->label( 'activator.license_activated' ) );
			} else {
				$message = $result->getError();
				if ( ! empty( $message ) ) {
					$this->setFlashMessage( 'error', $message );
				}
			}
		} elseif ( 'deactivate' === $action ) {
			$token  = $this->configuration->getEntity()->getActivationToken();
			$result = $this->deactivateLicense( $token );
			if ( ! $result->isError() ) {
				$this->clearCache = true;
				$this->setFlashMessage( 'success', $this->configuration->label( 'activator.license_deactivated' ) );
			} else {
				$message          = $result->getError();
				$this->clearCache = true;
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
		$license        = $token ? $this->configuration->getClient()->prepareValidateLicense( $token, true, true ) : array();
		$deactivated_at = isset( $license['deactivated_at'] ) ? $license['deactivated_at'] : false;
		$is_expired     = isset( $license['license']['is_expired'] ) ? (bool) $license['license']['is_expired'] : true;
		$is_deactivated = ! empty( $deactivated_at );
		$expires_at     = isset( $license['license']['expires_at'] ) ? $license['license']['expires_at'] : '';
		$license_key    = isset( $license['license']['license_key'] ) ? $license['license']['license_key'] : '';
		$readonly       = ! empty( $license_key ) && ! empty( $license['token'] ) ? 'readonly' : '';

		/**
		 * Setup the activation form text
		 */
		if ( ! $is_expired ) {

			$is_permanent = '0000-00-00 00:00:00' === $expires_at || empty( $expires_at );
			$word_valid   = $this->configuration->label( 'activator.word_valid' );

			$expires_at = Utilities::getFormattedDate( $expires_at );
			if ( ! $is_deactivated ) {
				$word_deactivate = $this->configuration->label( 'activator.word_deactivate' );
				if ( $is_permanent ) {
					$status  = sprintf( '<span class="dlm-success">%s</span>', $word_valid );
					$message = str_replace( ':status', $status, $this->configuration->label( 'activator.activation_permanent' ) );
				} else {
					$days_remaining = isset( $expires_at['remaining_days'] ) ? $expires_at['remaining_days'] : null;
					$status         = sprintf( '<span class="%s">%s</span>', ( $days_remaining && $days_remaining > 30 ? 'dlm-success' : 'dlm-warning' ), $word_valid );
					$message        = str_replace(
						[ ':status', ':expires_at', ':days_remaining' ],
						[ $status, $expires_at['default_format'], $days_remaining ],
						$this->configuration->label( 'activator.activation_expires' )
					);
				}
				$button = $word_deactivate;
				$action = 'deactivate';
			} else {
				$deactiv_date    = Utilities::getFormattedDate( $deactivated_at );
				$word_reactivate = $this->configuration->label( 'activator.word_reactivate' );

				if ( $is_permanent ) {
					$status  = sprintf( '<span class="dlm-warning">%s</span>', $word_valid );
					$message = str_replace(
						[ ':status', ':deactivated_at' ],
						[ $status, $deactiv_date['default_format'] ],
						$this->configuration->label( 'activator.activation_deactivated_permanent' )
					);
				} else {
					$days_remaining = isset( $expires_at['remaining_days'] ) ? $expires_at['remaining_days'] : null;
					$status         = sprintf( '<span class="%s">%s</span>', 'dlm-warning', $word_valid );
					$message        = str_replace(
						[ ':status', ':deactivated_at', ':days_remaining' ],
						[ $status, $deactiv_date['default_format'], $days_remaining ],
						$this->configuration->label( 'activator.activation_deactivated_expires' )
					);
				}
				$button = $word_reactivate;
				$action = 'reactivate';
			}
		} else {

			$word_expired_or_invalid = $this->configuration->label( 'activator.word_expired_or_invalid' );
			$word_activate           = $this->configuration->label( 'activator.word_activate' );
			$status                  = sprintf( '<span class="dlm-error">%s</span>', $word_expired_or_invalid );

			if ( $token ) {
				$message = str_replace(
					[
						':status',
						'<purchase_link>',
						'</purchase_link>',
					],
					[
						$status,
						sprintf( '<a href="%s" target="_blank">', $this->configuration->getEntity()->getPurchaseUrl() ),
						'</a>'
					],
					$this->configuration->label( 'activator.activation_expired_purchase' )
				);
			} else {
				$message = str_replace(
					[
						'<purchase_link>',
						'</purchase_link>',
					],
					[
						sprintf( '<a href="%s" target="_blank">', $this->configuration->getEntity()->getPurchaseUrl() ),
						'</a>'
					],
					$this->configuration->label( 'activator.activation_purchase' )
				);
			}
			$button = $word_activate;
			$action = 'activate';
		}

		/**
		 * Print the activation and deactivation form
		 */
		echo '<form method="POST" action="' . esc_url(admin_url( 'admin-post.php' )) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr($this->getAdminPostHookName( false )) . '">';
		echo '<div class="dlm-activator-row">';
		echo sprintf( '<label>%s</label>', $this->configuration->label('activator.word_product_key') );
		echo sprintf(
			'<input type="%s" %s name="%s" value="%s" placeholder="%s">',

			$this->configuration->isKeyInputMasked() ? 'password' : 'text',
			$readonly,
			$this->getFieldName( 'license_key' ),
			$license_key,
			$this->configuration->label('activator.help_product_key')
		);
		if ( ! empty( $message ) ) {
			echo sprintf( '<p class="dlm-info">%s</p>', $message );
		}
		echo '</div>';
		echo '<div class="dlm-activator-row">';
		echo sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr($this->getFieldName( 'action' )), esc_attr($action) );
		echo sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr($this->getFieldName( 'plugin_id' )), esc_attr($this->configuration->getEntity()->getId()) );
		echo sprintf( '<button type="submit" class="%s" name="%s" value="1">%s</button>', in_array( $action, array( 'activate', 'reactivate' ) ) ? 'button-primary' : 'button', esc_attr($this->getFieldName( 'activator' )), esc_attr($button) );
		if ( $is_deactivated ) {
			echo sprintf(
				'&nbsp;<button type="submit" class="%s" name="%s" value="1" title="%s">%s</button>',
				'button',
				$this->getFieldName( 'remove' ),
				$this->configuration->label('activator.help_remove'),
				$this->configuration->label('activator.word_remove')
			);
		}
		if ( $is_expired ) {
			echo sprintf(
				'&nbsp;<a href="%s" class="button-secondary" target="_blank">%s</a>',
				$this->configuration->getEntity()->getPurchaseUrl(),
				$this->configuration->label('activator.word_purchase')
			);
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
                .dlm-activator-row input[type=text], .dlm-activator-row input[type=password] {
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
                .dlm-error, .dlm-success,.dlm-warning {
                    font-weight: bold;
                }
                .dlm-success {
                    color: green;
                }
                .dlm-error {
                    color: red;
                }
                .dlm-warning {
                	color: #f19440;
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
	 * @param null $token
	 *
	 * @return array|Response|\WP_Error
	 */
	private function activateLicense( $key, $token = null ) {

		global $wp_version;

		$params = array(
			'label'    => home_url(),
			'software' => $this->configuration->getEntity()->getId(),
			'meta'     => array(
				'wp_version'  => $wp_version,
				'php_version' => PHP_VERSION,
				'web_server'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
			)
		);

		if ( ! is_null( $token ) ) {
			$params['token'] = $token;
		}

		$params = apply_filters( 'dlm_wp_updater_activate_meta', $params, $key, $this->configuration );

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
