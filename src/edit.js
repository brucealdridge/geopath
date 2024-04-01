import {__} from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';
import {
    PanelBody,
    TextControl,
    ToggleControl,
    Button,
    TextareaControl,
} from '@wordpress/components';
import mapboxgl from '!mapbox-gl';
import {useEffect, useRef} from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

function Edit({attributes, setAttributes}) {
    // Destructure attributes
    const {
        isPath,
        start,
        end,
        geoJson,
        locationName,
        locationCoords,
        weather,
        weatherSlug,
        temperature,
        mapZoom,
        mapPitch,
        mapBearing,
        mapCenter
    } = attributes;

    const mapContainer = useRef(null);
    const map = useRef(null);

    let mapLayer = null;
    let mapMarker = null;

    const refreshMapData = () => {
        if (geoJson && isPath) {
            // Check if the source already exists
            if (map.current.getSource('LineString')) {
                // Update the existing source's data
                map.current.getSource('LineString').setData(JSON.parse(geoJson));
            } else {
                // Add the source as it doesn't exist
                map.current.addSource('LineString', {
                    'type': 'geojson',
                    'data': JSON.parse(geoJson)
                });
            }
            if ( mapMarker ) {
                mapMarker.remove();
            }
            if (!map.current.getLayer('LineString')) {
                mapLayer = map.current.addLayer({
                    'id': 'LineString',
                    'type': 'line',
                    'source': 'LineString',
                    'layout': {
                        'line-join': 'round',
                        'line-cap': 'round'
                    },
                    'paint': {
                        'line-color': 'rgba(44,62,229,0.48)',
                        'line-width': 5
                    }
                });
            }

        } else if ( !isPath ) {
            if (map.current.getLayer('LineString')) {
                map.current.removeLayer('LineString');
            }
            const markerCoords = locationCoords.split(',').map(coord => parseFloat(coord.trim()));
            if ( mapMarker ) {
                mapMarker.remove();
            }
                mapMarker = new mapboxgl.Marker({
                    'color': '#314ccd'
                });
                mapMarker.setLngLat( markerCoords ).addTo(map.current);
        }
    };

    useEffect(() => {
        if (window.geoPath && window.geoPath.mapboxToken) {
            mapboxgl.accessToken = window.geoPath.mapboxToken;
            // Initialize your Mapbox map or other logic here
        } else {
            console.error('No Mapbox token found. Please add your token');
            return;
        }

        const [lng, lat] = (locationCoords || '40.785091, -73.968285').split(',').map(coord => parseFloat(coord.trim()));
        const [mlng, mlat] = (mapCenter || locationCoords || '40.785091, -73.968285').split(',').map(coord => parseFloat(coord.trim()));
        const zoom = parseFloat(mapZoom || 9);
        const pitch = parseFloat(mapPitch || 0);
        const bearing = parseFloat(mapBearing || 0);



        if (map.current) {
            map.current.setCenter([mlng, mlat]);
            map.current.setZoom(zoom);
            if (mapPitch) {
                map.current.setPitch(mapPitch);
            }
            if (mapBearing) {
                map.current.setBearing(mapBearing);
            }
            refreshMapData();
        } else {
            // Initialize map if it doesn't exist
            map.current = new mapboxgl.Map({
                container: mapContainer.current,
                style: 'mapbox://styles/mapbox/streets-v12',
                center: [mlng, mlat],
                zoom: zoom,
                pitch: pitch,
                bearing: bearing
            });
            // when the lap is loaded ...
            map.current.on('load', () => {
                refreshMapData();
            });
        }

    }, [mapCenter, locationCoords, mapZoom, mapPitch, mapBearing]);


    const updateAttributesBasedOnMap = () => {
        if (map.current) {
            const newLng = map.current.getCenter().lng.toFixed(4);
            const newLat = map.current.getCenter().lat.toFixed(4);
            const newZoom = map.current.getZoom().toFixed(2);
            const newPitch = map.current.getPitch().toFixed(2);
            const newBearing = map.current.getBearing().toFixed(2);

            setAttributes({
                mapCenter: `${newLng}, ${newLat}`,
                mapZoom: newZoom,
                mapPitch: newPitch,
                mapBearing: newBearing,
            });
        }
    }


    // Function to handle API call
    const fetchData = () => {
        // add start & end to the URL making sure they are encoded
        const apiUrl = `/geopath/location?start=${encodeURIComponent(start || '')}&end=${encodeURIComponent(end)}`;

        apiFetch( { path: apiUrl})
            .then(data => {
                let newAttributes = {};

                if (data.weather !== undefined) {
                    newAttributes.weather = data.weather;
                    newAttributes.weatherSlug = data.weatherSlug;
                    newAttributes.temperature = data.temperature;
                }
                if (data.locationName !== undefined) {
                    newAttributes.locationName = data.locationName;
                }
                if (data.locationCoords !== undefined) {
                    newAttributes.locationCoords = data.locationCoords;
                }
                if (data.mapZoom !== undefined) {
                    newAttributes.mapZoom = data.mapZoom;
                }
                if (data.geoJson !== undefined) {
                    newAttributes.geoJson = data.geoJson;
                }

                setAttributes(newAttributes);
            })
            .catch(error => {
                console.error('There has been a problem with your fetch operation:', error);
            });
    };

    return (
        <div {...useBlockProps()}>
            <InspectorControls>
                <PanelBody title={__('Date and Location Settings', 'geopath')} initialOpen={true}>
                    <ToggleControl
                        checked={isPath}
                        label={__(
                            'Show track',
                            'geopath'
                        )}
                        onChange={() =>
                            setAttributes({
                                isPath: !isPath,
                            })
                        }
                    />
                    {isPath && <TextControl
                        label={__('Start Date/Time', 'geopath')}
                        value={start}
                        onChange={(value) => setAttributes({start: value})}
                    />}
                    <TextControl
                        label={__((isPath ? 'End Date' : 'Date / Time'), 'geopath')}
                        value={end}
                        onChange={(value) => setAttributes({end: value})}
                    />
                    <Button isPrimary onClick={fetchData}>{__('Fetch Data', 'geopath')}</Button>
                </PanelBody>
                <PanelBody title={__('Display Settings', 'geopath')} initialOpen={false}>
                    <TextControl
                        label={__('Location Name', 'geopath')}
                        value={locationName}
                        onChange={(value) => setAttributes({locationName: value})}
                    />
                    <TextControl
                        label={__('Map Center', 'geopath')}
                        value={mapCenter}
                        onChange={(value) => setAttributes({mapCenter: value})}
                    />
                    <TextControl
                        label={__('Map Zoom Level', 'geopath')}
                        value={mapZoom}
                        onChange={(value) => setAttributes({mapZoom: value})}
                    />
                    <TextControl
                        label={__('Map Pitch', 'geopath')}
                        value={mapPitch}
                        onChange={(value) => setAttributes({mapPitch: value})}
                    />
                    <TextControl
                        label={__('Map Bearing', 'geopath')}
                        value={mapPitch}
                        onChange={(value) => setAttributes({mapBearing: value})}
                    />
                    <Button isPrimary onClick={updateAttributesBasedOnMap}>{__('Sync from map', 'geopath')}</Button>
                </PanelBody>
                <PanelBody title={__('Other Settings', 'geopath')} initialOpen={false}>
                    <TextControl
                        label={__('Weather', 'geopath')}
                        value={weather}
                        onChange={(value) => setAttributes({weather: value})}
                    />
                    <TextControl
                        label={__('Weather Slug', 'geopath')}
                        value={weatherSlug}
                        onChange={(value) => setAttributes({weatherSlug: value})}
                    />
                    <TextControl
                        label={__('Temperature', 'geopath')}
                        value={temperature}
                        onChange={(value) => setAttributes({temperature: value})}
                    />
                    <TextControl
                        label={__('Location Coordinates', 'geopath')}
                        value={locationCoords}
                        onChange={(value) => setAttributes({locationCoords: value})}
                        help={__('Format: lat, long', 'geopath')}
                    />
                    <TextareaControl
                        label={__('GeoJSON Data', 'geopath')}
                        value={geoJson}
                        onChange={(value) => setAttributes({geoJson: value})}
                        help={__('Enter your GeoJSON data here.', 'geopath')}
                    />
                </PanelBody>
            </InspectorControls>
            <div>
                <div ref={mapContainer} className="map-container" style={{height: '400px'}}/>
                <div style={{
                    textAlign: "center",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    gap: "20px"
                }}>
                    <div>
                        <p style={{fontSize: "1.1em", fontWeight: "bold", marginRight: "20px"}}>
                            {locationName}
                        </p>
                    </div>
                    {weather && temperature && <div>

                    <span style={{fontSize: "2em"}}>
                        <img src={weatherSlug} alt={weather}
                             style={{height: "1.6em", float: "left", marginRight: "10px"}}/>
                        {temperature}°C
                    </span>
                    </div>}
                    {weather && temperature &&
                        <div>
                            <p style={{margin: 0}}>
                                {weather}
                            </p>
                        </div>}
                </div>
            </div>
        </div>
    );
}

export default Edit;
