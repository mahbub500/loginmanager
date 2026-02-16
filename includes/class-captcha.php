<?php
/**
 * Math Captcha Handler.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Captcha
 *
 * Generates and validates a simple math captcha.
 * Uses WP transients — no PHP sessions needed.
 */
class Captcha {

    /**
     * Transient key prefix.
     */
    const TRANSIENT_PREFIX = 'loginmanager_captcha_';

    /**
     * Captcha expiry in seconds.
     */
    const EXPIRY = 300; // 5 minutes.

    /**
     * Generate a new math captcha question and store answer in transient.
     *
     * @return array { question: string, token: string }
     */
    public static function generate(): array {
        $num1  = wp_rand( 1, 9 );
        $num2  = wp_rand( 1, 9 );
        $ops   = [ '+', '-', '+', '+' ]; // Weighted towards addition.
        $op    = $ops[ array_rand( $ops ) ];

        $answer = ( '+' === $op ) ? $num1 + $num2 : $num1 - $num2;

        // Build a unique token tied to this question.
        $token = wp_generate_password( 20, false, false );

        set_transient(
            self::TRANSIENT_PREFIX . $token,
            $answer,
            self::EXPIRY
        );

        return [
            'question' => sprintf( 'What is %d %s %d?', $num1, $op, $num2 ),
            'token'    => $token,
        ];
    }

    /**
     * Validate a captcha answer against its token.
     *
     * @param string $token  The captcha token from the hidden field.
     * @param string $answer The user's answer.
     * @return bool
     */
    public static function validate( string $token, string $answer ): bool {
        if ( empty( $token ) || ! is_numeric( $answer ) ) {
            return false;
        }

        $transient_key    = self::TRANSIENT_PREFIX . sanitize_text_field( $token );
        $expected_answer  = get_transient( $transient_key );

        if ( false === $expected_answer ) {
            return false; // Expired or invalid token.
        }

        // Delete transient immediately — one use only.
        delete_transient( $transient_key );

        return (int) $answer === (int) $expected_answer;
    }

    /**
     * Check if captcha should be shown for current IP.
     *
     * @return bool
     */
    public static function should_show(): bool {
        if ( ! (int) get_option( 'loginmanager_enable_captcha', 1 ) ) {
            return false;
        }

        $ip_hash       = Attempt_Tracker::get_ip_hash();
        $record        = Database::get_record( $ip_hash );
        $captcha_after = (int) get_option( 'loginmanager_captcha_after', 3 );

        if ( ! $record ) {
            return false;
        }

        return $record->attempts >= $captcha_after;
    }
}