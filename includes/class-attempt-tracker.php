<?php
/**
 * Attempt Tracker.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Attempt_Tracker
 *
 * Tracks and validates login attempts per IP.
 */
class Attempt_Tracker {

    /**
     * Get hashed current visitor IP.
     */
    public static function get_ip_hash(): string {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            // Handle comma-separated list.
            $ip = explode( ',', $ip )[0];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Normalise loopback.
        if ( in_array( $ip, [ '::1', '127.0.0.1' ], true ) ) {
            $ip = '127.0.0.1';
        }

        return hash( 'sha256', $ip . wp_salt() );
    }

    /**
     * Check if current IP is locked.
     *
     * @return bool
     */
    public static function is_locked(): bool {
        $ip_hash = self::get_ip_hash();
        $record  = Database::get_record( $ip_hash );

        if ( ! $record ) {
            return false;
        }

        if ( empty( $record->locked_until ) ) {
            return false;
        }

        $locked_until = strtotime( $record->locked_until );
        if ( $locked_until > time() ) {
            return true;
        }

        // Lock expired — reset.
        Database::reset_record( $ip_hash );
        return false;
    }

    /**
     * Get remaining lockout time in minutes.
     *
     * @return int
     */
    public static function get_remaining_minutes(): int {
        $ip_hash = self::get_ip_hash();
        $record  = Database::get_record( $ip_hash );

        if ( ! $record || empty( $record->locked_until ) ) {
            return 0;
        }

        $remaining = strtotime( $record->locked_until ) - time();
        return max( 0, (int) ceil( $remaining / 60 ) );
    }

    /**
     * Record a failed attempt.
     */
    public static function record_failure(): void {
        $ip_hash     = self::get_ip_hash();
        $record      = Database::get_record( $ip_hash );
        $max         = (int) get_option( 'loginmanager_max_attempts', 5 );
        $lockout_min = (int) get_option( 'loginmanager_lockout_minutes', 10 );

        if ( $record ) {
            $new_attempts = $record->attempts + 1;
            $locked_until = null;

            if ( $new_attempts >= $max ) {
                $locked_until = gmdate(
                    'Y-m-d H:i:s',
                    time() + ( $lockout_min * 60 )
                );

                // Send admin notification.
                self::maybe_notify_admin( $new_attempts );
            }

            Database::update_record( $record->id, $new_attempts, $locked_until );
        } else {
            Database::insert_record( $ip_hash );
        }
    }

    /**
     * Get remaining attempts before lockout.
     *
     * @return int
     */
    public static function get_remaining_attempts(): int {
        $ip_hash = self::get_ip_hash();
        $record  = Database::get_record( $ip_hash );
        $max     = (int) get_option( 'loginmanager_max_attempts', 5 );

        if ( ! $record ) {
            return $max;
        }

        return max( 0, $max - $record->attempts );
    }

    /**
     * Reset attempts on successful login.
     */
    public static function record_success(): void {
        $ip_hash = self::get_ip_hash();
        Database::reset_record( $ip_hash );
    }

    /**
     * Send email notification to admin.
     *
     * @param int $attempts Number of attempts.
     */
    private static function maybe_notify_admin( int $attempts ): void {
        $notify = get_option( 'loginmanager_notify_email', '' );
        if ( empty( $notify ) ) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] LoginManager: IP Locked Out', 'login-manager' ),
            get_bloginfo( 'name' )
        );

        $message = sprintf(
            /* translators: 1: attempt count, 2: lockout minutes */
            __( 'An IP address has been locked out after %1$d failed login attempts. Lockout duration: %2$d minutes.', 'login-manager' ),
            $attempts,
            (int) get_option( 'loginmanager_lockout_minutes', 10 )
        );

        wp_mail( $notify, $subject, $message );
    }
}