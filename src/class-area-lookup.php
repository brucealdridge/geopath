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
		// make a get request to get the area for the location from openmeteo.
		// here is a sample request
		// https://openmeteo.org/api/0.1/area?lat=51.5074&lon=0.1278
		$response = wp_remote_get( "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=14&addressdetails=1" );

		// the response is a json object with the area data, we can return the output directly
		// return the place name
		$place = wp_remote_retrieve_body( $response );
		$place = json_decode( $place );
		return $place->display_name;

	}
}