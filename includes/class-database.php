<?php
/**
 * Database handler.
 *
 * @package LoginManager
 */

namespace LoginManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Database
 *
 * Manages database table creation and queries.
 */
class Database {

    /**
     * Get full table name with WP prefix.
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'loginmanager_attempts';
    }

    /**
     * Create plugin database tables.
     */
    public static function create_tables(): void {
        global $wpdb;

        $table      = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_hash      VARCHAR(64)         NOT NULL,
            attempts     SMALLINT(6)         NOT NULL DEFAULT 0,
            first_attempt DATETIME           NOT NULL,
            last_attempt  DATETIME           NOT NULL,
            locked_until  DATETIME           DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_hash (ip_hash)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get record by IP hash.
     *
     * @param string $ip_hash Hashed IP address.
     * @return object|null
     */
    public static function get_record( string $ip_hash ): ?object {
        global $wpdb;
        $table = self::table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE ip_hash = %s LIMIT 1",
                $ip_hash
            )
        );
    }

    /**
     * Insert a new attempt record.
     *
     * @param string $ip_hash Hashed IP address.
     * @return bool
     */
    public static function insert_record( string $ip_hash ): bool {
        global $wpdb;
        $now = current_time( 'mysql' );

        $result = $wpdb->insert(
            self::table(),
            [
                'ip_hash'       => $ip_hash,
                'attempts'      => 1,
                'first_attempt' => $now,
                'last_attempt'  => $now,
                'locked_until'  => null,
            ],
            [ '%s', '%d', '%s', '%s', '%s' ]
        );

        return false !== $result;
    }

    /**
     * Update attempts count.
     *
     * @param int    $id       Record ID.
     * @param int    $attempts New attempt count.
     * @param string $locked_until Datetime to lock until, or null.
     * @return bool
     */
    public static function update_record( int $id, int $attempts, ?string $locked_until = null ): bool {
        global $wpdb;

        $data   = [
            'attempts'     => $attempts,
            'last_attempt' => current_time( 'mysql' ),
            'locked_until' => $locked_until,
        ];
        $format = [ '%d', '%s', '%s' ];

        $result = $wpdb->update(
            self::table(),
            $data,
            [ 'id' => $id ],
            $format,
            [ '%d' ]
        );

        return false !== $result;
    }

    /**
     * Reset record for IP (on successful login).
     *
     * @param string $ip_hash Hashed IP address.
     * @return bool
     */
    public static function reset_record( string $ip_hash ): bool {
        global $wpdb;

        $result = $wpdb->update(
            self::table(),
            [
                'attempts'     => 0,
                'locked_until' => null,
            ],
            [ 'ip_hash' => $ip_hash ],
            [ '%d', '%s' ],
            [ '%s' ]
        );

        return false !== $result;
    }

    /**
     * Get all locked IPs for admin display.
     *
     * @return array
     */
    public static function get_all_records(): array {
        global $wpdb;
        $table = self::table();

        return $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY last_attempt DESC"
        ) ?? [];
    }

    /**
     * Delete a record by ID.
     *
     * @param int $id Record ID.
     * @return bool
     */
    public static function delete_record( int $id ): bool {
        global $wpdb;

        $result = $wpdb->delete(
            self::table(),
            [ 'id' => $id ],
            [ '%d' ]
        );

        return false !== $result;
    }
}