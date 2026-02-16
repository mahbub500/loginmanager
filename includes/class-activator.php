<?php
/**
 * Plugin Activator.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Activator
 *
 * Runs on plugin activation.
 */
class Activator {

    /**
     * Run activation tasks.
     */
    public static function activate(): void {
        Database::create_tables();
        self::set_default_options();
        // Flag to show setup wizard.
        set_transient( 'loginmanager_activation_redirect', true, 30 );
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options(): void {
        $defaults = [
            'loginmanager_max_attempts'    => 5,
            'loginmanager_lockout_minutes' => 10,
            'loginmanager_captcha_after'   => 3,
            'loginmanager_enable_captcha'  => 1,
            'loginmanager_notify_email'    => get_option( 'admin_email' ),
            'loginmanager_install_date'    => current_time( 'mysql' ),
        ];

        foreach ( $defaults as $key => $value ) {
            add_option( $key, $value );
        }
    }
}