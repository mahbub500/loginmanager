<?php
/**
 * Plugin Name: LoginManager
 * Plugin URI:  https://example.com/login-manager
 * Description: Limits login attempts and blocks IPs to protect against brute force attacks.
 * Author:      Mahbub
 * Author URI:  https://example.com
 * Text Domain: login-manager
 * Domain Path: /languages
 * Version:     1.0.0
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LOGINMANAGER_VERSION',  '1.0.0' );
define( 'LOGINMANAGER_FILE',     __FILE__ );
define( 'LOGINMANAGER_DIR',      plugin_dir_path( __FILE__ ) );
define( 'LOGINMANAGER_URL',      plugin_dir_url( __FILE__ ) );
define( 'LOGINMANAGER_BASENAME', plugin_basename( __FILE__ ) );

// PSR-4 style autoloader.
spl_autoload_register(
    function ( string $class_name ) {
        $prefix   = 'LoginManager\\';
        $base_dir = LOGINMANAGER_DIR . 'includes/';
        $len      = strlen( $prefix );

        if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
            return;
        }

        $relative = substr( $class_name, $len );
        $file     = $base_dir . 'class-' . strtolower(
            str_replace( [ '\\', '_' ], [ '/', '-' ], $relative )
        ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
);

register_activation_hook( LOGINMANAGER_FILE,   [ Activator::class,   'activate'   ] );
register_deactivation_hook( LOGINMANAGER_FILE, [ Deactivator::class, 'deactivate' ] );

add_action(
    'plugins_loaded',
    function () {
        Plugin::get_instance()->init();
    },
    1
);