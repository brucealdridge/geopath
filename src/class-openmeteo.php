<?php

namespace geopath;

class openmeteo {

	/**
	 * @param $lon
	 * @param $lat
	 * @param $timestamp
	 *
	 * @return array
	 */
	public function request( $lon, $lat, $timestamp ) {
		$date = gmdate( 'Y-m-d', $timestamp);
		// make a get request to get the historical weather for the timestamp for the location from open-meteo.com
		$response = wp_remote_get( "https://archive-api.open-meteo.com/v1/archive?latitude=$lat&longitude=$lon&start_date=$date&end_date=$date&hourly=temperature_2m,relative_humidity_2m,dew_point_2m,apparent_temperature,precipitation,rain,weather_code,is_day" );
		// the response is a json object with the weather data, we can return the output directly


		$response = json_decode( wp_remote_retrieve_body($response), true );

		/**
		 * Sample response
		 * {"latitude":52.54833,"longitude":13.407822,"generationtime_ms":0.12695789337158203,"utc_offset_seconds":0,"timezone":"GMT","timezone_abbreviation":"GMT","elevation":38.0,"hourly_units":{"time":"iso8601","temperature_2m":"°C","relative_humidity_2m":"%","dew_point_2m":"°C","apparent_temperature":"°C","precipitation":"mm","rain":"mm","weather_code":"wmo code","is_day":""},"hourly":{"time":["2024-03-11T00:00","2024-03-11T01:00","2024-03-11T02:00","2024-03-11T03:00","2024-03-11T04:00","2024-03-11T05:00","2024-03-11T06:00","2024-03-11T07:00","2024-03-11T08:00","2024-03-11T09:00","2024-03-11T10:00","2024-03-11T11:00","2024-03-11T12:00","2024-03-11T13:00","2024-03-11T14:00","2024-03-11T15:00","2024-03-11T16:00","2024-03-11T17:00","2024-03-11T18:00","2024-03-11T19:00","2024-03-11T20:00","2024-03-11T21:00","2024-03-11T22:00","2024-03-11T23:00"],"temperature_2m":[9.6,8.9,8.3,8.2,8.5,8.8,8.8,9.1,9.7,11.1,12.1,12.7,13.7,12.8,13.1,12.9,12.3,8.6,7.6,6.5,6.2,6.1,5.1,4.2],"relative_humidity_2m":[80,82,86,87,86,86,87,87,86,82,78,71,65,67,63,63,73,81,84,85,85,85,88,89],"dew_point_2m":[6.4,5.9,6.1,6.2,6.3,6.6,6.8,7.1,7.5,8.1,8.3,7.6,7.2,6.9,6.3,6.1,7.6,5.6,5.0,4.2,3.8,3.8,3.2,2.5],"apparent_temperature":[5.4,4.7,4.2,4.3,4.4,5.0,5.2,5.9,7.0,8.7,9.7,10.0,10.7,10.1,10.5,11.0,11.1,6.2,5.2,3.8,3.5,3.3,2.3,1.1],"precipitation":[0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00],"rain":[0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00],"weather_code":[3,2,2,3,3,3,3,3,3,3,3,2,3,3,3,2,1,0,1,1,1,0,0,1],"is_day":[0,0,0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,0,0,0,0,0,0]}}
		 */
		// find the appropriate time for the timestamp in the response
		// in the format 2024-03-11T00:00
		$hour = gmdate( 'Y-m-d\TH:00', $timestamp );
		$hour_index = array_search( $hour, $response['hourly']['time'] );
		// based on the index get the conditions for the timestamp
		$data = [];
		foreach(array_keys($response['hourly']) as $key) {
			$data[$key] = $response['hourly'][$key][$hour_index];
		}
		$data['conditions'] = weather_code::get_weather_conditions( $data['weather_code'], $data['is_day'] );

		// return the conditions as an array
		return $data;
	}

}