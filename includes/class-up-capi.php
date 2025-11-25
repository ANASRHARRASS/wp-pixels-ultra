<?php
/**
 * UP_CAPI
 *
 * Server-side event forwarding and queue processing for supported
 * advertising platforms (Meta, TikTok, Google Ads, Snapchat, Pinterest)
 * used by the Ultra Pixels plugin.
 *
 * @package WP_Pixels_Ultra
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CAPI helper methods and queue processing.
 */
class UP_CAPI {
	const QUEUE_OPTION = 'up_capi_queue';

	/**
	 * Enqueue payload into DB-backed queue for async processing.
	 *
	 * @param string $platform    Platform name (e.g., 'meta', 'tiktok').
	 * @param string $event_name  Event name.
	 * @param array  $payload     Event payload.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function enqueue_event( $platform, $event_name, $payload = array() ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'up_capi_queue';
		$now      = time();
		$inserted = $wpdb->insert(
			$table,
			array(
				'platform'     => substr( (string) $platform, 0, 50 ),
				'event_name'   => substr( (string) $event_name, 0, 191 ),
				'payload'      => wp_json_encode( $payload ),
				'attempts'     => 0,
				'next_attempt' => 0,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		if ( $inserted ) {
				// Prefer Action Scheduler if available for reliable background processing.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				// Schedule via Action Scheduler (group: up_capi).
				as_schedule_single_action( time() + 5, 'up_capi_process_queue', array(), 'up_capi' );
			} elseif ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
					wp_schedule_single_event( time() + 5, 'up_capi_process_queue' );
			}
			return true;
		}
		return false;
	}

	/**
	 * Process up to $limit events from the queue.
	 *
	 * @param int $limit Max number of events to process.
	 *
	 * @return int Number of processed items.
	 */
	public static function process_queue( $limit = 10 ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'up_capi_queue';
		$dl_table = $wpdb->prefix . 'up_capi_deadletter';
		$now      = time();

		// Select eligible rows.
		$rows = $wpdb->get_results( $wpdb->prepare( sprintf( 'SELECT * FROM %s WHERE (next_attempt = 0 OR next_attempt <= %%d) ORDER BY created_at ASC LIMIT %%d', esc_sql( $table ) ), $now, $limit ), ARRAY_A );
		if ( empty( $rows ) ) {
			return 0;
		}
		$processed = 0;
		// Group rows by platform for batch sending.
		$groups = array();
		foreach ( $rows as $row ) {
			$plat = ! empty( $row['platform'] ) ? $row['platform'] : 'generic';
			if ( ! isset( $groups[ $plat ] ) ) {
				$groups[ $plat ] = array();
			}
			$groups[ $plat ][] = $row;
		}

		foreach ( $groups as $plat => $group_rows ) {
			// Build events array and id map.
			$events = array();
			$id_map = array();
			foreach ( $group_rows as $r ) {
				$payload  = json_decode( $r['payload'], true );
				$events[] = is_array( $payload ) ? $payload : array();
				$id_map[] = $r['id'];
			}

			// Attempt batch send for this platform.
			$res     = self::send_batch( $plat, $events );
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
				// Delete all successfully sent rows.
				foreach ( $id_map as $del_id ) {
					$wpdb->delete( $table, array( 'id' => $del_id ), array( '%d' ) );
					++$processed;
				}
			} else {
				// Handle failures per-row: increment attempts and maybe dead-letter.
				foreach ( $group_rows as $row ) {
					$attempts = intval( $row['attempts'] ) + 1;
					if ( $attempts >= 5 ) {
						$wpdb->insert(
							$dl_table,
							array(
								'platform'        => $row['platform'],
								'event_name'      => $row['event_name'],
								'payload'         => $row['payload'],
								'failure_message' => isset( $message ) ? $message : 'failed',
								'failed_at'       => $now,
							),
							array( '%s', '%s', '%s', '%s', '%d' )
						);
						$wpdb->delete( $table, array( 'id' => $row['id'] ), array( '%d' ) );
					} else {
						$next = $now + ( 60 * $attempts );
						$wpdb->update(
							$table,
							array(
								'attempts'     => $attempts,
								'next_attempt' => $next,
							),
							array( 'id' => $row['id'] ),
							array( '%d', '%d' ),
							array( '%d' )
						);
					}
					++$processed;
				}
			}
		}
		// Record last processed time.
		update_option( 'up_capi_last_processed', $now );
		// Reschedule if work likely remains.
		$remaining = $wpdb->get_var( 'SELECT COUNT(1) FROM ' . esc_sql( $table ) );
		if ( $remaining && intval( $remaining ) > 0 ) {
			// Prefer Action Scheduler if available.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time() + 30, 'up_capi_process_queue', array(), 'up_capi' );
			} elseif ( ! wp_next_scheduled( 'up_capi_process_queue' ) ) {
					wp_schedule_single_event( time() + 30, 'up_capi_process_queue' );
			}
		}
		return $processed;
	}

	/**
	 * Get current queue length.
	 *
	 * @return int Queue length.
	 */
	public static function get_queue_length() {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$cnt   = $wpdb->get_var( 'SELECT COUNT(1) FROM ' . esc_sql( $table ) );
		return intval( $cnt );
	}

	/**
	 * List queued items (admin) with simple pagination.
	 *
	 * @param int $limit  Items per page.
	 * @param int $offset Offset.
	 *
	 * @return array List of queue rows.
	 */
	public static function list_queue( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		$rows  = $wpdb->get_results( $wpdb->prepare( sprintf( 'SELECT * FROM %s ORDER BY created_at DESC LIMIT %%d OFFSET %%d', esc_sql( $table ) ), intval( $limit ), intval( $offset ) ), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['payload'] = json_decode( $r['payload'], true );
		}
		return $rows;
	}

	/**
	 * Retry a queued item (reset attempts/next_attempt).
	 *
	 * @param int $id Queue row ID.
	 *
	 * @return bool True on success.
	 */
	public static function retry_item( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		return (bool) $wpdb->update(
			$table,
			array(
				'attempts'     => 0,
				'next_attempt' => 0,
			),
			array( 'id' => intval( $id ) ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete queued item.
	 *
	 * @param int $id Queue row ID.
	 *
	 * @return bool True on success.
	 */
	public static function delete_item( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_queue';
		return (bool) $wpdb->delete( $table, array( 'id' => intval( $id ) ), array( '%d' ) );
	}

	/**
	 * List dead-letter items.
	 *
	 * @param int $limit  Items per page.
	 * @param int $offset Offset.
	 *
	 * @return array Dead-letter rows.
	 */
	public static function list_deadletter( $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'up_capi_deadletter';
		$rows  = $wpdb->get_results( $wpdb->prepare( sprintf( 'SELECT * FROM %s ORDER BY failed_at DESC LIMIT %%d OFFSET %%d', esc_sql( $table ) ), intval( $limit ), intval( $offset ) ), ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['payload'] = json_decode( $r['payload'], true );
		}
		return $rows;
	}

	/**
	 * Retry a dead-letter item by reinserting to queue.
	 *
	 * @param int $id Dead-letter row ID.
	 *
	 * @return bool True on success.
	 */
	public static function retry_deadletter( $id ) {
		global $wpdb;
		$dl_table = $wpdb->prefix . 'up_capi_deadletter';
		$table    = $wpdb->prefix . 'up_capi_queue';
		$row      = $wpdb->get_row( $wpdb->prepare( sprintf( 'SELECT * FROM %s WHERE id = %%d', esc_sql( $dl_table ) ), intval( $id ) ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$now      = time();
		$inserted = $wpdb->insert(
			$table,
			array(
				'platform'     => substr( (string) $row['platform'], 0, 50 ),
				'event_name'   => substr( (string) $row['event_name'], 0, 191 ),
				'payload'      => $row['payload'],
				'attempts'     => 0,
				'next_attempt' => 0,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%d' )
		);
		if ( $inserted ) {
			$wpdb->delete( $dl_table, array( 'id' => intval( $id ) ), array( '%d' ) );
			return true;
		}
		return false;
	}

	/**
	 * Delete dead-letter item permanently.
	 *
	 * @param int $id Dead-letter row ID.
	 *
	 * @return bool True on success.
	 */
	public static function delete_deadletter( $id ) {
		global $wpdb;
		$dl_table = $wpdb->prefix . 'up_capi_deadletter';
		return (bool) $wpdb->delete( $dl_table, array( 'id' => intval( $id ) ), array( '%d' ) );
	}

	/**
	 * Return recent logs stored in option.
	 *
	 * @return array Recent logs.
	 */
	public static function get_logs() {
		$log = get_option( 'up_capi_log', array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( array_reverse( $log ), 0, 100 );
	}

	/**
	 * Send a single event to the specified platform or generic endpoint.
	 *
	 * @param string $platform   Platform identifier or 'generic'.
	 * @param string $event_name Event name.
	 * @param array  $payload    Event payload.
	 * @param bool   $blocking   Whether the request should be blocking.
	 *
	 * @return WP_Error|array|WP_HTTP_Response Response or WP_Error on failure.
	 */
	public static function send_event( $platform, $event_name, $payload = array(), $blocking = true ) {
		// Dispatch to platform-specific adapters when possible.
		$enable_meta       = 'yes' === UP_Settings::get( 'enable_meta', 'no' );
		$meta_id           = UP_Settings::get( 'meta_pixel_id', '' );
		$enable_tiktok     = 'yes' === UP_Settings::get( 'enable_tiktok', 'no' );
		$tiktok_id         = UP_Settings::get( 'tiktok_pixel_id', '' );
		$enable_google_ads = 'yes' === UP_Settings::get( 'enable_google_ads', 'no' );
		$google_ads_id     = UP_Settings::get( 'google_ads_id', '' );
		$enable_snapchat   = 'yes' === UP_Settings::get( 'enable_snapchat', 'no' );
		$snapchat_id       = UP_Settings::get( 'snapchat_pixel_id', '' );
		$enable_pinterest  = 'yes' === UP_Settings::get( 'enable_pinterest', 'no' );
		$pinterest_id      = UP_Settings::get( 'pinterest_tag_id', '' );
		$token             = UP_Settings::get( 'capi_token', '' );
		$snapchat_token    = UP_Settings::get( 'snapchat_api_token', '' );
		$pinterest_token   = UP_Settings::get( 'pinterest_access_token', '' );

		try {
			if ( 'meta' === $platform || ( $enable_meta && $meta_id && 'generic' === $platform ) ) {
				return self::send_to_meta( $meta_id, $token, array( array_merge( array( 'event_name' => $event_name ), $payload ) ), $blocking );
			}
			if ( 'tiktok' === $platform || ( $enable_tiktok && $tiktok_id && 'generic' === $platform ) ) {
				return self::send_to_tiktok( $tiktok_id, $token, array( array_merge( array( 'event_name' => $event_name ), $payload ) ), $blocking );
			}
			if ( 'google_ads' === $platform || ( $enable_google_ads && $google_ads_id && 'generic' === $platform ) ) {
				return self::send_to_google_ads( $google_ads_id, $token, array( array_merge( array( 'event_name' => $event_name ), $payload ) ), $blocking );
			}
			if ( 'snapchat' === $platform || ( $enable_snapchat && $snapchat_id && 'generic' === $platform ) ) {
				return self::send_to_snapchat( $snapchat_id, $snapchat_token, array( array_merge( array( 'event_name' => $event_name ), $payload ) ), $blocking );
			}
			if ( 'pinterest' === $platform || ( $enable_pinterest && $pinterest_id && 'generic' === $platform ) ) {
				return self::send_to_pinterest( $pinterest_id, $pinterest_token, array( array_merge( array( 'event_name' => $event_name ), $payload ) ), $blocking );
			}
			// Fallback: forward to configured generic CAPI endpoint.
			$endpoint = UP_Settings::get( 'capi_endpoint', '' );
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
			$response = wp_remote_post( $endpoint, $args );
			return $response;
		} catch ( Exception $e ) {
			return new WP_Error( 'send_exception', $e->getMessage() );
		}
	}

	/**
	 * Batch sender: platform => events array
	 */
	/**
	 * Batch sender: platform => events array.
	 *
	 * @param string $platform Platform identifier.
	 * @param array  $events   Array of events.
	 *
	 * @return WP_Error|array|WP_HTTP_Response
	 */
	protected static function send_batch( $platform, $events = array() ) {
		if ( empty( $events ) ) {
			return new WP_Error( 'no_events', 'No events to send' );
		}

		// Check if GTM forwarder is enabled.
		$use_gtm_forwarder = 'yes' === UP_Settings::get( 'use_gtm_forwarder', 'no' );

		if ( $use_gtm_forwarder ) {
			// Route all events through GTM Server Container.
			return self::send_to_gtm_server( $platform, $events, true );
		}

		// Original platform-specific routing.
		$token           = UP_Settings::get( 'capi_token', '' );
		$meta_id         = UP_Settings::get( 'meta_pixel_id', '' );
		$tiktok_id       = UP_Settings::get( 'tiktok_pixel_id', '' );
		$google_ads_id   = UP_Settings::get( 'google_ads_id', '' );
		$snapchat_id     = UP_Settings::get( 'snapchat_pixel_id', '' );
		$pinterest_id    = UP_Settings::get( 'pinterest_tag_id', '' );
		$snapchat_token  = UP_Settings::get( 'snapchat_api_token', '' );
		$pinterest_token = UP_Settings::get( 'pinterest_access_token', '' );

		if ( 'meta' === $platform && $meta_id ) {
			return self::send_to_meta( $meta_id, $token, $events, true );
		}
		if ( 'tiktok' === $platform && $tiktok_id ) {
			return self::send_to_tiktok( $tiktok_id, $token, $events, true );
		}
		if ( 'google_ads' === $platform && $google_ads_id ) {
			return self::send_to_google_ads( $google_ads_id, $token, $events, true );
		}
		if ( 'snapchat' === $platform && $snapchat_id ) {
			return self::send_to_snapchat( $snapchat_id, $snapchat_token, $events, true );
		}
		if ( 'pinterest' === $platform && $pinterest_id ) {
			return self::send_to_pinterest( $pinterest_id, $pinterest_token, $events, true );
		}
		// Fallback: send each event to generic endpoint individually.
		$last = null;
		foreach ( $events as $ev ) {
			$last = self::send_event( $platform, isset( $ev['event_name'] ) ? $ev['event_name'] : 'event', $ev, true );
		}
		return $last;
	}

	/**
	 * Send events to Meta Pixel via Graph API (batch)
	 */
	/**
	 * Send events to Meta Pixel via Graph API (batch).
	 *
	 * @param string $pixel_id     Meta Pixel ID.
	 * @param string $access_token Access token.
	 * @param array  $events       Events array.
	 * @param bool   $blocking     Whether request should be blocking.
	 *
	 * @return WP_Error|array|WP_HTTP_Response
	 */
	protected static function send_to_meta( $pixel_id, $access_token, $events = array(), $blocking = true ) {
		$url  = 'https://graph.facebook.com/v17.0/' . rawurlencode( $pixel_id ) . '/events?access_token=' . rawurlencode( $access_token );
		$data = array();
		foreach ( $events as $e ) {
			$item = array(
				'event_name'    => isset( $e['event_name'] ) ? $e['event_name'] : ( isset( $e['event'] ) ? $e['event'] : 'event' ),
				'event_time'    => isset( $e['event_time'] ) ? intval( $e['event_time'] ) : time(),
				'event_id'      => isset( $e['event_id'] ) ? $e['event_id'] : uniqid( 'ev_', true ),
				'user_data'     => array(),
				'custom_data'   => isset( $e['custom_data'] ) ? $e['custom_data'] : new stdClass(),
				'action_source' => isset( $e['action_source'] ) ? $e['action_source'] : 'website',
			);
			// Attempt to attach source URL if provided.
			if ( isset( $e['event_source_url'] ) ) {
				$item['event_source_url'] = esc_url_raw( $e['event_source_url'] );
			} elseif ( isset( $e['source_url'] ) ) {
				$item['event_source_url'] = esc_url_raw( $e['source_url'] );
			} elseif ( isset( $e['custom_data']['source_url'] ) ) {
				$item['event_source_url'] = esc_url_raw( $e['custom_data']['source_url'] );
			}
			if ( isset( $e['user_data'] ) && is_array( $e['user_data'] ) ) {
				foreach ( $e['user_data'] as $k => $v ) {
					if ( 'email_hash' === $k ) {
						$item['user_data']['em'] = $v;
					} elseif ( 'phone_hash' === $k ) {
						$item['user_data']['ph'] = $v;
					} else {
						$item['user_data'][ $k ] = $v;
					}
				}
			}
			$data[] = $item;
		}
		$body     = array( 'data' => $data );
		$args     = array(
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'timeout'  => 20,
			'blocking' => (bool) $blocking,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::log( 'error', 'Meta send error: ' . $response->get_error_message() );
		}
		return $response;
	}

	/**
	 * Send events to TikTok Pixel API (best-effort minimal implementation).
	 *
	 * @param string $pixel_id      TikTok pixel identifier.
	 * @param string $access_token  Access token (optional).
	 * @param array  $events        Array of event payloads.
	 * @param bool   $blocking      Whether request should be blocking.
	 *
	 * @return array|WP_Error|WP_HTTP_Response
	 */
	protected static function send_to_tiktok( $pixel_id, $access_token, $events = array(), $blocking = true ) {
		// Use TikTok Business API endpoint; structure may need adjustment by integrator.
		$url  = 'https://business-api.tiktok.com/open_api/v1.2/pixel/track/';
		$body = array(
			'pixel_code' => $pixel_id,
			'event_list' => array(),
		);
		foreach ( $events as $e ) {
			$item = array(
				'event'      => isset( $e['event_name'] ) ? $e['event_name'] : ( isset( $e['event'] ) ? $e['event'] : 'event' ),
				'event_time' => isset( $e['event_time'] ) ? intval( $e['event_time'] ) : time(),
				'event_id'   => isset( $e['event_id'] ) ? $e['event_id'] : uniqid( 'ev_', true ),
				'properties' => is_array( $e['custom_data'] ) ? $e['custom_data'] : ( is_object( $e['custom_data'] ) ? (array) $e['custom_data'] : array() ),
				'user'       => array(),
			);
			// Attach source URL into properties if available.
			if ( isset( $e['source_url'] ) && empty( $item['properties']['url'] ) ) {
				$item['properties']['url'] = esc_url_raw( $e['source_url'] );
			}
			if ( isset( $e['user_data'] ) && is_array( $e['user_data'] ) ) {
				foreach ( $e['user_data'] as $k => $v ) {
					if ( 'email_hash' === $k ) {
						$item['user']['em'] = $v;
					} elseif ( 'phone_hash' === $k ) {
						$item['user']['ph'] = $v;
					} else {
						$item['user'][ $k ] = $v;
					}
				}
			}
			$body['event_list'][] = $item;
		}
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $access_token ) ) {
			// TikTok accepts access_token either in query or header depending on setup; prefer header for privacy.
			$headers['Access-Token'] = $access_token;
		}
		$args     = array(
			'headers'  => $headers,
			'body'     => wp_json_encode( $body ),
			'timeout'  => 20,
			'blocking' => (bool) $blocking,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::log( 'error', 'TikTok send error: ' . $response->get_error_message() );
		}
		// Log non-2xx responses for visibility.
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			$code = intval( $response['response']['code'] );
			if ( $code < 200 || $code >= 300 ) {
				self::log( 'error', sprintf( 'TikTok HTTP %d: %s', $code, wp_json_encode( array( 'body' => $body ) ) ) );
			}
		}
		return $response;
	}

	/**
	 * Send events to Google Ads Conversion API (Enhanced Conversions).
	 *
	 * This implementation prepares a minimal payload intended for
	 * consumption by a GTM server container or middleware. It does not
	 * implement OAuth2 flows for direct Google Ads uploads.
	 *
	 * @param string $conversion_id Conversion identifier (Google Ads conversion ID).
	 * @param string $access_token  Optional access token passed to middleware.
	 * @param array  $events        Array of event payloads.
	 * @param bool   $blocking      Whether request should be blocking.
	 *
	 * @return array|WP_Error|WP_HTTP_Response
	 */
	protected static function send_to_google_ads( $conversion_id, $access_token, $events = array(), $blocking = true ) {
		// Minimal server-side Enhanced Conversions style forwarding.
		// Real offline / click conversion uploads require OAuth2 (developer token + customer ID + conversion action)
		// which is intentionally not implemented here to avoid complexity. Instead we expose a generic payload
		// that can be consumed by a GTM server container or custom middleware.
		$label            = UP_Settings::get( 'google_ads_label', '' );
		$server_container = UP_Settings::get( 'gtm_server_url', '' );
		if ( empty( $server_container ) ) {
			self::log( 'warn', 'Google Ads send skipped: gtm_server_url not configured' );
			return new WP_Error( 'google_ads_not_configured', 'GTM server URL required for Google Ads forwarding.' );
		}
		$endpoint = trailingslashit( $server_container ) . 'up-google-ads'; // custom path expected by integrator.
		$payloads = array();
		foreach ( $events as $e ) {
			$ec = array(
				'conversion_id'    => $conversion_id,
				'conversion_label' => $label,
				'event_time'       => isset( $e['event_time'] ) ? intval( $e['event_time'] ) : time(),
				'event_id'         => isset( $e['event_id'] ) ? $e['event_id'] : uniqid( 'gad_', true ),
				'event_name'       => isset( $e['event_name'] ) ? $e['event_name'] : ( isset( $e['event'] ) ? $e['event'] : 'conversion' ),
				'value'            => isset( $e['custom_data']['value'] ) ? floatval( $e['custom_data']['value'] ) : null,
				'currency'         => isset( $e['custom_data']['currency'] ) ? $e['custom_data']['currency'] : null,
				'source_url'       => isset( $e['source_url'] ) ? esc_url_raw( $e['source_url'] ) : null,
				'user_data'        => array(),
			);
			if ( isset( $e['user_data'] ) && is_array( $e['user_data'] ) ) {
				foreach ( $e['user_data'] as $k => $v ) {
					if ( in_array( $k, array( 'email_hash', 'phone_hash' ), true ) ) {
						$ec['user_data'][ $k ] = $v; // already hashed.
					}
				}
			}
			$payloads[] = $ec;
		}
		$body = array( 'google_ads_conversions' => $payloads );
		$args = array(
			'timeout'  => 15,
			'headers'  => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'     => wp_json_encode( $body ),
			'blocking' => (bool) $blocking,
		);
		if ( ! empty( $access_token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $access_token; // optional generic token if middleware wants it.
		}
		$response = wp_remote_post( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			self::log( 'error', 'Google Ads forward error: ' . $response->get_error_message() );
		} else {
			$code = isset( $response['response']['code'] ) ? intval( $response['response']['code'] ) : 0;
			if ( $code < 200 || $code >= 300 ) {
				self::log( 'error', 'Google Ads forward HTTP ' . $code );
			}
		}
		return $response;
	}

	/**
	 * Send events to Snapchat Conversions API.
	 *
	 * @param string $pixel_id      Snapchat pixel ID.
	 * @param string $access_token  Snapchat access token (optional).
	 * @param array  $events        Array of event payloads.
	 * @param bool   $blocking      Whether request should be blocking.
	 *
	 * @return array|WP_Error|WP_HTTP_Response
	 */
	protected static function send_to_snapchat( $pixel_id, $access_token, $events = array(), $blocking = true ) {
		$url  = 'https://tr.snapchat.com/v2/conversion';
		$body = array( 'batch' => array() ); // batch key supports multiple events.
		foreach ( $events as $e ) {
			$item = array(
				'pixel_id'      => $pixel_id,
				'event_name'    => isset( $e['event_name'] ) ? strtoupper( $e['event_name'] ) : 'CUSTOM_EVENT_1',
				'event_time'    => isset( $e['event_time'] ) ? intval( $e['event_time'] ) : time(),
				'event_id'      => isset( $e['event_id'] ) ? $e['event_id'] : uniqid( 'snap_', true ),
				'action_source' => 'website',
			);
			if ( isset( $e['source_url'] ) ) {
				$item['event_source_url'] = esc_url_raw( $e['source_url'] );
			}
			// User data (hashed).
			if ( isset( $e['user_data'] ) && is_array( $e['user_data'] ) ) {
				$item['user'] = array();
				if ( isset( $e['user_data']['email_hash'] ) ) {
					$item['user']['em'] = $e['user_data']['email_hash'];
				}
				if ( isset( $e['user_data']['phone_hash'] ) ) {
					$item['user']['ph'] = $e['user_data']['phone_hash'];
				}
			}
			// Custom data.
			if ( isset( $e['custom_data'] ) && is_array( $e['custom_data'] ) ) {
				if ( isset( $e['custom_data']['value'] ) ) {
					$item['price'] = floatval( $e['custom_data']['value'] );
				}
				if ( isset( $e['custom_data']['currency'] ) ) {
					$item['currency'] = sanitize_text_field( $e['custom_data']['currency'] );
				}
				if ( isset( $e['custom_data']['item_ids'] ) && is_array( $e['custom_data']['item_ids'] ) ) {
					$item['item_ids'] = array_map( 'sanitize_text_field', $e['custom_data']['item_ids'] );
				}
			}
			$body['batch'][] = $item;
		}
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $access_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $access_token;
		}
		$args     = array(
			'headers'  => $headers,
			'body'     => wp_json_encode( $body ),
			'timeout'  => 20,
			'blocking' => (bool) $blocking,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::log( 'error', 'Snapchat send error: ' . $response->get_error_message() );
		}
		return $response;
	}

	/**
	 * Send events to Pinterest Conversions API.
	 *
	 * @param string $tag_id        Pinterest tag ID.
	 * @param string $access_token  Pinterest access token (optional).
	 * @param array  $events        Array of event payloads.
	 * @param bool   $blocking      Whether request should be blocking.
	 *
	 * @return array|WP_Error|WP_HTTP_Response
	 */
	protected static function send_to_pinterest( $tag_id, $access_token, $events = array(), $blocking = true ) {
		// Pinterest Conversions API (events endpoint).
		$url  = 'https://api.pinterest.com/v5/events';
		$body = array( 'data' => array() );
		foreach ( $events as $e ) {
			$item = array(
				'event_name'    => isset( $e['event_name'] ) ? $e['event_name'] : 'custom',
				'event_time'    => isset( $e['event_time'] ) ? intval( $e['event_time'] ) : time(),
				'event_id'      => isset( $e['event_id'] ) ? $e['event_id'] : uniqid( 'pin_', true ),
				'action_source' => 'web',
			);
			if ( isset( $e['source_url'] ) ) {
				$item['event_source_url'] = esc_url_raw( $e['source_url'] );
			}
			if ( isset( $e['user_data'] ) && is_array( $e['user_data'] ) ) {
				$item['user_data'] = array();
				if ( isset( $e['user_data']['email_hash'] ) ) {
					$item['user_data']['em'] = array( $e['user_data']['email_hash'] );
				}
			}
			if ( isset( $e['custom_data'] ) && is_array( $e['custom_data'] ) ) {
				$item['custom_data'] = array();
				if ( isset( $e['custom_data']['value'] ) ) {
					$item['custom_data']['value'] = floatval( $e['custom_data']['value'] );
				}
				if ( isset( $e['custom_data']['currency'] ) ) {
					$item['custom_data']['currency'] = sanitize_text_field( $e['custom_data']['currency'] );
				}
			}
			$body['data'][] = $item;
		}
		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $access_token ) ) {
			$headers['Authorization'] = 'Bearer ' . $access_token;
		}
		$args     = array(
			'headers'  => $headers,
			'body'     => wp_json_encode( $body ),
			'timeout'  => 20,
			'blocking' => (bool) $blocking,
		);
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::log( 'error', 'Pinterest send error: ' . $response->get_error_message() );
		}
		return $response;
	}

	/**
	 * Send events to a GTM Server Container for unified routing.
	 *
	 * Prepares a small batch payload including platform identifiers and
	 * events and forwards it to the configured GTM Server URL.
	 *
	 * @param string $platform Platform identifier (meta, tiktok, google_ads, etc.).
	 * @param array  $events   Array of event payloads.
	 * @param bool   $blocking Whether to wait for response.
	 *
	 * @return array|WP_Error|WP_HTTP_Response Response from GTM server or WP_Error on failure.
	 */
	protected static function send_to_gtm_server( $platform, $events = array(), $blocking = true ) {
		$gtm_server_url = UP_Settings::get( 'gtm_server_url', '' );

		if ( empty( $gtm_server_url ) ) {
			self::log( 'error', 'GTM forwarder enabled but gtm_server_url not configured' );
			return new WP_Error( 'gtm_not_configured', 'GTM Server Container URL is required for GTM forwarding.' );
		}

		// Build the endpoint URL - use a standard path for event ingestion.
		$endpoint = trailingslashit( $gtm_server_url ) . 'event';

		// Get platform-specific IDs for forwarding to GTM.
		$pixel_ids = array(
			'meta_pixel_id'     => UP_Settings::get( 'meta_pixel_id', '' ),
			'tiktok_pixel_id'   => UP_Settings::get( 'tiktok_pixel_id', '' ),
			'google_ads_id'     => UP_Settings::get( 'google_ads_id', '' ),
			'google_ads_label'  => UP_Settings::get( 'google_ads_label', '' ),
			'snapchat_pixel_id' => UP_Settings::get( 'snapchat_pixel_id', '' ),
			'pinterest_tag_id'  => UP_Settings::get( 'pinterest_tag_id', '' ),
		);

		// Prepare batch payload for GTM server.
		$payload = array(
			'platform'  => $platform,
			'events'    => $events,
			'pixel_ids' => $pixel_ids,
			'source'    => 'wordpress',
			'site_url'  => home_url(),
			'timestamp' => time(),
		);

		// SECURITY: Do NOT include sensitive platform tokens in payload to external GTM server.
		// If GTM server requires authentication, use a non-sensitive shared secret or API key in headers.
		// $capi_token = UP_Settings::get( 'capi_token', '' );
		// $snapchat_token = UP_Settings::get( 'snapchat_api_token', '' );
		// $pinterest_token = UP_Settings::get( 'pinterest_access_token', '' );
		// Tokens intentionally not sent to GTM server for security reasons.

		$args = array(
			'timeout'  => 20,
			'headers'  => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'WordPress-UltraPixels/' . ( defined( 'UP_VERSION' ) ? UP_VERSION : '1.0' ),
			),
			'body'     => wp_json_encode( $payload ),
			'blocking' => (bool) $blocking,
		);

		$response = wp_remote_post( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			self::log( 'error', sprintf( 'GTM forwarder error (%s): %s', $platform, $response->get_error_message() ) );
		} elseif ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			$code = intval( $response['response']['code'] );
			if ( $code >= 200 && $code < 300 ) {
				self::log( 'info', sprintf( 'GTM forwarded %d events for platform %s (HTTP %d)', count( $events ), $platform, $code ) );
			} else {
				self::log( 'error', sprintf( 'GTM forwarder HTTP %d for platform %s', $code, $platform ) );
			}
		}

		return $response;
	}

	/**
	 * Append a message to the plugin CAPI log and PHP error log.
	 *
	 * @param string $level   Log level (e.g. 'info', 'warn', 'error').
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	protected static function log( $level, $message ) {
		$log = get_option( 'up_capi_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'time'  => gmdate( 'c' ),
			'level' => $level,
			'msg'   => $message,
		);
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, -100 );
		}
		update_option( 'up_capi_log', $log );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[UP_CAPI] ' . $message );
		}
	}
}
