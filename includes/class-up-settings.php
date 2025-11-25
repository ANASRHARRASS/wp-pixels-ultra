<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class UP_Settings {
	// Replace previous simple register with structured settings, sanitization and defaults
	public static function init() {
		register_setting(
			'up_settings_group',
			'up_settings',
			array( __CLASS__, 'sanitize_callback' )
		);
	}

	public static function defaults() {
		$default_mappings = array(
			'purchase'          => array(
				'meta'       => array(
					'event_name'        => 'Purchase',
					'include_user_data' => true,
				),
				'tiktok'     => array(
					'event_name'        => 'PlaceAnOrder',
					'include_user_data' => true,
				),
				'google_ads' => array(
					'event_name'        => 'conversion',
					'include_user_data' => true,
				),
				'snapchat'   => array(
					'event_name'        => 'PURCHASE',
					'include_user_data' => true,
				),
				'pinterest'  => array(
					'event_name'        => 'checkout',
					'include_user_data' => true,
				),
			),
			'add_to_cart'       => array(
				'meta'       => array(
					'event_name'        => 'AddToCart',
					'include_user_data' => false,
				),
				'tiktok'     => array(
					'event_name'        => 'AddToCart',
					'include_user_data' => false,
				),
				'google_ads' => array(
					'event_name'        => 'add_to_cart',
					'include_user_data' => false,
				),
				'snapchat'   => array(
					'event_name'        => 'ADD_CART',
					'include_user_data' => false,
				),
				'pinterest'  => array(
					'event_name'        => 'add_to_cart',
					'include_user_data' => false,
				),
			),
			'view_item'         => array(
				'meta'       => array(
					'event_name'        => 'ViewContent',
					'include_user_data' => false,
				),
				'tiktok'     => array(
					'event_name'        => 'ViewContent',
					'include_user_data' => false,
				),
				'google_ads' => array(
					'event_name'        => 'view_item',
					'include_user_data' => false,
				),
				'snapchat'   => array(
					'event_name'        => 'VIEW_CONTENT',
					'include_user_data' => false,
				),
				'pinterest'  => array(
					'event_name'        => 'page_visit',
					'include_user_data' => false,
				),
			),
			'view_item_list'    => array(
				'meta'   => array(
					'event_name'        => 'ViewCategory',
					'include_user_data' => false,
				),
				'tiktok' => array(
					'event_name'        => 'BrowseCategory',
					'include_user_data' => false,
				),
			),
			'begin_checkout'    => array(
				'meta'       => array(
					'event_name'        => 'InitiateCheckout',
					'include_user_data' => false,
				),
				'tiktok'     => array(
					'event_name'        => 'InitiateCheckout',
					'include_user_data' => false,
				),
				'google_ads' => array(
					'event_name'        => 'begin_checkout',
					'include_user_data' => false,
				),
				'snapchat'   => array(
					'event_name'        => 'START_CHECKOUT',
					'include_user_data' => false,
				),
			),
			'whatsapp_initiate' => array(
				'meta'       => array(
					'event_name'        => 'Contact',
					'include_user_data' => true,
				),
				'tiktok'     => array(
					'event_name'        => 'Contact',
					'include_user_data' => true,
				),
				'google_ads' => array(
					'event_name'        => 'conversion',
					'include_user_data' => true,
				),
			),
			'whatsapp_click'    => array(
				'meta'       => array(
					'event_name'        => 'Lead',
					'include_user_data' => true,
				),
				'tiktok'     => array(
					'event_name'        => 'Lead',
					'include_user_data' => true,
				),
				'google_ads' => array(
					'event_name'        => 'conversion',
					'include_user_data' => true,
				),
				'snapchat'   => array(
					'event_name'        => 'SIGN_UP',
					'include_user_data' => true,
				),
			),
			'form_submit'       => array(
				'meta'       => array(
					'event_name'        => 'Lead',
					'include_user_data' => true,
				),
				'tiktok'     => array(
					'event_name'        => 'SubmitForm',
					'include_user_data' => true,
				),
				'google_ads' => array(
					'event_name'        => 'conversion',
					'include_user_data' => true,
				),
				'snapchat'   => array(
					'event_name'        => 'SIGN_UP',
					'include_user_data' => true,
				),
			),
		);
		return array(
			'gtm_container_id'         => '',
			'meta_pixel_id'            => '',
			'tiktok_pixel_id'          => '',
			'google_ads_id'            => '',
			'google_ads_label'         => '',
			'snapchat_pixel_id'        => '',
			'snapchat_api_token'       => '',
			'pinterest_tag_id'         => '',
			'pinterest_access_token'   => '',
			'gtm_manage_pixels'        => 'no',
			'enable_gtm'               => 'no',
			'enable_meta'              => 'no',
			'enable_tiktok'            => 'no',
			'enable_google_ads'        => 'no',
			'enable_snapchat'          => 'no',
			'enable_pinterest'         => 'no',
			'gtm_server_url'           => '',
			'use_gtm_forwarder'        => 'no',
			'server_secret'            => '',
			'capi_endpoint'            => '',
			'capi_token'               => '',
			'event_mapping'            => wp_json_encode( $default_mappings ),
			// rate limit controls (requests per minute)
			'rate_limit_ip_per_min'    => 60,
			'rate_limit_token_per_min' => 600,
			'retry_after_seconds'      => 60,
		);
	}

	public static function sanitize_callback( $input ) {
		$defaults = self::defaults();
		$out      = $defaults;
		if ( ! is_array( $input ) ) {
			return $out;
		}
		// whitelist and sanitize known keys
		foreach ( $defaults as $key => $default ) {
			if ( isset( $input[ $key ] ) ) {
				$val = $input[ $key ];
				switch ( $key ) {
                    case 'tracking_mode':
                        $out[ $key ] = ( $val === 'pure_s2s' ) ? 'pure_s2s' : 'hybrid';
                        break;
					case 'enable_gtm':
					case 'enable_meta':
					case 'enable_tiktok':
					case 'enable_google_ads':
					case 'enable_snapchat':
					case 'enable_pinterest':
					case 'gtm_manage_pixels':
					case 'use_gtm_forwarder':
						$out[ $key ] = ( $val === 'yes' ) ? 'yes' : 'no';
						break;
					case 'rate_limit_ip_per_min':
					case 'rate_limit_token_per_min':
					case 'retry_after_seconds':
						$out[ $key ] = max( 1, intval( $val ) );
						break;
					case 'capi_token':
						// token-like values: sanitize_text_field then trim
						$out[ $key ] = sanitize_text_field( trim( $val ) );
						break;
					case 'google_ads_label':
					case 'snapchat_api_token':
					case 'pinterest_access_token':
						$out[ $key ] = sanitize_text_field( trim( $val ) );
						break;
					case 'capi_endpoint':
					case 'gtm_server_url':
						$out[ $key ] = esc_url_raw( trim( $val ) );
						break;
					default:
						$out[ $key ] = sanitize_text_field( $val );
						break;
				}
			}
		}

		$raw_map = isset( $input['event_mapping'] ) ? trim( $input['event_mapping'] ) : '';
		if ( $raw_map === '' ) {
			$out['event_mapping'] = '{}';
		} else {
			$decoded = json_decode( $raw_map, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$out['event_mapping'] = wp_json_encode( $decoded );
			} else {
				set_transient( 'up_event_map_error', 'Invalid JSON for event mapping. Changes not saved.', 30 );
			}
		}

		return $out;
	}

	public static function get( $key, $default = '' ) {
		$opts     = get_option( 'up_settings', array() );
		$defaults = self::defaults();
		$opts     = wp_parse_args( $opts, $defaults );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	public static function update( $key, $value ) {
		$opts = get_option( 'up_settings', array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$opts[ $key ] = $value;
		update_option( 'up_settings', $opts );
	}

	// Backwards compatible page renderer. Admin class will call this.
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = get_option( 'up_settings', array() );
		$opts = wp_parse_args( $opts, self::defaults() );

		// Use the WP Settings API form action target and nonce is handled by settings_fields()
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Ultra Pixels Settings', 'ultra-pixels-ultra' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'up_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gtm_manage_pixels">Let GTM manage all client pixels</label></th>
						<td>
							<select name="up_settings[gtm_manage_pixels]" id="gtm_manage_pixels">
								<option value="no" <?php selected( $opts['gtm_manage_pixels'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['gtm_manage_pixels'], 'yes' ); ?>>Yes</option>
							</select>
							<p class="description">When set to Yes, the plugin will NOT inject Meta/TikTok/Snapchat/Pinterest base code; use GTM tags instead. Server-side queue still runs.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gtm_container_id">GTM Container ID</label></th>
						<td><input name="up_settings[gtm_container_id]" id="gtm_container_id" type="text" value="<?php echo esc_attr( $opts['gtm_container_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_gtm">Enable GTM</label></th>
						<td>
							<select name="up_settings[enable_gtm]" id="enable_gtm">
								<option value="no" <?php selected( $opts['enable_gtm'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_gtm'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
							<tr>
								<th scope="row"><label for="tracking_mode">Tracking Mode</label></th>
								<td>
									<select name="up_settings[tracking_mode]" id="tracking_mode">
										<option value="hybrid" <?php selected( $opts['tracking_mode'], 'hybrid' ); ?>>Hybrid (recommended)</option>
										<option value="pure_s2s" <?php selected( $opts['tracking_mode'], 'pure_s2s' ); ?>>Pure Server-to-Server (no client GTM)</option>
									</select>
									<p class="description">Hybrid: client Data Layer + server forwarding. Pure S2S: only server-side events (no client pixels).</p>
								</td>
							</tr>
					<tr>
						<th scope="row"><label for="meta_pixel_id">Meta Pixel ID</label></th>
						<td><input name="up_settings[meta_pixel_id]" id="meta_pixel_id" type="text" value="<?php echo esc_attr( $opts['meta_pixel_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_meta">Enable Meta</label></th>
						<td>
							<select name="up_settings[enable_meta]" id="enable_meta">
								<option value="no" <?php selected( $opts['enable_meta'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_meta'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="tiktok_pixel_id">TikTok Pixel ID</label></th>
						<td><input name="up_settings[tiktok_pixel_id]" id="tiktok_pixel_id" type="text" value="<?php echo esc_attr( $opts['tiktok_pixel_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_tiktok">Enable TikTok</label></th>
						<td>
							<select name="up_settings[enable_tiktok]" id="enable_tiktok">
								<option value="no" <?php selected( $opts['enable_tiktok'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_tiktok'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="google_ads_id">Google Ads Conversion ID</label></th>
						<td><input name="up_settings[google_ads_id]" id="google_ads_id" type="text" value="<?php echo esc_attr( $opts['google_ads_id'] ); ?>" class="regular-text" />
						<p class="description">Enter your Google Ads Conversion ID (AW-XXXXXXXXX)</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="google_ads_label">Google Ads Conversion Label</label></th>
						<td><input name="up_settings[google_ads_label]" id="google_ads_label" type="text" value="<?php echo esc_attr( $opts['google_ads_label'] ); ?>" class="regular-text" />
						<p class="description">Optional: Conversion label for enhanced conversions / offline uploads.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_google_ads">Enable Google Ads</label></th>
						<td>
							<select name="up_settings[enable_google_ads]" id="enable_google_ads">
								<option value="no" <?php selected( $opts['enable_google_ads'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_google_ads'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="snapchat_pixel_id">Snapchat Pixel ID</label></th>
						<td><input name="up_settings[snapchat_pixel_id]" id="snapchat_pixel_id" type="text" value="<?php echo esc_attr( $opts['snapchat_pixel_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="snapchat_api_token">Snapchat API Token</label></th>
						<td><input name="up_settings[snapchat_api_token]" id="snapchat_api_token" type="text" value="<?php echo esc_attr( $opts['snapchat_api_token'] ); ?>" class="regular-text" />
						<p class="description">Bearer token for Snapchat Conversions API (do not expose publicly).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_snapchat">Enable Snapchat</label></th>
						<td>
							<select name="up_settings[enable_snapchat]" id="enable_snapchat">
								<option value="no" <?php selected( $opts['enable_snapchat'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_snapchat'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pinterest_tag_id">Pinterest Tag ID</label></th>
						<td><input name="up_settings[pinterest_tag_id]" id="pinterest_tag_id" type="text" value="<?php echo esc_attr( $opts['pinterest_tag_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pinterest_access_token">Pinterest Access Token</label></th>
						<td><input name="up_settings[pinterest_access_token]" id="pinterest_access_token" type="text" value="<?php echo esc_attr( $opts['pinterest_access_token'] ); ?>" class="regular-text" />
						<p class="description">Access token for Pinterest Conversions API.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="enable_pinterest">Enable Pinterest</label></th>
						<td>
							<select name="up_settings[enable_pinterest]" id="enable_pinterest">
								<option value="no" <?php selected( $opts['enable_pinterest'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['enable_pinterest'], 'yes' ); ?>>Yes</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gtm_server_url">GTM Server Container URL</label></th>
						<td><input name="up_settings[gtm_server_url]" id="gtm_server_url" type="text" value="<?php echo esc_attr( $opts['gtm_server_url'] ); ?>" class="regular-text" />
						<p class="description">Optional: Enter your server-side GTM container URL for enhanced measurement</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="use_gtm_forwarder">Use GTM Server for Event Forwarding</label></th>
						<td>
							<select name="up_settings[use_gtm_forwarder]" id="use_gtm_forwarder">
								<option value="no" <?php selected( $opts['use_gtm_forwarder'], 'no' ); ?>>No</option>
								<option value="yes" <?php selected( $opts['use_gtm_forwarder'], 'yes' ); ?>>Yes</option>
							</select>
							<p class="description">When enabled, all server-side events are forwarded to GTM Server Container instead of directly calling platform APIs. Requires GTM Server Container URL to be configured.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="capi_endpoint">CAPI Endpoint</label></th>
						<td><input name="up_settings[capi_endpoint]" id="capi_endpoint" type="text" value="<?php echo esc_attr( $opts['capi_endpoint'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="capi_token">CAPI Token</label></th>
						<td><input name="up_settings[capi_token]" id="capi_token" type="text" value="<?php echo esc_attr( $opts['capi_token'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="server_secret">Server Secret</label></th>
						<td><input name="up_settings[server_secret]" id="server_secret" type="password" value="<?php echo esc_attr( $opts['server_secret'] ); ?>" class="regular-text" />
						<p class="description">Optional secret for server-to-server ingest (if used, prefer defining <code>UP_SERVER_SECRET</code> in <code>wp-config.php</code>).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rate_limit_ip_per_min">Rate limit (IP, per minute)</label></th>
						<td><input name="up_settings[rate_limit_ip_per_min]" id="rate_limit_ip_per_min" type="number" min="1" value="<?php echo esc_attr( $opts['rate_limit_ip_per_min'] ); ?>" class="small-text" />
						<p class="description">Maximum ingest requests allowed per IP per minute (transient-based).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rate_limit_token_per_min">Rate limit (Token, per minute)</label></th>
						<td><input name="up_settings[rate_limit_token_per_min]" id="rate_limit_token_per_min" type="number" min="1" value="<?php echo esc_attr( $opts['rate_limit_token_per_min'] ); ?>" class="small-text" />
						<p class="description">Maximum ingest requests allowed per token/secret per minute (transient-based). Higher for service tokens.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="retry_after_seconds">Retry-After (seconds)</label></th>
						<td><input name="up_settings[retry_after_seconds]" id="retry_after_seconds" type="number" min="1" value="<?php echo esc_attr( $opts['retry_after_seconds'] ); ?>" class="small-text" />
						<p class="description">Seconds to return in the Retry-After header when rate-limited.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>

				<h2>CAPI Queue</h2>
				<div id="up-capi-queue">
					<p>Queue length: <strong id="up-queue-length"><?php echo intval( class_exists( 'UP_CAPI' ) ? UP_CAPI::get_queue_length() : 0 ); ?></strong></p>
					<p>Last processed: <strong id="up-last-processed">
					<?php
					$lp = get_option( 'up_capi_last_processed', 0 );
					echo $lp ? date( 'c', $lp ) : 'never';
					?>
					</strong></p>
					<p>
						<button id="up-process-queue" class="button button-primary">Process now</button>
						<span id="up-process-result" style="margin-left:12px;"></span>
						<button id="up-send-test-event" class="button" style="margin-left:12px;">Send test event</button>
						<span id="up-send-test-result" style="margin-left:12px;"></span>
					</p>

					<h3>Queue Items</h3>
					<p>
						<label for="up-queue-limit">Items per page: </label>
						<select id="up-queue-limit">
							<option value="10">10</option>
							<option value="20" selected>20</option>
							<option value="50">50</option>
						</select>
						<button id="up-queue-refresh" class="button">Refresh</button>
					</p>
					<div id="up-queue-items"></div>
				</div>

				<h2>Event Mapping</h2>
				<p>Configure how your events are named and sent to Meta and TikTok CAPI. The mapping uses a JSON format: each event key (e.g., "purchase") maps to platform configurations with event names and user data inclusion.</p>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="event_mapping">Event Mapping (JSON)</label></th>
						<td>
							<textarea name="up_settings[event_mapping]" id="event_mapping" rows="15" class="large-text code" style="width:100%;"><?php echo esc_textarea( $opts['event_mapping'] ); ?></textarea>
							<p class="description">
								<strong>Common events:</strong> purchase, add_to_cart, view_item, view_item_list, begin_checkout, whatsapp_initiate, whatsapp_click<br/>
								<strong>Platforms:</strong> meta, tiktok<br/>
								<strong>Fields:</strong> event_name (string), include_user_data (boolean)<br/>
								Example: <code>{"purchase": {"meta": {"event_name": "Purchase", "include_user_data": true}}}</code>
							</p>
						</td>
					</tr>
				</table>

				<div id="event-mapping-preview" style="background:#f5f5f5;padding:12px;border-radius:4px;margin:16px 0;">
					<h3>Current Mappings Preview</h3>
					<div id="mapping-preview-content" style="font-size:12px;white-space:pre-wrap;font-family:monospace;max-height:300px;overflow:auto;">
						Loading...
					</div>
				</div>

			</form>
			<h2>Landing Page Integration</h2>
			<p>Track WhatsApp interactions and custom events on landing pages by adding data attributes to your HTML elements:</p>
			<h3>WhatsApp Button Example</h3>
			<pre style="background:#f5f5f5;padding:12px;border-radius:4px;overflow:auto;"><code>&lt;!-- Simple WhatsApp link --&gt;
&lt;a href="https://wa.me/15551234567?text=Hello%20I%20need%20help" class="button"&gt;Contact us on WhatsApp&lt;/a&gt;

&lt;!-- With custom event attributes --&gt;
&lt;a href="https://wa.me/15551234567" data-up-event="whatsapp_initiate" data-up-payload='{"button_location":"hero"}'&gt;WhatsApp Help&lt;/a&gt;

&lt;!-- Generic custom event --&gt;
&lt;button data-up-event="video_play" data-up-payload='{"video_id":"promo_1", "duration":60}'&gt;Play Video&lt;/button&gt;</code></pre>

			<h2>Notes</h2>
			<p>Use GTM for advanced deployments. For server-side CAPI forwarding add a secure endpoint and token. This plugin provides a boilerplate — extend with your server-side forwarding logic and event mapping.</p>
		</div>

		<script>
			(function(){
				function updateMappingPreview() {
					var txt = document.getElementById('event_mapping');
					if (!txt) return;
					try {
						var mapping = JSON.parse(txt.value);
						var html = '<strong>Events:</strong> ' + Object.keys(mapping).join(', ') + '<br/>';
						Object.keys(mapping).forEach(function(evt){
							var cfg = mapping[evt];
							var platforms = Object.keys(cfg).join(', ');
							html += '  ' + evt + ' → ' + platforms + '<br/>';
						});
						document.getElementById('mapping-preview-content').innerHTML = html;
					} catch(e) {
						document.getElementById('mapping-preview-content').innerHTML = '<span style="color:red;">Invalid JSON: ' + e.message + '</span>';
					}
				}
				var ta = document.getElementById('event_mapping');
				if (ta) {
					ta.addEventListener('input', updateMappingPreview);
					updateMappingPreview();
				}
			})();
			(function(){
				var sendBtn = document.getElementById('up-send-test-event');
				if (!sendBtn) return;
				var resultSpan = document.getElementById('up-send-test-result');
				sendBtn.addEventListener('click', function(e){
					e.preventDefault();
					resultSpan.textContent = 'Sending…';
					var data = new FormData();
					data.append('action', 'up_send_test_event');
					data.append('nonce', '<?php echo wp_create_nonce( 'up-send-test' ); ?>');
					fetch( ajaxurl, {
						method: 'POST',
						body: data,
						credentials: 'same-origin'
					}).then(function(resp){
						return resp.json();
					}).then(function(json){
						if (json && json.success) {
							resultSpan.innerHTML = '<span style="color:green">Test sent — response: ' + (json.data.status || 'OK') + '</span>';
						} else {
							var msg = (json && json.data && json.data.message) ? json.data.message : 'Unknown error';
							resultSpan.innerHTML = '<span style="color:red">Failed: ' + msg + '</span>';
						}
					}).catch(function(err){
						resultSpan.innerHTML = '<span style="color:red">Request failed</span>';
					});
				});
			})();
		</script>
		<?php
	}
}
