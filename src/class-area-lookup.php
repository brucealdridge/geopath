<?php

namespace geopath;

class area_lookup {
	public function __construct() {}

	/**
	 * @param $lat
	 * @param $lon
	 *
	 * @return string
	 */
	public function get_area( $lat, $lon ) {
		// make a get request to get the area for the location from nominatim.
		$response = wp_remote_get( "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=14&addressdetails=1" );

		// the response is a json object with the area data
		$place = wp_remote_retrieve_body( $response );
		$place = json_decode( $place );

		// Construct a more usable location string
		$address = $place->address;
		$location = '';

		if (isset($address->town)) {
			$location .= $address->town;
		}

		if ($address->country_code === 'us' && isset($address->state)) {
			$location .= ', ' . $address->state;
		} elseif (isset($address->country)) {
			$location .= ', ' . $address->country;
		}

		return $location;

	}
}