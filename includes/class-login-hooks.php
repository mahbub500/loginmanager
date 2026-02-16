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
        add_action( 'login_form',            [ $this, 'inject_captcha_field' ] );
        add_filter( 'authenticate',          [ $this, 'check_lockout_and_captcha' ], 20, 3 );
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
                'remaining'     => Attempt_Tracker::get_remaining_attempts(),
                'max'           => (int) get_option( 'loginmanager_max_attempts', 5 ),
                'locked'        => Attempt_Tracker::is_locked(),
                'lockTime'      => Attempt_Tracker::get_remaining_minutes(),
                'showCaptcha'   => Captcha::should_show(),
            ]
        );
    }

    /**
     * Inject math captcha field into the login form.
     */
    public function inject_captcha_field(): void {
        if ( ! Captcha::should_show() ) {
            return;
        }

        $captcha = Captcha::generate();
        ?>
        <div class="lm-captcha-wrap" id="lm-captcha-wrap">
            <label for="lm_captcha_answer" class="lm-captcha-label">
                <span class="lm-captcha-icon">🔐</span>
                <?php esc_html_e( 'Security Check', 'login-manager' ); ?>
            </label>
            <div class="lm-captcha-box">
                <div class="lm-captcha-question" id="lm-captcha-question">
                    <?php echo esc_html( $captcha['question'] ); ?>
                </div>
                <input
                    type="number"
                    id="lm_captcha_answer"
                    name="lm_captcha_answer"
                    class="lm-captcha-input input"
                    placeholder="<?php esc_attr_e( 'Your answer', 'login-manager' ); ?>"
                    autocomplete="off"
                    required
                >
            </div>
            <input
                type="hidden"
                name="lm_captcha_token"
                value="<?php echo esc_attr( $captcha['token'] ); ?>"
            >
            <span class="lm-captcha-hint">
                <?php esc_html_e( 'Solve the math problem to continue.', 'login-manager' ); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Block locked IPs and validate captcha before authentication.
     *
     * @param mixed  $user     Current user or error.
     * @param string $username Username.
     * @param string $password Password.
     * @return mixed
     */
    public function check_lockout_and_captcha( $user, string $username, string $password ) {
        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        // 1. Check if IP is locked out.
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

        // 2. Validate captcha if it should be shown.
        if ( Captcha::should_show() ) {
            // phpcs:disable WordPress.Security.NonceVerification
            $token  = isset( $_POST['lm_captcha_token'] )  ? sanitize_text_field( wp_unslash( $_POST['lm_captcha_token'] ) )  : '';
            $answer = isset( $_POST['lm_captcha_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['lm_captcha_answer'] ) ) : '';
            // phpcs:enable

            if ( empty( $token ) || empty( $answer ) ) {
                $error = new \WP_Error();
                $error->add(
                    'loginmanager_captcha_empty',
                    __( '<strong>Security Check Failed.</strong> Please answer the math question.', 'login-manager' )
                );
                return $error;
            }

            if ( ! Captcha::validate( $token, $answer ) ) {
                $error = new \WP_Error();
                $error->add(
                    'loginmanager_captcha_wrong',
                    __( '<strong>Wrong Answer.</strong> Please solve the math problem correctly.', 'login-manager' )
                );
                return $error;
            }
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
        // Don't append remaining attempts if locked or captcha error.
        if (
            Attempt_Tracker::is_locked() ||
            strpos( $error, 'loginmanager' ) !== false
        ) {
            return $error;
        }

        $remaining = Attempt_Tracker::get_remaining_attempts();
        $max       = (int) get_option( 'loginmanager_max_attempts', 5 );

        if ( $remaining < $max && $remaining > 0 ) {
            $error .= sprintf(
                '<br><span class="loginmanager-attempts">⚠ %s</span>',
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