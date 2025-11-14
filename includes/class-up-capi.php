<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UP_CAPI {
	const QUEUE_OPTION = 'up_capi_queue';

	// enqueue payload into DB-backed queue for async processing
	public static function enqueue_event( $platform, $event_name, $payload = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$now = time();
		$inserted = $wpdb->insert( $table, array(
			'platform' => substr( (string) $platform, 0, 50 ),
			'event_name' => substr( (string) $event_name, 0, 191 ),
			'payload' => wp_json_encode( $payload ),
			'attempts' => 0,
			'next_attempt' => 0,
			'created_at' => $now,
		), array( '%s', '%s', '%s', '%d', '%d', '%d' ) );

		if ( $inserted ) {
			if ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
				wp_schedule_single_event( time() + 5, 'up_capi_process_queue' );
			}
			return true;
		}
		return false;
	}

	// process up to $limit events from the queue
	public static function process_queue( $limit = 10 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$dl_table = $wpdb->prefix . 'up_capi_deadletter';
		$now = time();

		// select eligible rows
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE (next_attempt = 0 OR next_attempt <= %d) ORDER BY created_at ASC LIMIT %d", $now, $limit ), ARRAY_A );
		if ( empty( $rows ) ) return 0;
		$processed = 0;
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			$res = self::send_event( $row['platform'], $row['event_name'], is_array( $payload ) ? $payload : array(), false );
			$success = true;
			if ( is_wp_error( $res ) ) {
				$success = false;
				$message = $res->get_error_message();
			} elseif ( is_array( $res ) && isset( $res['response'] ) ) {
				$code = intval( $res['response']['code'] );
				if ( $code < 200 || $code >= 300 ) {
					$success = false;
					$message = sprintf( 'HTTP %d', $code );
				}
			}

			if ( $success ) {
				$wpdb->delete( $table, array( 'id' => $row['id'] ), array( '%d' ) );
			} else {
				// increment attempts and either reschedule or dead-letter
				$attempts = intval( $row['attempts'] ) + 1;
				if ( $attempts >= 5 ) {
					// move to dead-letter
					$wpdb->insert( $dl_table, array(
						'platform' => $row['platform'],
						'event_name' => $row['event_name'],
						'payload' => $row['payload'],
						'failure_message' => isset( $message ) ? $message : 'failed',
						'failed_at' => $now,
					), array( '%s', '%s', '%s', '%s', '%d' ) );
					$wpdb->delete( $table, array( 'id' => $row['id'] ), array( '%d' ) );
				} else {
					$next = $now + ( 60 * $attempts );
					$wpdb->update( $table, array( 'attempts' => $attempts, 'next_attempt' => $next ), array( 'id' => $row['id'] ), array( '%d', '%d' ), array( '%d' ) );
				}
			}
			$processed++;
		}
		// record last processed time
		update_option( 'up_capi_last_processed', $now );
		// reschedule if work likely remains
		$remaining = $wpdb->get_var( "SELECT COUNT(1) FROM {$table}" );
		if ( $remaining && intval( $remaining ) > 0 ) {
			if ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
				wp_schedule_single_event( time() + 30, 'up_capi_process_queue' );
			}
		}
		return $processed;
	}

	// helper: return current queue length (DB-backed)
	public static function get_queue_length() {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$cnt = $wpdb->get_var( "SELECT COUNT(1) FROM {$table}" );
		return intval( $cnt );
	}

	// list queued items (admin) with simple pagination
	public static function list_queue( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", intval( $limit ), intval( $offset ) ), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['payload'] = json_decode( $r['payload'], true );
		}
		return $rows;
	}

	// retry a queued item (reset attempts/next_attempt)
	public static function retry_item( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		return (bool) $wpdb->update( $table, array( 'attempts' => 0, 'next_attempt' => 0 ), array( 'id' => intval( $id ) ), array( '%d', '%d' ), array( '%d' ) );
	}

	// delete queued item
	public static function delete_item( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		return (bool) $wpdb->delete( $table, array( 'id' => intval( $id ) ), array( '%d' ) );
	}

	public static function send_event( $platform, $event_name, $payload = array(), $blocking = true ) {
		$endpoint = UP_Settings::get( 'capi_endpoint', '' );
		$token = UP_Settings::get( 'capi_token', '' );
		if ( empty( $endpoint ) ) {
			return new WP_Error( 'no_endpoint', 'CAPI endpoint not configured' );
		}

		$body = array(
			'platform'  => $platform,
			'event'     => $event_name,
			'payload'   => $payload,
			'site'      => home_url(),
			'timestamp' => time(),
		);

		$args = array(
			'timeout'  => 15,
			'headers'  => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'     => wp_json_encode( $body ),
			'blocking' => (bool) $blocking,
		);

		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$attempts = 0;
		$max = $blocking ? 2 : 1;
		$last_response = null;
		while ( $attempts < $max ) {
			$attempts++;
			$response = wp_remote_post( $endpoint, $args );
			$last_response = $response;
			if ( is_wp_error( $response ) ) {
				self::log( 'error', sprintf( 'CAPI: attempt %d error: %s', $attempts, $response->get_error_message() ) );
				if ( $attempts < $max ) sleep(1);
				continue;
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				self::log( 'info', sprintf( 'CAPI success [%s] event=%s', $platform, $event_name ) );
				return $response;
			} else {
				self::log( 'error', sprintf( 'CAPI: attempt %d HTTP %d', $attempts, $code ) );
				if ( $attempts < $max ) sleep(1);
				continue;
			}
		}
		return $last_response;
	}

	protected static function log( $level, $message ) {
		$log = get_option( 'up_capi_log', array() );
		if ( ! is_array( $log ) ) $log = array();
		$log[] = array( 'time' => date( 'c' ), 'level' => $level, 'msg' => $message );
		if ( count( $log ) > 100 ) $log = array_slice( $log, -100 );
		update_option( 'up_capi_log', $log );
		error_log( '[UP_CAPI] ' . $message );
	}
}
