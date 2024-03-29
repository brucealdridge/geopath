<?php

namespace geopath;

class owntracks {
	public $server_url;
	public $user;
	public $device;

	public function __construct($server_url, $user, $device) {
		$this->server_url = $server_url;
		$this->user = $user;
		$this->device = $device;
	}

	/**
	 * @param int $start_timestamp The start timestamp in UTC
	 * @param int $end_timestamp The end timestamp in UTC
	 *
	 * @return string
	 */
	public function request( int $start_timestamp, int $end_timestamp ) {

		// make a get request to get the historical weather for the timestamp for the location from owntracks.
		// here is a sample request
		// https://example.com/api/0/locations?from=2024-03-24T09:38:54.516Z&to=2024-03-26T21:38:54.516Z&format=linestring&user=bruce&device=phone
		$start_time = gmdate( 'Y-m-d\TH:i:s.v\Z', $start_timestamp );
		$end_time = gmdate( 'Y-m-d\TH:i:s.v\Z', $end_timestamp );
		$response = wp_remote_get( "{$this->server_url}/api/0/locations?from=$start_time&to=$end_time&format=geojson&user={$this->user}&device={$this->device}" );

		// the response is a geojson object with the location data, we can return the output directly for caching
		return wp_remote_retrieve_body( $response );
	}
}