<?php
/**
 * Setup Wizard.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Setup_Wizard
 *
 * Multi-step setup wizard shown on first activation.
 */
class Setup_Wizard {

    /**
     * Current wizard step.
     *
     * @var int
     */
    private int $step;

    /**
     * Total steps.
     *
     * @var int
     */
    private const TOTAL_STEPS = 3;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->step = (int) get_option( 'loginmanager_wizard_step', 1 );
    }

    /**
     * Register wizard hooks.
     */
    public function register(): void {
        add_action( 'admin_menu',            [ $this, 'register_wizard_page' ] );
        add_action( 'admin_init',            [ $this, 'maybe_redirect_to_wizard' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_wizard_assets' ] );
        add_action( 'admin_post_loginmanager_wizard_save', [ $this, 'handle_wizard_save' ] );
    }

    /**
     * Register hidden wizard admin page.
     */
    public function register_wizard_page(): void {
        add_dashboard_page(
            __( 'LoginManager Setup', 'login-manager' ),
            __( 'LoginManager Setup', 'login-manager' ),
            'manage_options',
            'loginmanager-wizard',
            [ $this, 'render_wizard' ]
        );
    }

    /**
     * Redirect to wizard after activation.
     */
    public function maybe_redirect_to_wizard(): void {
        if ( ! get_option( 'loginmanager_show_wizard', false ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        if ( isset( $_GET['page'] ) && 'loginmanager-wizard' === $_GET['page'] ) {
            return;
        }

        if ( wp_doing_ajax() ) {
            return;
        }

        update_option( 'loginmanager_show_wizard', false );
        wp_safe_redirect( admin_url( 'index.php?page=loginmanager-wizard' ) );
        exit;
    }

    /**
     * Enqueue wizard assets.
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_wizard_assets( string $hook ): void {
        if ( 'dashboard_page_loginmanager-wizard' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'loginmanager-admin',
            LOGINMANAGER_URL . 'assets/css/admin.css',
            [],
            LOGINMANAGER_VERSION
        );

        wp_enqueue_script(
            'loginmanager-wizard',
            LOGINMANAGER_URL . 'assets/js/wizard.js',
            [ 'jquery' ],
            LOGINMANAGER_VERSION,
            true
        );
    }

    /**
     * Handle wizard form submission.
     */
    public function handle_wizard_save(): void {
        check_admin_referer( 'loginmanager_wizard_step' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'login-manager' ) );
        }

        $step = isset( $_POST['wizard_step'] ) ? absint( $_POST['wizard_step'] ) : 1;

        switch ( $step ) {
            case 1:
                $max     = isset( $_POST['loginmanager_max_attempts'] ) ? absint( $_POST['loginmanager_max_attempts'] ) : 5;
                $lockout = isset( $_POST['loginmanager_lockout_minutes'] ) ? absint( $_POST['loginmanager_lockout_minutes'] ) : 10;
                update_option( 'loginmanager_max_attempts',    max( 1, $max ) );
                update_option( 'loginmanager_lockout_minutes', max( 1, $lockout ) );
                break;

            case 2:
                $captcha_after  = isset( $_POST['loginmanager_captcha_after'] ) ? absint( $_POST['loginmanager_captcha_after'] ) : 3;
                $enable_captcha = isset( $_POST['loginmanager_enable_captcha'] ) ? 1 : 0;
                update_option( 'loginmanager_captcha_after',  $captcha_after );
                update_option( 'loginmanager_enable_captcha', $enable_captcha );
                break;

            case 3:
                $email = isset( $_POST['loginmanager_notify_email'] ) ? sanitize_email( wp_unslash( $_POST['loginmanager_notify_email'] ) ) : '';
                update_option( 'loginmanager_notify_email', $email );
                // Mark wizard complete.
                update_option( 'loginmanager_wizard_step', 0 );
                wp_safe_redirect( admin_url( 'index.php?page=loginmanager-wizard&step=complete' ) );
                exit;
        }

        $next_step = $step + 1;
        update_option( 'loginmanager_wizard_step', $next_step );
        wp_safe_redirect( admin_url( 'index.php?page=loginmanager-wizard&step=' . $next_step ) );
        exit;
    }

    /**
     * Render the wizard page.
     */
    public function render_wizard(): void {
        // phpcs:ignore WordPress.Security.NonceVerification
        $current_step = isset( $_GET['step'] ) ? sanitize_text_field( wp_unslash( $_GET['step'] ) ) : '1';

        if ( 'complete' === $current_step ) {
            $this->render_complete();
            return;
        }

        $step = (int) $current_step;
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'LoginManager Setup Wizard', 'login-manager' ); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="lm-wizard-body">
            <div class="lm-wizard">
                <!-- Wizard Header -->
                <div class="lm-wizard__header">
                    <div class="lm-wizard__logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="#6366f1"/>
                            <path d="M10 17l-3-3 1.41-1.41L10 14.17l5.59-5.58L17 10l-7 7z" fill="white"/>
                        </svg>
                        <span>LoginManager</span>
                    </div>
                    <p><?php esc_html_e( 'Quick Setup Wizard', 'login-manager' ); ?></p>
                </div>

                <!-- Progress Bar -->
                <?php
                $step_labels = [
                    1 => __( 'Attempt Limits', 'login-manager' ),
                    2 => __( 'Captcha',        'login-manager' ),
                    3 => __( 'Notifications',  'login-manager' ),
                ];
                ?>
                <div class="lm-step-bar">
                    <?php foreach ( $step_labels as $i => $label ) :
                        $class = $i < $step ? 'is-done' : ( $i === $step ? 'is-active' : '' );
                    ?>
                    <div class="lm-step-bar__item <?php echo esc_attr( $class ); ?>">
                        <div class="lm-step-bar__dot">
                            <?php if ( $i < $step ) : ?>
                                <svg viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?php else : ?>
                                <?php echo esc_html( $i ); ?>
                            <?php endif; ?>
                        </div>
                        <span class="lm-step-bar__label"><?php echo esc_html( $label ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Wizard Card -->
                <div class="lm-wizard__card">
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'loginmanager_wizard_step' ); ?>
                        <input type="hidden" name="action" value="loginmanager_wizard_save">
                        <input type="hidden" name="wizard_step" value="<?php echo esc_attr( $step ); ?>">

                        <?php
                        switch ( $step ) {
                            case 1: $this->render_step_1(); break;
                            case 2: $this->render_step_2(); break;
                            case 3: $this->render_step_3(); break;
                        }
                        ?>

                        <div class="lm-wizard__actions">
                            <?php if ( $step > 1 ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'index.php?page=loginmanager-wizard&step=' . ( $step - 1 ) ) ); ?>" class="lm-btn lm-btn--ghost">
                                    &larr; <?php esc_html_e( 'Back', 'login-manager' ); ?>
                                </a>
                            <?php else : ?>
                                <span></span>
                            <?php endif; ?>

                            <button type="submit" class="lm-btn lm-btn--primary">
                                <?php echo $step < self::TOTAL_STEPS ? esc_html__( 'Next Step →', 'login-manager' ) : esc_html__( 'Finish Setup ✓', 'login-manager' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <p class="lm-wizard__skip">
                    <a href="<?php echo esc_url( admin_url( 'options-general.php?page=loginmanager' ) ); ?>">
                        <?php esc_html_e( 'Skip wizard, configure manually', 'login-manager' ); ?>
                    </a>
                </p>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Render Step 1 – Attempt limits.
     */
    private function render_step_1(): void {
        ?>
        <div class="lm-wizard__step-content">
            <h2><?php esc_html_e( 'Login Attempt Limits', 'login-manager' ); ?></h2>
            <p><?php esc_html_e( 'Set how many failed attempts are allowed before an IP is temporarily blocked.', 'login-manager' ); ?></p>

            <div class="lm-field">
                <label for="lm_wz_max"><?php esc_html_e( 'Maximum failed attempts', 'login-manager' ); ?></label>
                <div class="lm-field__input-wrap">
                    <input type="number" id="lm_wz_max" name="loginmanager_max_attempts"
                        value="<?php echo esc_attr( get_option( 'loginmanager_max_attempts', 5 ) ); ?>"
                        min="1" max="20">
                    <span class="lm-field__unit"><?php esc_html_e( 'attempts', 'login-manager' ); ?></span>
                </div>
                <span class="lm-field__hint"><?php esc_html_e( 'Recommended: 5', 'login-manager' ); ?></span>
            </div>

            <div class="lm-field">
                <label for="lm_wz_lockout"><?php esc_html_e( 'Lockout duration', 'login-manager' ); ?></label>
                <div class="lm-field__input-wrap">
                    <input type="number" id="lm_wz_lockout" name="loginmanager_lockout_minutes"
                        value="<?php echo esc_attr( get_option( 'loginmanager_lockout_minutes', 10 ) ); ?>"
                        min="1" max="1440">
                    <span class="lm-field__unit"><?php esc_html_e( 'minutes', 'login-manager' ); ?></span>
                </div>
                <span class="lm-field__hint"><?php esc_html_e( 'Recommended: 10–30 minutes', 'login-manager' ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render Step 2 – Captcha settings.
     */
    private function render_step_2(): void {
        $captcha_after  = (int) get_option( 'loginmanager_captcha_after', 3 );
        $enable_captcha = (int) get_option( 'loginmanager_enable_captcha', 1 );
        ?>
        <div class="lm-wizard__step-content">
            <h2><?php esc_html_e( 'Captcha Protection', 'login-manager' ); ?></h2>
            <p><?php esc_html_e( 'Challenge suspicious users with a captcha after repeated failures.', 'login-manager' ); ?></p>

            <div class="lm-field">
                <label class="lm-toggle-label">
                    <span><?php esc_html_e( 'Enable Captcha', 'login-manager' ); ?></span>
                    <label class="lm-toggle">
                        <input type="checkbox" name="loginmanager_enable_captcha" id="lm_wz_captcha"
                            <?php checked( $enable_captcha, 1 ); ?>>
                        <span class="lm-toggle__slider"></span>
                    </label>
                </label>
                <span class="lm-field__hint"><?php esc_html_e( 'Show a captcha challenge after multiple failed attempts.', 'login-manager' ); ?></span>
            </div>

            <div class="lm-field" id="lm-captcha-after-field">
                <label for="lm_wz_captcha_after"><?php esc_html_e( 'Show captcha after', 'login-manager' ); ?></label>
                <div class="lm-field__input-wrap">
                    <input type="number" id="lm_wz_captcha_after" name="loginmanager_captcha_after"
                        value="<?php echo esc_attr( $captcha_after ); ?>"
                        min="1" max="10">
                    <span class="lm-field__unit"><?php esc_html_e( 'failed attempts', 'login-manager' ); ?></span>
                </div>
                <span class="lm-field__hint"><?php esc_html_e( 'Recommended: 3', 'login-manager' ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render Step 3 – Notifications.
     */
    private function render_step_3(): void {
        ?>
        <div class="lm-wizard__step-content">
            <h2><?php esc_html_e( 'Email Notifications', 'login-manager' ); ?></h2>
            <p><?php esc_html_e( 'Get notified by email when an IP is locked out.', 'login-manager' ); ?></p>

            <div class="lm-field">
                <label for="lm_wz_email"><?php esc_html_e( 'Notification email address', 'login-manager' ); ?></label>
                <input type="email" id="lm_wz_email" name="loginmanager_notify_email"
                    value="<?php echo esc_attr( get_option( 'loginmanager_notify_email', get_option( 'admin_email' ) ) ); ?>"
                    placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                <span class="lm-field__hint"><?php esc_html_e( 'Leave blank to disable email alerts.', 'login-manager' ); ?></span>
            </div>

            <div class="lm-info-box">
                <strong><?php esc_html_e( 'Almost done!', 'login-manager' ); ?></strong>
                <?php esc_html_e( 'Click "Finish Setup" to activate LoginManager with your chosen settings. You can always change these later in Settings → LoginManager.', 'login-manager' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render wizard complete screen.
     */
    private function render_complete(): void {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php esc_html_e( 'LoginManager — Setup Complete', 'login-manager' ); ?></title>
            <?php wp_head(); ?>
        </head>
        <body class="lm-wizard-body">
            <div class="lm-wizard">
                <div class="lm-wizard__complete">
                    <div class="lm-wizard__check">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="#6366f1"/>
                            <path d="M9 12l2 2 4-4" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <h1><?php esc_html_e( 'You\'re all set!', 'login-manager' ); ?></h1>
                    <p><?php esc_html_e( 'LoginManager is now protecting your site from brute force attacks.', 'login-manager' ); ?></p>
                    <div class="lm-wizard__complete-actions">
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=loginmanager' ) ); ?>" class="lm-btn lm-btn--primary">
                            <?php esc_html_e( 'Go to Dashboard', 'login-manager' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url() ); ?>" class="lm-btn lm-btn--ghost">
                            <?php esc_html_e( 'WordPress Admin', 'login-manager' ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}