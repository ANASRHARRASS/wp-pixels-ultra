<?php
/**
 * includes/settings.php
 * Provider settings: admin page + secret resolution helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPU_Provider_Settings {
	const OPTION_NAME = 'wpu_providers_config';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_settings_page() {
		add_options_page(
			'WPU Providers',
			'WPU Providers',
			'manage_options',
			'wpu-providers',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'wpu_providers_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public static function sanitize( $input ) {
		// Support both legacy array submission and JSON textarea input.
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				add_settings_error( 'wpu_providers_group', 'wpu_json_parse_error', 'Providers JSON is invalid: ' . json_last_error_msg(), 'error' );
				return array();
			}
			$input = $decoded;
		}

		if ( ! is_array( $input ) ) {
			return array();
		}
		$out = array();
		foreach ( $input as $id => $cfg ) {
			$id         = sanitize_text_field( $id );
			$out[ $id ] = array(
				'label'          => sanitize_text_field( $cfg['label'] ?? $id ),
				'env_var'        => sanitize_text_field( $cfg['env_var'] ?? '' ),
				'option_key'     => sanitize_text_field( $cfg['option_key'] ?? '' ),
				'endpoint'       => esc_url_raw( $cfg['endpoint'] ?? '' ),
				'enabled'        => ! empty( $cfg['enabled'] ) ? 1 : 0,
				'rate_limit_ppm' => intval( $cfg['rate_limit_ppm'] ?? 60 ),
				'auth_method'    => sanitize_text_field( $cfg['auth_method'] ?? 'bearer' ),
			);
		}
		return $out;
	}

	public static function render_page() {
		$configs = get_option( self::OPTION_NAME, array() );
		?>
		<div class="wrap">
			<h1>WPU Providers</h1>
			<p>
				Recommended: store provider API keys in environment variables or in wp-config.php constants.
				Only store API keys in database options if you understand the security tradeoffs.
			</p>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'wpu_providers_group' );
				do_settings_sections( 'wpu_providers_group' );
				?>
				<p>Providers JSON (example structure shown). For a more user-friendly UI we can implement a table editor.</p>
				<textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>" rows="10" cols="80" style="font-family: monospace;"><?php echo esc_textarea( wp_json_encode( $configs, JSON_PRETTY_PRINT ) ); ?></textarea>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function get_provider( $id ) {
		$configs = get_option( self::OPTION_NAME, array() );
		return $configs[ $id ] ?? null;
	}

	public static function resolve_secret( $provider_cfg ) {
		if ( empty( $provider_cfg ) || ! is_array( $provider_cfg ) ) {
			return '';
		}

		if ( ! empty( $provider_cfg['env_var'] ) ) {
			$val = getenv( $provider_cfg['env_var'] );
			if ( $val !== false && $val !== '' ) {
				return $val;
			}
			if ( defined( $provider_cfg['env_var'] ) ) {
				return constant( $provider_cfg['env_var'] );
			}
		}

		if ( ! empty( $provider_cfg['option_key'] ) ) {
			$opt = get_option( $provider_cfg['option_key'], '' );
			if ( ! empty( $opt ) ) {
				return $opt;
			}
		}

		return '';
	}
}

WPU_Provider_Settings::init();
