<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Activator;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Configuration;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Updater;

class Main {

	/**
	 * The configuration
	 * @var Configuration
	 */
	protected $configuration;

	/**
	 * The Activator
	 * @var Activator
	 */
	protected $activator;

	/**
	 * The Updater
	 * @var Updater
	 */
	protected $updater;

	/**
	 * Constructor
	 *
	 * @param $args
	 * @param array $config
	 *
	 * @throws \Exception
	 */
	public function __construct( $args, $config = array() ) {
		$this->configuration = new Configuration( $args );
		$this->activator     = new Activator( $this->configuration );
		$this->updater       = new Updater( $this->configuration );
	}

	/**
	 * Returns the Activator instance
	 * @return Activator
	 */
	public function getActivator() {
		return $this->activator;
	}

	/**
	 * Returns the Updater instance
	 * @return Updater
	 */
	public function getUpdater() {
		return $this->updater;
	}

	/**
	 * Returns the configuration instance
	 * @return Configuration
	 */
	public function getConfiguration() {
		return $this->configuration;
	}

	/**
	 * Set the activator
	 *
	 * @param $args
	 */
	public function setActivator( $args ) {
		if ( is_object( $args ) ) {
			$this->activator = $args;
		} elseif ( class_exists( $args ) ) {
			$this->activator = new $args( $this->configuration );
		}
	}

	/**
	 * Set the updater
	 *
	 * @param $args
	 */
	public function setUpdater( $args ) {
		if ( is_object( $args ) ) {
			$this->updater = $args;
		} elseif ( class_exists( $args ) ) {
			$this->updater = new $args( $this->configuration );
		}
	}

	/**
	 * Set the configuration instance
	 *
	 * @param $args
	 */
	public function setApplication( $args ) {
		$this->configuration = $args;
	}
}
