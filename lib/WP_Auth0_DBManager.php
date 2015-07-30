<?php

class WP_Auth0_DBManager {

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'initialize_wpdb_tables' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'check_update' ) );
	}

	public static function initialize_wpdb_tables() {
		global $wpdb;

		$wpdb->auth0_log = $wpdb->prefix.'auth0_log';
		$wpdb->auth0_user = $wpdb->prefix.'auth0_user';
		$wpdb->auth0_error_logs = $wpdb->prefix.'auth0_error_logs';
	}

	public static function check_update() {
		if ( (int) get_site_option( 'auth0_db_version' ) !== AUTH0_DB_VERSION ) {
			self::install_db();
		}
	}

	public static function install_db() {
		global $wpdb;

		self::initialize_wpdb_tables();

		$sql = array();

		$sql[] = "CREATE TABLE ".$wpdb->auth0_log." (
					id INT(11) AUTO_INCREMENT NOT NULL,
					event VARCHAR(100) NOT NULL,
					level VARCHAR(100) NOT NULL DEFAULT 'notice',
					description TEXT,
					details LONGTEXT,
					logtime INT(11) NOT NULL,
					PRIMARY KEY  (id)
				);";

		$sql[] = "CREATE TABLE ".$wpdb->auth0_user." (
					auth0_id VARCHAR(100) NOT NULL,
					wp_id INT(11)  NOT NULL,
					auth0_obj TEXT,
					id_token TEXT,
					access_token TEXT,
					last_update DATETIME,
					PRIMARY KEY  (auth0_id)
				);";

		$sql[] = "CREATE TABLE ".$wpdb->auth0_error_logs." (
					id INT(11) AUTO_INCREMENT NOT NULL,
					date DATETIME  NOT NULL,
					section VARCHAR(255),
					code VARCHAR(255),
					message TEXT,
					PRIMARY KEY  (id)
				);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		foreach ( $sql as $s ) {
			dbDelta( $s );
		}
		update_option( 'auth0_db_version', AUTH0_DB_VERSION );


		$options = WP_Auth0_Options::Instance();
		$cdn_url = $options->get( 'cdn_url' );
		if ( strpos( $cdn_url, 'auth0-widget-5' ) !== false || strpos( $cdn_url, 'lock-6' ) !== false ) {
			$options->set( 'cdn_url', '//cdn.auth0.com/js/lock-7.min.js' );
		}

	}

	public static function get_auth0_users($user_ids = null) {
		global $wpdb;

		$where = '';
		if ( $user_ids ) {
			$ids = esc_sql( implode( ',',  array_filter( $user_ids, 'ctype_digit' ) ) );
			$where .= " AND u.id IN ($ids) ";
		}

		$sql = sprintf( 'SELECT a.* FROM %s a JOIN %s u ON a.wp_id = u.id %s', $wpdb->auth0_user, $wpdb->users, $where );
		$results = $wpdb->get_results( $sql );

		if ( $results instanceof WP_Error ) {
			self::insert_auth0_error( 'findAuth0User',$userRow );
			return array();
		}

		return $results;
	}

	public static function get_current_user_profiles() {
        global $current_user;
        global $wpdb;

        get_currentuserinfo();
        $userData = array();

        if ($current_user instanceof WP_User && $current_user->ID > 0 ) {
            $sql = 'SELECT auth0_obj
                    FROM ' . $wpdb->auth0_user .'
                    WHERE wp_id = %d';
            $results = $wpdb->get_results($wpdb->prepare($sql, $current_user->ID));

            if (is_null($results) || $results instanceof WP_Error ) {

                return null;
            }

            foreach ($results as $value) {
                $userData[] = unserialize($value->auth0_obj);
            }

        }

        return $userData;
    }
}
