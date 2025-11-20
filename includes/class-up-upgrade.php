<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_Upgrade {
    const SCHEMA_VERSION = '1.1'; // event_id introduction
    const OPTION_KEY = 'up_db_version';

    public static function maybe_run() {
        $current = get_option( self::OPTION_KEY, '1.0' );
        if ( version_compare( $current, self::SCHEMA_VERSION, '>=' ) ) {
            return; // already upgraded
        }
        self::upgrade_event_id_column();
        update_option( self::OPTION_KEY, self::SCHEMA_VERSION );
    }

    private static function upgrade_event_id_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'up_capi_queue';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( $exists !== $table ) {
            return; // fresh install handled in activation
        }
        // check if column already exists
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'event_id'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN event_id VARCHAR(64) NOT NULL DEFAULT ''" );
            // add unique index
            $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE INDEX event_id (event_id)" );
            // backfill event_id values for existing rows in small batches
            $batch = 500;
            $offset = 0;
            while ( true ) {
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, platform, event_name, payload, created_at FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d", $batch, $offset ), ARRAY_A );
                if ( empty( $rows ) ) break;
                foreach ( $rows as $r ) {
                    if ( ! empty( $r['event_id'] ) ) continue; // skip if already set
                    $payload = json_decode( $r['payload'], true );
                    $event_id = self::derive_event_id( $r['platform'], $r['event_name'], $payload, $r['created_at'] );
                    $wpdb->update( $table, array( 'event_id' => $event_id ), array( 'id' => $r['id'] ), array( '%s' ), array( '%d' ) );
                }
                if ( count( $rows ) < $batch ) break;
                $offset += $batch;
            }
        }
    }

    public static function derive_event_id( $platform, $event_name, $payload, $created_at = null ) {
        $parts = array( (string) $platform, (string) $event_name );
        if ( is_array( $payload ) ) {
            if ( isset( $payload['user_data']['email_hash'] ) ) {
                $parts[] = (string) $payload['user_data']['email_hash'];
            } elseif ( isset( $payload['user_data']['phone_hash'] ) ) {
                $parts[] = (string) $payload['user_data']['phone_hash'];
            }
            if ( isset( $payload['custom_data']['order_id'] ) ) {
                $parts[] = (string) $payload['custom_data']['order_id'];
            } elseif ( isset( $payload['custom_data']['value'] ) && isset( $payload['custom_data']['currency'] ) ) {
                $parts[] = (string) $payload['custom_data']['value'] . ':' . (string) $payload['custom_data']['currency'];
            }
        }
        // hour bucket for determinism without infinite collision
        $bucket = $created_at ? gmdate( 'YmdH', intval( $created_at ) ) : gmdate( 'YmdH' );
        $parts[] = $bucket;
        $raw = implode( '|', $parts );
        return 'upe_' . substr( hash( 'sha256', $raw ), 0, 32 );
    }
}
