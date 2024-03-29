<?php

namespace geopath;

class weather_code {

	private const CODES = [
		0  => [
			'day'   => [
				'description' => 'Sunny',
				'icon'        => 'clear-day',
			],
			'night' => [
				'description' => 'Clear',
				'icon'        => 'clear-night',
			]
		],
		1  => [
			'day'   => [
				'description' => 'Mainly Sunny',
				'icon'        => 'clear-day',
			],
			'night' => [
				'description' => 'Mainly Clear',
				'icon'        => 'clear-night',
			]
		],
		2  => [
			'day'   => [
				'description' => 'Partly Cloudy',
				'icon'        => 'partly-cloudy-day',
			],
			'night' => [
				'description' => 'Partly Cloudy',
				'icon'        => 'partly-cloudy-night',
			]
		],
		3  => [
			'description' => 'Cloudy',
			'icon'        => 'cloudy',
		],
		45 => [
			'day'   => [
				'description' => 'Foggy',
				'icon'        => 'fog-day',
			],
			'night' => [
				'description' => 'Foggy',
				'icon'        => 'fog-night',
			]
		],
		48 => [
			'day'   => [
				'description' => 'Rime Fog',
				'icon'        => 'fog-day',
			],
			'night' => [
				'description' => 'Rime Fog',
				'icon'        => 'fog-night',
			]
		],
		51 => [
			'description' => 'Light Drizzle',
			'icon'        => 'drizzle',
		],
		53 => [
			'description' => 'Drizzle',
			'icon'        => 'drizzle',
		],
		55 => [
			'description' => 'Heavy Drizzle',
			'icon'        => 'drizzle',
		],
		56 => [
			'description' => 'Light Freezing Drizzle',
			'icon'        => 'drizzle',
		],
		57 => [
			'description' => 'Freezing Drizzle',
			'icon'        => 'drizzle',
		],
		61 => [
			'description' => 'Light Rain',
			'icon'        => 'rain',
		],
		63 => [
			'description' => 'Rain',
			'icon'        => 'rain',
		],
		65 => [
			'description' => 'Heavy Rain',
			'icon'        => 'rain',
		],
		66 => [
			'description' => 'Light Freezing Rain',
			'icon'        => 'rain',
		],
		67 => [
			'description' => 'Freezing Rain',
			'icon'        => 'rain',
		],
		71 => [
			'description' => 'Light Snow',
			'icon'        => 'snowflake',
		],
		73 => [
			'description' => 'Snow',
			'icon'        => 'snow',
		],
		75 => [
			'description' => 'Heavy Snow',
			'icon'        => 'snow',
		],
		77 => [
			'description' => 'Snow Grains',
			'icon'        => 'snow',
		],
		80 => [
			'description' => 'Light Showers',
			'icon'        => 'rain',
		],
		81 => [
			'description' => 'Showers',
			'icon'        => 'rain',
		],
		82 => [
			'description' => 'Heavy Showers',
			'icon'        => 'rain',
		],
		85 => [
			'description' => 'Light Snow Showers',
			'icon'        => 'snowflake',
		],
		86 => [
			'description' => 'Snow Showers',
			'icon'        => 'snow',
		],
		95 => [
			'description' => 'Thunderstorm',
			'icon'        => 'thunderstorms',
		],
		96 => [
			'description' => 'Light Thunderstorms With Hail',
			'icon'        => 'thunderstorms',
		],
		99 => [
			'description' => 'Thunderstorm With Hail',
			'icon'        => 'thunderstorms',
		]
	];

	/**
	 * @param string $code
	 * @param string $daytime
	 *
	 * @return string[]
	 */
	public static function get_weather_conditions( $code, $daytime = true ) {
		// if day/night doesn't exist, use the default
		return self::CODES[ $code ][ $daytime ? 'day' : 'night' ] ?? self::CODES[ $code ];
	}

}
