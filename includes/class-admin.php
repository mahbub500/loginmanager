<?php
/**
 * Admin Panel.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Admin
 *
 * Handles WP admin settings and dashboard.
 */
class Admin {

    /**
     * Register admin hooks.
     */
    public function register(): void {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'admin_notices',         [ $this, 'admin_notices' ] );
        add_action( 'admin_post_loginmanager_delete_record', [ $this, 'handle_delete_record' ] );
        add_filter( "plugin_action_links_" . LOGINMANAGER_BASENAME, [ $this, 'add_settings_link' ] );
    }

    /**
     * Register admin menu.
     */
    public function register_menu(): void {
        add_options_page(
            __( 'LoginManager Settings', 'login-manager' ),
            __( 'LoginManager', 'login-manager' ),
            'manage_options',
            'loginmanager',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'settings_page_loginmanager' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'loginmanager-admin',
            LOGINMANAGER_URL . 'assets/css/admin.css',
            [],
            LOGINMANAGER_VERSION
        );
    }

    /**
     * Register settings fields.
     */
    public function register_settings(): void {
        register_setting( 'loginmanager_settings', 'loginmanager_max_attempts',    [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'loginmanager_settings', 'loginmanager_lockout_minutes', [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'loginmanager_settings', 'loginmanager_captcha_after',   [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'loginmanager_settings', 'loginmanager_enable_captcha',  [ 'type' => 'integer', 'sanitize_callback' => 'absint' ] );
        register_setting( 'loginmanager_settings', 'loginmanager_notify_email',    [ 'type' => 'string',  'sanitize_callback' => 'sanitize_email' ] );
    }

    /**
     * Admin notices.
     */
    public function admin_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show after wizard is complete.
        if ( get_option( 'loginmanager_show_wizard', false ) ) {
            return;
        }

        $locked = array_filter(
            Database::get_all_records(),
            fn( $r ) => ! empty( $r->locked_until ) && strtotime( $r->locked_until ) > time()
        );

        if ( count( $locked ) > 0 ) {
            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
                sprintf(
                    /* translators: %d: locked IP count */
                    esc_html__( 'LoginManager: %d IP(s) are currently locked out.', 'login-manager' ),
                    count( $locked )
                ),
                esc_url( admin_url( 'options-general.php?page=loginmanager' ) ),
                esc_html__( 'View details', 'login-manager' )
            );
        }
    }

    /**
     * Add settings link to plugin list.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_settings_link( array $links ): array {
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'options-general.php?page=loginmanager' ) ),
            esc_html__( 'Settings', 'login-manager' )
        );
        array_unshift( $links, $settings );
        return $links;
    }

    /**
     * Handle delete record POST action.
     */
    public function handle_delete_record(): void {
        check_admin_referer( 'loginmanager_delete_record' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'login-manager' ) );
        }

        $id = isset( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
        if ( $id ) {
            Database::delete_record( $id );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=loginmanager&deleted=1' ) );
        exit;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'login-manager' ) );
        }

        $records = Database::get_all_records();
        $deleted = isset( $_GET['deleted'] ) ? true : false; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="lm-wrap">
            <div class="lm-header">
                <div class="lm-header__logo">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z" fill="#6366f1"/>
                        <path d="M10 17l-3-3 1.41-1.41L10 14.17l5.59-5.58L17 10l-7 7z" fill="white"/>
                    </svg>
                    <span><?php esc_html_e( 'LoginManager', 'login-manager' ); ?></span>
                </div>
                <p class="lm-header__sub"><?php esc_html_e( 'Brute force protection &amp; login attempt management', 'login-manager' ); ?></p>
            </div>

            <?php if ( $deleted ) : ?>
            <div class="lm-alert lm-alert--success"><?php esc_html_e( 'Record deleted successfully.', 'login-manager' ); ?></div>
            <?php endif; ?>

            <div class="lm-grid">
                <!-- Settings Panel -->
                <div class="lm-card">
                    <h2 class="lm-card__title"><?php esc_html_e( 'Settings', 'login-manager' ); ?></h2>
                    <form method="POST" action="options.php">
                        <?php settings_fields( 'loginmanager_settings' ); ?>
                        <div class="lm-field">
                            <label for="lm_max_attempts"><?php esc_html_e( 'Max Login Attempts', 'login-manager' ); ?></label>
                            <input type="number" id="lm_max_attempts" name="loginmanager_max_attempts" value="<?php echo esc_attr( get_option( 'loginmanager_max_attempts', 5 ) ); ?>" min="1" max="100">
                            <span class="lm-field__hint"><?php esc_html_e( 'Number of allowed attempts before lockout.', 'login-manager' ); ?></span>
                        </div>
                        <div class="lm-field">
                            <label for="lm_lockout_minutes"><?php esc_html_e( 'Lockout Duration (minutes)', 'login-manager' ); ?></label>
                            <input type="number" id="lm_lockout_minutes" name="loginmanager_lockout_minutes" value="<?php echo esc_attr( get_option( 'loginmanager_lockout_minutes', 10 ) ); ?>" min="1" max="1440">
                            <span class="lm-field__hint"><?php esc_html_e( 'How long to lock out an IP after max attempts.', 'login-manager' ); ?></span>
                        </div>
                        <div class="lm-field">
                            <label for="lm_notify_email"><?php esc_html_e( 'Notification Email', 'login-manager' ); ?></label>
                            <input type="email" id="lm_notify_email" name="loginmanager_notify_email" value="<?php echo esc_attr( get_option( 'loginmanager_notify_email', get_option('admin_email') ) ); ?>">
                            <span class="lm-field__hint"><?php esc_html_e( 'Leave blank to disable email notifications.', 'login-manager' ); ?></span>
                        </div>
                        <button type="submit" class="lm-btn lm-btn--primary"><?php esc_html_e( 'Save Settings', 'login-manager' ); ?></button>
                    </form>
                </div>

                <!-- Stats Panel -->
                <div class="lm-card">
                    <h2 class="lm-card__title"><?php esc_html_e( 'Overview', 'login-manager' ); ?></h2>
                    <?php
                    $total  = count( $records );
                    $locked = count( array_filter( $records, fn( $r ) => ! empty( $r->locked_until ) && strtotime( $r->locked_until ) > time() ) );
                    $safe_pct = $total > 0 ? round( ( ( $total - $locked ) / $total ) * 100 ) : 100;
                    ?>

                    <div class="lm-ring-wrap">
                        <div class="lm-ring" data-lm-ring="<?php echo esc_attr( $safe_pct ); ?>">
                            <svg viewBox="0 0 100 100">
                                <circle class="lm-ring__track" cx="50" cy="50" r="40"/>
                                <circle class="lm-ring__fill"  cx="50" cy="50" r="40"/>
                            </svg>
                            <div class="lm-ring__center">
                                <span class="lm-ring__number"><?php echo esc_html( $safe_pct ); ?>%</span>
                                <span class="lm-ring__unit"><?php esc_html_e( 'Safe', 'login-manager' ); ?></span>
                            </div>
                        </div>
                        <span class="lm-ring-label"><?php esc_html_e( 'IPs with clean status', 'login-manager' ); ?></span>
                    </div>
                    <div class="lm-stats">
                        <div class="lm-stat">
                            <span class="lm-stat__number"><?php echo esc_html( $total ); ?></span>
                            <span class="lm-stat__label"><?php esc_html_e( 'Total IPs Tracked', 'login-manager' ); ?></span>
                        </div>
                        <div class="lm-stat lm-stat--danger">
                            <span class="lm-stat__number"><?php echo esc_html( $locked ); ?></span>
                            <span class="lm-stat__label"><?php esc_html_e( 'Currently Locked', 'login-manager' ); ?></span>
                        </div>
                    </div>

                    <h3 class="lm-section-title"><?php esc_html_e( 'Recent Activity', 'login-manager' ); ?></h3>
                    <?php if ( empty( $records ) ) : ?>
                        <p class="lm-empty"><?php esc_html_e( 'No login attempts recorded yet.', 'login-manager' ); ?></p>
                    <?php else : ?>
                        <div class="lm-table-wrap">
                            <table class="lm-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'IP (Hashed)', 'login-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Attempts', 'login-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'login-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Last Attempt', 'login-manager' ); ?></th>
                                        <th><?php esc_html_e( 'Action', 'login-manager' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $records as $record ) :
                                        $is_locked   = ! empty( $record->locked_until ) && strtotime( $record->locked_until ) > time();
                                        $status_class = $is_locked ? 'lm-badge lm-badge--danger' : 'lm-badge lm-badge--success';
                                        $status_text  = $is_locked ? __( 'Locked', 'login-manager' ) : __( 'Active', 'login-manager' );
                                    ?>
                                    <tr>
                                        <td><code class="lm-code"><?php echo esc_html( substr( $record->ip_hash, 0, 12 ) . '...' ); ?></code></td>
                                        <td><strong><?php echo esc_html( $record->attempts ); ?></strong></td>
                                        <td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
                                        <td><?php echo esc_html( $record->last_attempt ); ?></td>
                                        <td>
                                            <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                                <?php wp_nonce_field( 'loginmanager_delete_record' ); ?>
                                                <input type="hidden" name="action" value="loginmanager_delete_record">
                                                <input type="hidden" name="record_id" value="<?php echo esc_attr( $record->id ); ?>">
                                                <button type="submit" class="lm-btn lm-btn--danger lm-btn--sm" onclick="return confirm('<?php esc_attr_e( 'Delete this record?', 'login-manager' ); ?>')"><?php esc_html_e( 'Delete', 'login-manager' ); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}