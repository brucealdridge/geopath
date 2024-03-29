<?php

namespace geopath;

class settings {

	private $settings = [
		'mapbox' => [
			'token' => [
				'name' => 'Mapbox API Token',
				'label' => 'You can get it from <a href="https://mapbox.com">here</a>',
			],
			'style' => [
				'name' => 'Mapbox Style',
				'label' => 'You can get it from <a href="https://docs.mapbox.com/mapbox-gl-js/guides/styles/#classic-style-templates-and-custom-styles">here</a>',
				'default' => 'mapbox://styles/mapbox/standard',
			],
		],
		'owntracks' => [
			'api_url' => [
				'name' => 'Owntracks API URL',
				'label' => 'you can use https://user:pass@domain.com for HTTP auth',
			],
			'user' => [
				'name' => 'Owntracks User',
				'label' => 'Which user to query data for',
			],
			'device' => [
				'name' => 'Owntracks Device',
				'label' => 'Which device to query data for',
			],
		],
        'geojson' => [
            'min_accuracy' => [
                'name' => 'Minimum Accuracy',
                'label' => 'Minimum accuracy to consider for location data (in meters)',
                'default' => 100,
            ],
        ]
	];
	public function get( $section, $setting ) {
		return get_option( 'geopath_'.$section.'_'.$setting, $this->settings[$section][$setting]['default'] ?? '');
	}

	public function __construct( ) {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'options_page' ) );
			add_action( 'admin_init', array( $this, 'settings_init' ) );
		}
	}

	public function settings_init() {

		foreach( $this->settings as $section => $settings ) {


			add_settings_section(
				'geopath_section_' . $section,
				ucfirst( $section ),
				function( $args ) use ($section) {
					echo '<p>Settings for ' . $args['title'] . '</p>';
				},
				'geopath'
			);
			foreach( $settings as $setting_id => $setting ) {
				$option_name = 'geopath_'.$section.'_'.$setting_id;
				register_setting( 'geopath', $option_name );

				add_settings_field(
					$option_name,
					$setting['name'],
					function() use ( $setting, $option_name ) {
						$value = get_option( $option_name, $setting['default'] ?? '' );

						printf(
							'<input class="large-text" type="text" name="%1$s" id="%1$s" value="%2$s"><br/><label for="%1$s">%3$s</label>',
							$option_name,
							$value,
							$setting['label'] ?? ''
						);
					},
					'geopath',
					'geopath_section_' . $section,
				);
			}

		}

	}

	/**
	 * Add the top level menu page.
	 */
	public function options_page() {
		add_submenu_page(
			'options-general.php',
			'GeoPath Settings',
			'GeoPath',
			'manage_options',
			'geopath',
			[ $this, 'page_html' ]
		);
	}


	public function page_html() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// add error/update messages

		// check if the user have submitted the settings
		// WordPress will add the "settings-updated" $_GET parameter to the url
		if ( isset( $_GET['settings-updated'] ) ) {
			// add settings saved message with the class of "updated"
			add_settings_error( 'geopath_messages', 'wporg_message', __( 'Settings Saved', 'wporg' ), 'updated' );
		}

		// show error/update messages
		settings_errors( 'geopath_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// output security fields for the registered setting "wporg"
				settings_fields( 'geopath' );
				// output setting sections and their fields
				// (sections are registered for "wporg", each field is registered to a specific section)
				do_settings_sections( 'geopath' );
				// output save settings button
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}
}