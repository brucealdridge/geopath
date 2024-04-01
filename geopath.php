<?php
/**
 * GeoPath
 *
 * @package           GeoPath
 * @author            brucealdridge
 * @copyright         2024
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       GeoPath
 * Plugin URI:        https://github.com/brucealdridge/geopath
 * Description:       Adds geopath block type which grabs location data from Owntracks and display it on a map.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Bruce Aldridge
 * Author URI:        https://brucealdridge.com
 * GitHub Plugin URI: https://github.com/brucealdridge/geopath
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

class GeoPath {
	public const META_PREFIX = '_geopath_';

	private $icon_style = 'svg'; // either svg,svg-static or png
	private $icon_format = '.svg'; // either .svg or .png

	private \geopath\settings $settings;

	public function __construct() {

		add_action ('init', [$this, 'init']);
		add_action('wp_enqueue_scripts', [$this, 'load_assets']);
		add_action('rest_api_init', function () {
			register_rest_route('geopath', '/location', array(
				'methods' => 'GET',
				'callback' => [$this, 'rest_api'],
				'permission_callback' => [$this, 'rest_permission_callback'],
			));
		});

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

	public function rest_permission_callback () {
		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'OMG you can not view private data.', 'my-text-domain' ), array( 'status' => 401 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		return true;
	}
	public function rest_api() {

		$start = $_GET['start'] === '' ? null : $_GET['start'];
		$meta = $this->get_meta( $start , $_GET['end'] );

		return new WP_REST_Response( $meta, 200 );
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
			'end' => $end_date,
		];
		if ($start_date !== null) {
			$meta['start'] = $start_date;
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

				$meta['geoJson'] = $parser->convert_to_linestring();

				// now we can parse the geojson and get the weather data
				$location = $parser->get_last_location();
				$center = $parser->find_center_point();
				$zoom = $parser->find_approriate_zoom();
				$meta['mapZoom'] = $zoom;

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
				$meta['poi'] = $location['longitude'].', '.$location['latitude'];
			} catch ( \Exception $e ) {
				// if there is an error, we can log it and return, nothing else we can do without location data
				error_log( $e->getMessage() );

				return $meta;
			}
		}
		if (is_array($center) && array_key_exists('latitude', $location) ) {
			// save the center point of the location for the rendering of the map
			$meta['locationCoords'] =  $location['longitude'].', '.$location['latitude'];
		}

		try {
			$lookup     = new \geopath\area_lookup();
			$place_name = $lookup->get_area( $location['latitude'], $location['longitude'] );
			// we use the POI or the endpoint of the linestring to get the location name
			$meta['locationName'] = $place_name;

		} catch (\Exception $e) {
			// if there is an error, we can log it
			error_log( $e->getMessage() );
		}

		try {
			// get the weather data
			$openmeteo = new \geopath\openmeteo();
			// weather is requested based on either the POI or the endpoint of the linestring
			$weather   = $openmeteo->request( $location['longitude'], $location['latitude'], $end_time->getTimestamp() );

			$meta['weatherSlug'] = $weather['conditions']['icon'];
			$meta['weather'] = $weather['conditions']['description'];
			$meta['temperature'] = (string) $weather['apparent_temperature'];
		} catch (\Exception $e) {
			// if there is an error, we can log it
			error_log( $e->getMessage() );
		}

		return $meta;
	}

	public function init()
	{
		register_block_type(
			__DIR__ . '/build'
		);

	}

	public function load_assets()
	{
		wp_register_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.css', array(), '3.2.0');
		wp_register_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v3.2.0/mapbox-gl.js', array(), '3.2.0');
	}

}
new GeoPath();


function geopath_add_inline_block_editor_data() {
	// Register the script
	wp_register_script('geopath-block-custom-block-editor-script', plugins_url('script.js', __FILE__), array(), '1.0', true);

	// Get the API token
	$api_token = get_option('geopath_mapbox_token');

	// Add inline script
	$script = sprintf(
		'var geoPath = { mapboxToken: "%s" };',
		esc_js($api_token)
	);
	wp_add_inline_script('geopath-block-custom-block-editor-script', $script, 'before');

	// Enqueue the script
	wp_enqueue_script('geopath-block-custom-block-editor-script');
}
add_action('wp_enqueue_scripts', 'geopath_add_inline_block_editor_data');
add_action('admin_enqueue_scripts', 'geopath_add_inline_block_editor_data');