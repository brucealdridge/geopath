<?php
/**
 * GeoPath Post
 *
 * @package           GeoPath Post
 * @author            brucealdridge
 * @copyright         2024
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       GeoPath Post
 * Plugin URI:        https://github.com/brucealdridge/geopath-post
 * Description:       Adds [geopath] and [geopoint] shortcodes which grab location data from Owntracks and display it on a map.
 * Version:           1.0.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Bruce Aldridge
 * Author URI:        https://brucealdridge.com
 * GitHub Plugin URI: https://github.com/brucealdridge/geopath-post
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

class GeoPathPost {
	public const META_PREFIX = '_geopath_';

	private $icon_style = 'svg'; // either svg,svg-static or png
	private $icon_format = '.svg'; // either .svg or .png

	private \geopath\settings $settings;

	public function __construct() {
		add_shortcode('geopath', array($this, 'geopath_shortcode'));
		add_shortcode('geopoint', array($this, 'geopoint_shortcode'));

		add_action ('init', [$this, 'register_meta']);
		add_action('wp_enqueue_scripts', [$this, 'load_assets']);

		if ( class_exists('WP_CLI') ) {
			$this->register_cli_command();
		}
		require plugin_dir_path( __FILE__ ) . 'src/class-settings.php';
		$this->settings = new \geopath\settings();

	}
	public function load_dependencies() {
		require_once plugin_dir_path( __FILE__ ) . 'src/class-geojson-parser.php';
		require_once plugin_dir_path( __FILE__ ) . 'src/class-openmeteo.php';
		require_once plugin_dir_path( __FILE__ ) . 'src/class-owntracks.php';
		require_once plugin_dir_path( __FILE__ ) . 'src/class-weather-code.php';
		require_once plugin_dir_path( __FILE__ ) . 'src/class-area-lookup.php';
	}

	/**
	 * The shortcode callback function
	 *
	 * @param array $atts The attributes passed to the shortcode
	 * @return string The content to display where the shortcode is used
	 */
	public function geopath_shortcode( $atts ) {
		global $post;

		// Ensure the global $post variable is set (if within The Loop)
		if (!$post) {
			return '';
		}

		// Extract the attributes passed to the shortcode
		$attributes = shortcode_atts(array(
			'start' => '',
			'end' => '',
		), $atts);

		$start_date = get_post_meta($post->ID, self::META_PREFIX.'start', true);
		$end_date = get_post_meta($post->ID, self::META_PREFIX.'end', true);
		if (! $start_date || ! $end_date ) {

			// and we need to refresh the cached data
			$meta = $this->get_meta( $attributes['start'], $attributes['end'] );
			foreach( $meta as $key => $value ) {
				update_post_meta( $post->ID, $key, $value );
			}
		}

		// You can return something here if you want to display something on the page where the shortcode is used.
		// For simply updating post meta without displaying anything, just return an empty string.
		return $this->render_shortcode( $post->ID );
	}


	/**
	 * The shortcode callback function
	 *
	 * @param array $atts The attributes passed to the shortcode
	 * @return string The content to display where the shortcode is used
	 */
	public function geopoint_shortcode( $atts ) {
		global $post;

		// Ensure the global $post variable is set (if within The Loop)
		if (!$post) {
			return '';
		}

		// Extract the attributes passed to the shortcode
		$attributes = shortcode_atts(array(
			'date' => '',
		), $atts);


		$end_date = get_post_meta($post->ID, self::META_PREFIX.'end', true);
		if ( ! $end_date ) {

			// and we need to refresh the cached data
			$meta = $this->get_meta( null, $attributes['date'] );
			foreach( $meta as $key => $value ) {
				update_post_meta( $post->ID, $key, $value );
			}
		}

		// You can return something here if you want to display something on the page where the shortcode is used.
		// For simply updating post meta without displaying anything, just return an empty string.
		return $this->render_shortcode( $post->ID );
	}



	/**
	 * @param ?string $start_date
	 * @param string $end_date
	 *
	 * @return array
	 */
	private function get_meta ( $start_date, $end_date) {
		$this->load_dependencies();

		$expected_start = date('Y-m-d H:i:s', strtotime($start_date) );
		$expected_end = date('Y-m-d H:i:s', strtotime($end_date));

		if ( (! str_starts_with($expected_start, $start_date) && $start_date !== null)
			|| !str_starts_with($expected_end, $end_date) ) {
			return [];
		}

		$meta = [
			self::META_PREFIX . 'end' => $end_date,
		];
		if ($start_date !== null) {
			$meta[self::META_PREFIX . 'start'] = $start_date;
		}

		// convert them to a timestamp based on the timezone of the site
		try {
			$timezone = new \DateTimeZone( get_option( 'timezone_string' ) ?? 'UTC' );
		} catch (\Exception $e) {
			$timezone = new \DateTimeZone( date_default_timezone_get() );
		}

		$end_time = new \DateTime( $end_date, $timezone );
		$owntracks = new \geopath\owntracks(
			$this->settings->get('owntracks', 'api_url'),
			$this->settings->get('owntracks', 'user'),
			$this->settings->get('owntracks', 'device')
		);

		if ( $start_date !== null ) {

			$start_time = new \DateTime( $start_date, $timezone );

			try {
				$geojson   = $owntracks->request( $start_time->getTimestamp(), $end_time->getTimestamp() );

				$parser   = new \geopath\geojson_parser( $geojson, $this->settings );

				$meta['geojson'] = $parser->convert_to_linestring();

				// now we can parse the geojson and get the weather data
				$location = $parser->get_last_location();
				$center = $parser->find_center_point();
				$zoom = $parser->find_approriate_zoom();
				$meta['map_zoom'] = $zoom;

			} catch ( \Exception $e ) {
				// if there is an error, we can log it and return, nothing else we can do
				error_log( $e->getMessage() );

				return $meta;
			}
		} else {

			try {
				// grab a point from own tracks
				$range_start = clone $end_time;
				$range_end = clone $end_time;
				$range_period = new \DateInterval('PT1H');
				$range_start->sub( $range_period );
				$range_end->add( $range_period );
				$geojson = $owntracks->request( $range_start->getTimestamp(), $range_end->getTimestamp() );

				$parser = new \geopath\geojson_parser( $geojson, $this->settings );
				$location = $parser->get_location_at_time( $end_time->getTimestamp() );
				$center = $location;
			} catch ( \Exception $e ) {
				// if there is an error, we can log it and return, nothing else we can do without location data
				error_log( $e->getMessage() );

				return $meta;
			}
		}
		if (is_array($center) && array_key_exists('latitude', $location) ) {
			// save the center point of the location for the rendering of the map
			$meta['location_geo'] =  $location['longitude'].', '.$location['latitude'];
		}

		try {
			$lookup     = new \geopath\area_lookup();
			$place_name = $lookup->get_area( $location['latitude'], $location['longitude'] );
			// we use the POI or the endpoint of the linestring to get the location name
			$meta['location'] = $place_name;

		} catch (\Exception $e) {
			// if there is an error, we can log it
			error_log( $e->getMessage() );
		}

		try {
			// get the weather data
			$openmeteo = new \geopath\openmeteo();
			// weather is requested based on either the POI or the endpoint of the linestring
			$weather   = $openmeteo->request( $location['longitude'], $location['latitude'], $end_time->getTimestamp() );

			$meta['weather_icon'] = $weather['conditions']['icon'];
			$meta['weather_description'] = $weather['conditions']['description'];
			$meta['weather_temperature'] = $weather['apparent_temperature'];
		} catch (\Exception $e) {
			// if there is an error, we can log it
			error_log( $e->getMessage() );
		}

		return $meta;
	}

	private function register_cli_command() {
		// these commands are just used for testing

		WP_CLI::add_command(
			'geopath',
			function ($args, $assoc_args) {
				date_default_timezone_set( get_option( 'timezone_string' ) ); // this is not set by default on CLI it seems.
				$meta = $this->get_meta($assoc_args['start'], $assoc_args['end']);
				WP_CLI::success( 'Meta data' );
				WP_CLI::success( json_encode( $meta, JSON_PRETTY_PRINT ) );
			},
			[
				'shortdesc' => 'Get the meta data for a post',
				'synopsis' => [
					[
						'type' => 'assoc',
						'name' => 'start',
						'description' => 'The start date and time in the format Y-m-d\TH:i:s',
						'optional' => false,
					],
					[
						'type' => 'assoc',
						'name' => 'end',
						'description' => 'The end date and time in the format Y-m-d\TH:i:s',
						'optional' => false,
					],
				],
			]
		);
		WP_CLI::add_command(
			'geopoint',
			function ($args, $assoc_args) {
				date_default_timezone_set( get_option( 'timezone_string' ) ); // this is not set by default on CLI it seems.
				$meta = $this->get_meta(null, $assoc_args['date']);
				WP_CLI::success( 'Meta data' );
				WP_CLI::success( json_encode( $meta, JSON_PRETTY_PRINT ) );
			},
			[
				'shortdesc' => 'Get the meta data for a post',
				'synopsis' => [
					[
						'type' => 'assoc',
						'name' => 'date',
						'description' => 'The date and time in the format Y-m-d H:i:s',
						'optional' => false,
					],
				],
			]
		);
	}

	public function register_meta()
	{
		register_meta(
			'post',
			'weather_icon',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'      => 'weather icon'
			)
		);
		register_meta(
			'post',
			'weather_description',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'      => 'Weather conditions',
			)
		);
		register_meta(
			'post',
			'weather_temperature',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'      => 'Temperature in C',
			)
		);
		register_meta(
			'post',
			'map_zoom',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'default'      => '12',
				'type'         => 'string',
				'description'      => 'zoom level',
			)
		);
		register_meta(
			'post',
			'geojson',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'      => 'Raw GEOJSON data',
			)
		);
		register_meta(
			'post',
			'location',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'description'      => 'Location',
			)
		);
	}

	private function render_shortcode( int $ID ) {
		$weather_img = plugins_url('/weather-icons/', __FILE__).$this->icon_style.'/'.get_post_meta( $ID, 'weather_icon', true ).$this->icon_format;
		$weather_description = get_post_meta( $ID, 'weather_description', true );
		$weather_temperature = get_post_meta( $ID, 'weather_temperature', true );
		$map_zoom = get_post_meta( $ID, 'map_zoom', true );
		$location = get_post_meta( $ID, 'location', true );
		$geo_location = get_post_meta( $ID, 'location_geo', true );
		if ($geo_location ) {
			[ $lat, $lon ] = explode( ',', get_post_meta( $ID, 'location_geo', true ) );
		}
		$geojson = get_post_meta( $ID, 'geojson', true );

		$map_id = 'geopath_map_'. $ID;

		wp_enqueue_style('mapbox-gl-css');
		wp_enqueue_script('mapbox-gl-js');

		if ( $geojson ) {
			/*
			$this->load_dependencies();
			$parser = new \geopath\geojson_parser( $geojson, $this->settings );
			$center = $parser->find_center_point();
			$lon = $center['latitude'];
			$lat = $center['longitude'];
			$map_zoom = $map_zoom ?: $parser->find_approriate_zoom();
			*/
			$geojson = json_decode( $geojson );
		}

		if ( ! isset($lat) ) {
			return '';
		}
		$data = [
			'accessToken' => $this->settings->get('mapbox', 'token'),
			'mapStyle'    => $this->settings->get('mapbox', 'style'),
			'container'   => $map_id, // Pass the unique ID
			'center'      => [ $lat, $lon ],
			'zoom'        => $map_zoom ?: 12
		];
		if ( $geojson ) {
			$data['geojson'] = $geojson;
		}
		wp_enqueue_script( 'my-mapbox-init', plugins_url( '/js/mapbox-init.js', __FILE__ ), array( 'mapbox-gl-js' ), '1.0.2', true );
		wp_localize_script( 'my-mapbox-init', 'mapbox_' . $map_id, $data );

		return '<div id="'. $map_id .'" style="width: 100%; height: 400px;"></div>
<div style="text-align: center; display: flex; align-items: center; justify-content: center; gap: 20px;">
    <div>
        <p style="font-size: 1.1em; font-weight: bold; margin-right: 20px;">'. $location .'</p>
    </div>
    <div>
        <span style="font-size: 2em; margin: 0;">
        	<img src="'. $weather_img .'" alt="'. $weather_description .'" style=" height: 1.6em; float: left; margin-right: 10px" >
        '. $weather_temperature .'°C
        </span>
    </div>
    <div>
        <p style="margin: 0;">'. $weather_description .'</p>
    </div>
</div>
';
	}
	public function load_assets()
	{
		wp_register_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css', array(), '3.2.0');
		wp_register_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js', array(), '3.2.0');
	}

}
new GeoPathPost();