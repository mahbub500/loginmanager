<?php
/**
 * Core Plugin class.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plugin
 *
 * Bootstraps all plugin components.
 */
class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin
     */
    private static Plugin $instance;

    /**
     * Get singleton instance.
     */
    public static function get_instance(): Plugin {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin components.
     */
    public function init(): void {
        $this->load_textdomain();

        ( new Login_Hooks() )->register();
        ( new Admin() )->register();

        // Show setup wizard on first activation.
        if ( get_option( 'loginmanager_show_wizard', false ) ) {
            ( new Setup_Wizard() )->register();
        }
    }

    /**
     * Load plugin text domain.
     */
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'login-manager',
            false,
            dirname( LOGINMANAGER_BASENAME ) . '/languages'
        );
    }

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void {
        throw new \Exception( 'Cannot unserialize singleton.' );
    }
}