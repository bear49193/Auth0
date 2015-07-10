<?php
/**
 * Plugin Name: Wordpress Auth0 Integration
 * Description: Implements the Auth0 Single Sign On solution into Wordpress
 * Version: 1.4.0
 * Author: Auth0
 * Author URI: https://auth0.com
 */

define( 'WPA0_PLUGIN_FILE', __FILE__ );
define( 'WPA0_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPA0_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WPA0_LANG', 'wp-auth0' );
define( 'AUTH0_DB_VERSION', 3 );
define( 'WPA0_VERSION', '1.4.0' );

/**
 * Main plugin class
 */
class WP_Auth0 {

	public static function init() {

		spl_autoload_register( array( __CLASS__, 'autoloader' ) );

		WP_Auth0_Ip_Check::init();
		WP_Auth0_DBManager::init();

		add_action( 'init', array( __CLASS__, 'wp_init' ) );

		// Add hooks for install uninstall and update.
		register_activation_hook( WPA0_PLUGIN_FILE, array( __CLASS__, 'install' ) );
		register_deactivation_hook( WPA0_PLUGIN_FILE, array( __CLASS__, 'uninstall' ) );

		// Add an action to append a stylesheet for the login page.
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'render_auth0_login_css' ) );

		// Add a hook to add Auth0 code on the login page.
		add_filter( 'login_message', array( __CLASS__, 'render_form' ) );

		// Add hook to handle when a user is deleted.
		add_action( 'delete_user', array( __CLASS__, 'delete_user' ) );

		add_shortcode( 'auth0', array( __CLASS__, 'shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'wp_enqueue' ) );

		add_action( 'widgets_init', array( __CLASS__, 'wp_register_widget' ) );

		add_filter( 'query_vars', array( __CLASS__, 'a0_register_query_vars' ) );

		$plugin = plugin_basename( __FILE__ );
		add_filter( "plugin_action_links_$plugin", array( __CLASS__, 'wp_add_plugin_settings_link' ) );

		if ( isset( $_GET['message'] ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'a0_render_message' ) );
		}

		WP_Auth0_LoginManager::init();
		WP_Auth0_UsersRepo::init();
		WP_Auth0_Settings_Section::init();
		WP_Auth0_Admin::init();
		WP_Auth0_Dashboard_Preferences::init();
		WP_Auth0_ErrorLog::init();
		WP_Auth0_Configure_JWTAUTH::init();
		WP_Auth0_Dashboard_Widgets::init();
		WP_Auth0_WooCommerceOverrides::init();
		WP_Auth0_Export_Users::init();
		// WP_Auth0_Amplificator::init();.

		add_action( 'plugins_loaded', array( __CLASS__, 'check_jwt_auth' ) );
	}

	public static function is_jwt_auth_enabled() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active( 'wp-jwt-auth/JWT_AUTH.php' );
	}

	public static function is_jwt_configured() {
		$options = WP_Auth0_Options::Instance();
		return (
			JWT_AUTH_Options::get( 'aud' ) === $options->get( 'client_id' ) &&
			JWT_AUTH_Options::get( 'secret' ) === $options->get( 'client_secret' ) &&
			JWT_AUTH_Options::get( 'secret_base64_encoded' ) &&
			$options->get( 'jwt_auth_integration' ) &&
			JWT_AUTH_Options::get( 'jwt_attribute' ) === 'sub'
		);
	}

	public static function check_jwt_auth() {
		if ( isset( $_REQUEST['page'] ) && 'wpa0-jwt-auth' === $_REQUEST['page'] ) {
			return;
		}

		if ( self::is_jwt_auth_enabled() && ! self::is_jwt_configured() ) {
			add_action( 'admin_notices', array( __CLASS__, 'notify_jwt' ) );
		}
	}

	public static function notify_jwt() {
		?>
		<div class="update-nag">
			JWT Auth installed. To configure it to work the Auth0 plugin, click <a href="admin.php?page=wpa0-jwt-auth">HERE</a>
		</div>
		<?php
	}

	public static function get_plugin_dir_url() {
		return plugin_dir_url( __FILE__ );
	}

	public static function  a0_register_query_vars( $qvars ) {
		$qvars[] = 'error_description';
		return $qvars;
	}

	public static function a0_render_message() {
		$message = null;

		if ( $message ) {
			echo "<div class=\"a0-message\">$message <small onclick=\"jQuery('.a0-message').hide();\">(Close)</small></div>";
			echo '<script type="text/javascript">
				setTimeout(function(){jQuery(".a0-message").hide();}, 10 * 1000);
			</script>';
		}
	}

	/**
	 * Add settings link on plugin page.
	 */
	public static function wp_add_plugin_settings_link( $links ) {

		$settings_link = '<a href="admin.php?page=wpa0-errors">Error Log</a>';
		array_unshift( $links, $settings_link );

		$settings_link = '<a href="admin.php?page=wpa0">Settings</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	public static function wp_register_widget() {
		register_widget( 'WP_Auth0_Embed_Widget' );
		register_widget( 'WP_Auth0_Popup_Widget' );
		register_widget( 'WP_Auth0_SocialAmplification_Widget' );
	}

	public static function wp_enqueue() {
		$options = WP_Auth0_Options::Instance();
		$client_id = $options->get( 'client_id' );

		if ( trim( $client_id ) === '' ) {
			return;
		}

		if ( isset( $_GET['message'] ) ) {
			wp_enqueue_script( 'jquery' );
		}

		wp_enqueue_style( 'auth0-widget', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/css/main.css' );
	}

	public static function shortcode( $atts ) {
		ob_start();

		require_once WPA0_PLUGIN_DIR . 'templates/login-form.php';
		renderAuth0Form( false, self::buildSettings( $atts ) );

		$html = ob_get_clean();
		return $html;
	}

	public static function render_back_to_auth0() {

		include WPA0_PLUGIN_DIR . 'templates/back-to-auth0.php';

	}

	protected static function _get_boolean( $value ) {
		return ( 1 === (int) $value || strtolower( $value ) === 'true');
	}

	protected static function _is_valid( $array, $key ) {
		return ( isset( $array[$key] ) && trim( $array[$key] ) !== '' );
	}

	public static function build_settings( $settings ) {
		$options_obj = array();
		if ( isset( $settings['form_title'] ) &&
			( ! isset( $settings['dict'] ) || ( isset( $settings['dict'] ) && trim( $settings['dict'] ) === '' ) ) &&
			trim( $settings['form_title'] ) !== '' ) {

			$options_obj['dict'] = array(
				'signin' => array(
					'title' => $settings['form_title'],
				)
			);

		} elseif ( isset( $settings['dict'] ) && trim( $settings['dict'] ) !== '' ) {
			if ( $oDict = json_decode( $settings['dict'], true ) ) {
				$options_obj['dict'] = $oDict;
			} else {
				$options_obj['dict'] = $settings['dict'];
			}
		}
		if ( self::_is_valid( $settings, 'custom_css' ) ) {
			$options_obj['custom_css'] = $settings['custom_css'];
		}
		if ( self::_is_valid( $settings, 'custom_js' ) ) {
			$options_obj['custom_js'] = $settings['custom_js'];
		}
		if ( self::_is_valid( $settings, 'social_big_buttons' ) ) {
			$options_obj['socialBigButtons'] = self::_get_boolean( $settings['social_big_buttons'] );
		}
		if ( self::_is_valid( $settings, 'gravatar' ) ) {
			$options_obj['gravatar'] = self::_get_boolean( $settings['gravatar'] );
		}
		if ( self::_is_valid( $settings, 'username_style' ) ) {
			$options_obj['usernameStyle'] = $settings['username_style'];
		}
		if ( self::_is_valid( $settings, 'remember_last_login' ) ) {
			$options_obj['rememberLastLogin'] = self::_get_boolean( $settings['remember_last_login'] );
		}
		if ( self::_is_valid( $settings, 'sso' ) ) {
			$options_obj['sso'] = self::_get_boolean( $settings['sso'] );
		}
		if ( self::_is_valid( $settings, 'auth0_implicit_workflow' ) ) {
			$options_obj['auth0_implicit_workflow'] = self::_get_boolean( $settings['auth0_implicit_workflow'] );
		}
		if ( self::_is_valid( $settings, 'icon_url' ) ) {
			$options_obj['icon'] = $settings['icon_url'];
		}
		if ( isset( $settings['extra_conf'] ) && trim( $settings['extra_conf'] ) !== '' ) {
			$extra_conf_arr = json_decode( $settings['extra_conf'], true );
			$options_obj = array_merge( $extra_conf_arr, $options_obj );
		}
		return $options_obj;
	}

	public static function render_auth0_login_css() {
		$client_id = WP_Auth0_Options::Instance()->get( 'client_id' );

		if ( trim( $client_id ) === '' ) {
			return;
		}
	?>
		<link rel='stylesheet' href='<?php echo plugins_url( 'assets/css/login.css', __FILE__ ); ?>' type='text/css' />
	<?php
	}

	public static function render_form( $html ) {
		$client_id = WP_Auth0_Options::Instance()->get( 'client_id' );

		if ( trim( $client_id ) === '' ) {
			return;
		}

		ob_start();
		require_once WPA0_PLUGIN_DIR . 'templates/login-form.php';
		renderAuth0Form();

		$html = ob_get_clean();
		return $html;
	}

	public static function insert_auth0_error( $section, $wp_error ) {

		if ( $wp_error instanceof WP_Error ) {
			$code = $wp_error->get_error_code();
			$message = $wp_error->get_error_message();
		} elseif ( $wp_error instanceof Exception ) {
			$code = $wp_error->getCode();
			$message = $wp_error->getMessage();
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->auth0_error_logs,
			array(
				'section' => $section,
				'date' => date( 'c' ),
				'code' => $code,
				'message' => $message,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	public static function delete_user( $user_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->auth0_user, array( 'wp_id' => $user_id ), array( '%d' ) );
	}

	public static function wp_init() {
		self::setup_rewrites();
	}

	private static function setup_rewrites() {
		add_rewrite_tag( '%auth0%', '([^&]+)' );
		add_rewrite_tag( '%code%', '([^&]+)' );
		add_rewrite_tag( '%state%', '([^&]+)' );
		add_rewrite_tag( '%auth0_error%', '([^&]+)' );
		add_rewrite_rule( '^auth0', 'index.php?auth0=1', 'top' );
	}

	public static function install() {
		WP_Auth0_DBManager::install_db();
		self::setup_rewrites();

		flush_rewrite_rules();
	}

	public static function uninstall() {
		flush_rewrite_rules();
	}

	private static function autoloader( $class ) {
		$path = WPA0_PLUGIN_DIR;
		$paths = array();
		$exts = array( '.php', '.class.php' );

		$paths[] = $path;
		$paths[] = $path.'lib/';
		$paths[] = $path.'lib/exceptions/';
		$paths[] = $path.'lib/dashboard-widgets/';

		foreach ( $paths as $p ) {
			foreach ( $exts as $ext ) {
				if ( file_exists( $p.$class.$ext ) ) {
					require_once( $p.$class.$ext );
					return true;
				}
			}
		}

		return false;
	}
}

if ( ! function_exists( 'get_currentauth0userinfo' ) ) {
	function get_currentauth0userinfo() {
		global $current_user;
		global $currentauth0_user;
		global $wpdb;

		get_currentuserinfo();

		if ( $current_user instanceof WP_User && $current_user->ID > 0 ) {
			$sql = 'SELECT auth0_obj FROM ' . $wpdb->auth0_user .' WHERE wp_id = %d';
			$result = $wpdb->get_row( $wpdb->prepare( $sql, $current_user->ID ) );

			if ( is_null( $result ) || $result instanceof WP_Error ) {
				return null;
			}
			$currentauth0_user = unserialize( $result->auth0_obj );
		}

		return $currentauth0_user;
	}
}

WP_Auth0::init();
