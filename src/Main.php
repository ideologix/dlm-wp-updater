<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP;

use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Activator;
use IdeoLogix\DigitalLicenseManagerUpdaterWP\Core\Updater;

class Main {

	/**
	 * The application
	 * @var Application
	 */
	protected $application;

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
	public function __constructor( $args, $config = array() ) {
		$this->application = new Application( $args );
		$this->activator   = new Activator( $this->application );
		$this->updater     = new Updater( $this->application );
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
	 * Returns the application instance
	 * @return Application
	 */
	public function getApplication() {
		return $this->application;
	}
}
