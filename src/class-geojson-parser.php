<?php

namespace geopath;

class geojson_parser {
	/**
	 * @var mixed
	 */
	private $geojson;
	/**
	 * @var settings
	 */
	private $settings;

	public function __construct( $geojson, settings $settings ) {
		$this->geojson = json_decode( $geojson );
		$this->settings = $settings;
	}

	public function convert_to_linestring() {
		$coordinates = $this->find_all_coords();
		$linestring = [
			'type' => 'LineString',
			'coordinates' => $coordinates
		];
		return json_encode($linestring);
	}
	
	/**
	 * Get the last location from the geojson
	 *
	 * @return array
	 */
	public function get_last_location() {
		// this assumes that we have a linestring GEOJSON format
		$coordinates = $this->find_all_coords();
		if ( [] === $coordinates ) {
			throw new \Exception('no location found');
		}
		$last = end( $coordinates );
		[$lon, $lat] = $last;
		
		// return as array [lon, lat]
		return [
			'latitude'  => $lat,
			'longitude' => $lon
		];
	}

	public function find_all_coords(): array {
		$minimum_accuracy = $this->settings->get('geojson', 'min_accuracy');
		if ($this->geojson->features ?? null) {
			$coords = [];
			foreach ( $this->geojson->features as $feature ) {

				if ( ! $feature || ! $feature->geometry || ! $feature->geometry->coordinates ) {
					continue;
				}
				if ( $feature->properties && $feature->properties->acc && $feature->properties->acc > $minimum_accuracy ) {
					// if the accuracy is too low, skip this point
					continue;
				}
				$coords[] = $feature->geometry->coordinates;

			}

			return $coords;
		}
		return $this->geojson->geometry->coordinates ?? [];
	}

	public function find_center_point()
	{
		$coords = $this->find_all_coords();
		$lat = 0;
		$lon = 0;
		$count = 0;
		foreach ( $coords as $coord ) {
			$lon += $coord[0]; // geojson has lon first
			$lat += $coord[1];
			$count++;
		}
		if ($lon === 0 || $lat === 0 || $count === 0) {
			throw new \Exception('no location found');
		}
		$lat = $lat / $count;
		$lon = $lon / $count;
		return [
			'latitude'  => $lat,
			'longitude' => $lon
		];
	}

   private function get_zoom_for_mapbox($lat_min, $lat_max, $lng_min, $lng_max, $defaultZoom = null) {

	   $dx = abs($lat_max - $lat_min);
	   $dy = abs($lng_max - $lng_min);
	   $d = max($dx, $dy);

	   // Return zoom level
	   if ($d > 100) return $defaultZoom ?? 0;
	   if ($d > 75 && $d <= 100) return 0.2;
	   if ($d > 50 && $d <= 75) return 0.4;
	   if ($d > 40 && $d <= 50) return 1.2;
	   if ($d > 30 && $d <= 40) return 1.4;
	   if ($d > 20 && $d <= 30) return 1.6;
	   if ($d > 15 && $d <= 20) return 1.7;
	   if ($d > 10 && $d <= 15) return 2.1;
	   if ($d > 5 && $d <= 10) return 2.7;
	   if ($d > 2.5 && $d <= 5) return 3.2;
	   if ($d > 1 && $d <= 2.5) return 3.8;
	   if ($d > 0.5 && $d <= 1) return 6;
	   if ($d > 0.25 && $d <= 0.5) return 7.2;
	   if ($d > 0.125 && $d <= 0.25) return 7.6;
	   if ($d > 0.10 && $d <= 0.125) return 8.2;
	   if ($d > 0.01 && $d <= 0.10) return 8.8;
	   if ($d > 0.001 && $d <= 0.01) return 9;

	   return 9.6;
   }

	public function find_approriate_zoom()
	{
		$coords = $this->find_all_coords();
		$min_lat = 90;
		$max_lat = -90;
		$min_lon = 180;
		$max_lon = -180;
		foreach ( $coords as $coord ) {
			$min_lat = min($min_lat, $coord[1]);
			$max_lat = max($max_lat, $coord[1]);
			$min_lon = min($min_lon, $coord[0]);
			$max_lon = max($max_lon, $coord[0]);
		}

		return $this->get_zoom_for_mapbox($min_lat, $max_lat, $min_lon, $max_lon, 9.6);

	}

	public function get_location_at_time( int $timestamp ) {
		if ( ! isset( $this->geojson->features ) ) {
			throw new \Exception('looks like an unsupported geojson format');
		}

		// date format required: 2024-03-24T18:28:36Z
		$diff = PHP_INT_MAX;
		foreach($this->geojson->features as $feature) {
			if ($feature && $feature->properties && $feature->properties->tst && $feature->geometry && $feature->geometry->coordinates) {
				$cur_diff = abs( $feature->properties->tst - $timestamp );
				if ($cur_diff < $diff) {
					$diff = $cur_diff;
					$coordinates = $feature->geometry->coordinates;

					[$lon, $lat] = $coordinates;
				}
			}
		}
		if (!isset($lon) || !isset($lat)) {
			throw new \Exception('no location found');
		}

		return [
			'latitude'  => $lat,
			'longitude' => $lon
		];
	}
}
