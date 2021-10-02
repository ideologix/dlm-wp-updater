<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Http;

class Response {

	/**
	 * The error code
	 * @var string
	 */
	protected $code = '';

	/**
	 * The error message
	 * @var string
	 */
	protected $error = '';

	/**
	 * The data
	 * @var array
	 */
	protected $data = array();

	/**
	 * The raw response
	 * @var array
	 */
	protected $raw;

	/**
	 * Set data
	 *
	 * @param $data
	 */
	public function setData( $data ) {
		$this->data = $data;
	}

	/**
	 * Return data
	 *
	 * @param string $key
	 *
	 * @return array|mixed|null
	 */
	public function getData( $key = '' ) {
		if ( ! empty( $key ) ) {
			return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
		} else {
			return $this->data;
		}
	}

	/**
	 * Is error?
	 * @return bool
	 */
	public function isError() {
		return ! empty( $this->error );
	}

	/**
	 * Set Error
	 *
	 * @param $message
	 */
	public function setError( $message ) {
		$this->error = $message;
	}

	/**
	 * Returns the error
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Set Error
	 *
	 * @param $code
	 */
	public function setCode( $code ) {
		$this->code = $code;
	}

	/**
	 * Returns the error
	 * @return string
	 */
	public function getCode() {
		return $this->code;
	}

	/**
	 * Get raw response
	 * @return array
	 */
	public function getRaw() {
		return $this->raw;
	}

	/**
	 * Set raw response
	 *
	 * @param $raw
	 */
	public function setRaw( $raw ) {
		$this->raw = $raw;
	}
}
