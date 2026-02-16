<?php
/**
 * Plugin Deactivator.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Deactivator
 *
 * Runs on plugin deactivation.
 */
class Deactivator {

    /**
     * Run deactivation tasks.
     */
    public static function deactivate(): void {
        // Clean up transients.
        delete_transient( 'loginmanager_cache' );
    }
}