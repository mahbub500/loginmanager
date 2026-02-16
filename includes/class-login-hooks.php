<?php
/**
 * Login Hooks.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Login_Hooks
 *
 * Hooks into WordPress login events.
 */
class Login_Hooks {

    /**
     * Register all hooks.
     */
    public function register(): void {
        add_action( 'login_enqueue_scripts', [ $this, 'enqueue_login_assets' ] );
        add_filter( 'authenticate',          [ $this, 'check_lockout' ], 20, 3 );
        add_action( 'wp_login_failed',       [ $this, 'on_login_failed' ] );
        add_action( 'wp_login',              [ $this, 'on_login_success' ], 10, 2 );
        add_filter( 'login_errors',          [ $this, 'filter_login_errors' ] );
    }

    /**
     * Enqueue login page assets.
     */
    public function enqueue_login_assets(): void {
        wp_enqueue_style(
            'loginmanager-login',
            LOGINMANAGER_URL . 'assets/css/login.css',
            [],
            LOGINMANAGER_VERSION
        );

        wp_enqueue_script(
            'loginmanager-login',
            LOGINMANAGER_URL . 'assets/js/login.js',
            [ 'jquery' ],
            LOGINMANAGER_VERSION,
            true
        );

        wp_localize_script(
            'loginmanager-login',
            'LoginManagerData',
            [
                'remaining' => Attempt_Tracker::get_remaining_attempts(),
                'locked'    => Attempt_Tracker::is_locked(),
                'lockTime'  => Attempt_Tracker::get_remaining_minutes(),
            ]
        );
    }

    /**
     * Block locked IPs before authentication.
     *
     * @param mixed  $user     Current user or error.
     * @param string $username Username.
     * @param string $password Password.
     * @return mixed
     */
    public function check_lockout( $user, string $username, string $password ) {
        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        if ( Attempt_Tracker::is_locked() ) {
            $remaining = Attempt_Tracker::get_remaining_minutes();
            $error     = new \WP_Error();
            $error->add(
                'loginmanager_locked',
                sprintf(
                    /* translators: %d: minutes remaining */
                    __( '<strong>Access Blocked.</strong> Too many failed attempts. Please try again in %d minute(s).', 'login-manager' ),
                    $remaining
                )
            );
            return $error;
        }

        return $user;
    }

    /**
     * Handle failed login.
     *
     * @param string $username Username.
     */
    public function on_login_failed( string $username ): void {
        Attempt_Tracker::record_failure();
    }

    /**
     * Handle successful login.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       WP_User object.
     */
    public function on_login_success( string $user_login, \WP_User $user ): void {
        Attempt_Tracker::record_success();
    }

    /**
     * Filter login error messages.
     *
     * @param string $error Error HTML.
     * @return string
     */
    public function filter_login_errors( string $error ): string {
        if ( Attempt_Tracker::is_locked() ) {
            return $error; // Already set above.
        }

        $remaining = Attempt_Tracker::get_remaining_attempts();
        $max       = (int) get_option( 'loginmanager_max_attempts', 5 );

        if ( $remaining < $max ) {
            $error .= sprintf(
                '<br><span class="loginmanager-attempts">%s</span>',
                sprintf(
                    /* translators: %d: attempts remaining */
                    esc_html__( '%d attempt(s) remaining before lockout.', 'login-manager' ),
                    $remaining
                )
            );
        }

        return $error;
    }
}