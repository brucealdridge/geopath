document.addEventListener('DOMContentLoaded', function() {
    // Loop over each mapbox instance data
    if ( !window.geoPath) {
        return;
    }
    for (let key in window) {
        if (key.startsWith('geopath_map_')) {
            let mapData = window[key];
            mapboxgl.accessToken = mapData.accessToken;
            const map = new mapboxgl.Map({
                container: mapData.container, // Use the unique container ID
                style: 'mapbox://styles/mapbox/streets-v12',
                center: mapData.center, // starting position ([lng, lat] for Mombasa, Kenya)
                zoom: mapData.zoom,
                pitch: mapData.pitch ?? 0,
                bearing: mapData.bearing ?? 0
            });

            map.on('load', () => {
                // Add GeoJSON data to the map, using the localized data
                if (mapData.geojson) {
                    map.addSource('LineString', {
                        'type': 'geojson',
                        'data': mapData.geojson
                    });
                    map.addLayer({
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

                    // Calculate bounds from the GeoJSON data
                    const bounds = new mapboxgl.LngLatBounds();
                    mapData.geojson.geometry.coordinates.forEach((coord) => {
                        bounds.extend(coord);

                    });
                    // Adjust the map view to fit the bounds of the GeoJSON data with padding
                    // map.fitBounds(bounds, { padding: 20 });
                } else {
                    const marker = new mapboxgl.Marker({
                        'color': '#314ccd'
                    });
                    marker.setLngLat(mapData.poi).addTo(map);
                }

            });
        }
    }
});