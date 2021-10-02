<?php

namespace IdeoLogix\DigitalLicenseManagerUpdaterWP\Core;

use DateTime;

class Utilities {

	/**
	 * Returns formatted date
	 *
	 * @param $date
	 *
	 * @return array|false
	 */
	public static function getFormattedDate( $date ) {
		$result = false;

		if ( ! empty( $date ) ) {
			$date_format              = get_option( 'date_format' );
			$dateTime                 = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
			$result                   = array();
			$result['default_format'] = $dateTime->format( $date_format );
			$result['remaining_days'] = self::getDaysDifference( $date, date( 'Y-m-d H:i:s' ) );
		}

		return $result;

	}

	/**
	 * Calculate days difference
	 *
	 * @param $ymd1
	 * @param $ymd2
	 *
	 * @return string
	 */
	public static function getDaysDifference( $ymd1, $ymd2 ) {
		if ( empty( $ymd1 ) ) {
			return '';
		}
		if ( empty( $ymd2 ) ) {
			return '';
		}
		$dt1  = DateTime::createFromFormat( 'Y-m-d H:i:s', $ymd1 );
		$dt2  = DateTime::createFromFormat( 'Y-m-d H:i:s', $ymd2 );
		$diff = $dt1->diff( $dt2, false );

		return $diff->days;
	}


	/**
	 * Extract specific array keys
	 * @param $arr
	 * @param $keys
	 *
	 * @return array
	 */
	public static function arrayOnly($arr, $keys) {
		$newArr = array();
		foreach($keys as $key) {
			if(isset($arr[$key])) {
				$newArr[$key] = $arr[$key];
			}
		}
		return $newArr;
	}

}
