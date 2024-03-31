<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @var array $attributes
 */
$start = $attributes['start'] ?? '';
$end = $attributes['end'] ?? '';
$isPath = $attributes['isPath'] ?? '';
$geoJson = $attributes['geoJson'] ?? '';
$locationName = $attributes['locationName'] ?? '';
$locationCoords = $attributes['locationCoords'] ?? '';
$weather = $attributes['weather'] ?? '';
$weatherSlug = $attributes['weatherSlug'] ?? '';
$temperature = $attributes['temperature'] ?? '';
$mapZoom = $attributes['mapZoom'] ?? '';
$mapPitch = $attributes['mapPitch'] ?? '';
$mapBearing = $attributes['mapBearing'] ?? '';
$mapCenter = $attributes['mapCenter'] ?? '';


$weather_img = plugins_url('/weather-icons/', __DIR__).'svg'.'/'.$weatherSlug.'.svg';

$map_id = uniqid('geopath_map_',  false);

wp_enqueue_style('mapbox-gl-css');
wp_enqueue_script('mapbox-gl-js');


if ( stripos( $locationCoords, ',') === false ) {
    $locationCoords = '-73.5804, 45.53483';
}
if ( stripos( $mapCenter, ',') === false ) {
    $mapCenter = $locationCoords;
}

$settings = new geopath\settings();
$data = [
	'accessToken' => $settings->get('mapbox', 'token'),
	'mapStyle'    => $settings->get('mapbox', 'style'),
	'container'   => $map_id, // Pass the unique ID
	'center'      => array_map('floatval', explode(',', $mapCenter)),
	'poi'         => array_map('floatval', explode(',', $locationCoords)),
	'zoom'        => $mapZoom ?? 10,
	'pitch'       => $mapPitch ?? 0,
	'bearing'     => $mapBearing ?? 0,
];
if ( $geoJson ) {
	$data['geojson'] = json_decode($geoJson);
}

wp_enqueue_script( 'my-mapbox-init', plugins_url( '/js/mapbox-init.js', __DIR__ ), array( 'mapbox-gl-js' ), '1.0.2', true );
wp_localize_script( 'my-mapbox-init', $map_id, $data );

?>
<div id="<?= $map_id ?>" style="width: 100%; height: 400px;"></div>
<div style="text-align: center; display: flex; align-items: center; justify-content: center; gap: 20px;">
    <div>
        <p style="font-size: 1.1em; font-weight: bold; margin-right: 20px;"><?= $locationName ?></p>
    </div>
    <div>
        <span style="font-size: 2em; margin: 0;">
        	<img src="<?= $weather_img ?>" alt="<?= $weather ?>" style=" height: 1.6em; float: left; margin-right: 10px" >
        <?= $temperature ?>°C
        </span>
    </div>
    <div>
        <p style="margin: 0;"><?= $weather ?></p>
    </div>
</div>
