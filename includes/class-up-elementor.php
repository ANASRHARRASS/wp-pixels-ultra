<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Elementor integration helpers for Ultra Pixels plugin
 * Provides automatic tracking for Elementor widgets, popups, and forms
 */
class UP_Elementor {

	public static function init() {
		// Only initialize if Elementor is active
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Add tracking scripts for Elementor pages
		add_action( 'wp_footer', array( __CLASS__, 'output_tracking_script' ), 999 );

		// Hook into Elementor form submissions
		add_action( 'elementor_pro/forms/new_record', array( __CLASS__, 'on_form_submit' ), 10, 2 );
	}

	/**
	 * Output JavaScript for tracking Elementor interactions
	 */
	public static function output_tracking_script() {
		// Only on pages with Elementor content
		if ( ! \Elementor\Plugin::$instance->documents->get_current() ) {
			return;
		}

		?>
<script>
(function() {
	'use strict';
	
	// Check if UP_CONFIG and dataLayer are available
	if (typeof window.dataLayer === 'undefined') {
		window.dataLayer = [];
	}
	
	/**
	 * Track Elementor Popup Open/Close
	 */
	jQuery(document).on('elementor/popup/show', function(event, id, instance) {
		var popupSettings = instance.getSettings('settings');
		var popupTitle = popupSettings ? (popupSettings.popup_title || id) : id;
		
		window.dataLayer.push({
			event: 'up_event',
			event_name: 'popup_open',
			event_id: 'popup_' + id + '_' + Date.now(),
			event_time: Math.floor(Date.now() / 1000),
			source_url: window.location.href,
			custom_data: {
				popup_id: id,
				popup_title: popupTitle,
				trigger_type: 'elementor'
			}
		});
		
		// Also send to server
		if (window.UP_CONFIG && window.UP_CONFIG.ingest_url) {
			fetch(window.UP_CONFIG.ingest_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.UP_CONFIG.nonce || ''
				},
				body: JSON.stringify({
					event_name: 'popup_open',
					event_id: 'popup_' + id + '_' + Date.now(),
					event_time: Math.floor(Date.now() / 1000),
					source_url: window.location.href,
					custom_data: { popup_id: id, popup_title: popupTitle }
				}),
				keepalive: true
			}).catch(function(e) { /* ignore */ });
		}
	});
	
	jQuery(document).on('elementor/popup/hide', function(event, id, instance) {
		window.dataLayer.push({
			event: 'up_event',
			event_name: 'popup_close',
			event_id: 'popup_close_' + id + '_' + Date.now(),
			event_time: Math.floor(Date.now() / 1000),
			source_url: window.location.href,
			custom_data: {
				popup_id: id,
				trigger_type: 'elementor'
			}
		});
	});
	
	/**
	 * Track Button Clicks with data attributes
	 * Look for Elementor buttons with tracking attributes
	 */
	jQuery(document).on('click', '.elementor-button[data-up-event], .elementor-widget-button [data-up-event]', function(e) {
		var $btn = jQuery(this);
		var eventName = $btn.attr('data-up-event') || 'button_click';
		var payload = {};
		
		try {
			var payloadStr = $btn.attr('data-up-payload');
			if (payloadStr) {
				payload = JSON.parse(payloadStr);
			}
		} catch (err) { /* ignore */ }
		
		// Add button context
		payload.button_text = $btn.text().trim();
		payload.button_url = $btn.attr('href') || '';
		payload.widget_type = 'elementor_button';
		
		window.dataLayer.push({
			event: 'up_event',
			event_name: eventName,
			event_id: 'btn_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
			event_time: Math.floor(Date.now() / 1000),
			source_url: window.location.href,
			custom_data: payload
		});
		
		// Send to server
		if (window.UP_CONFIG && window.UP_CONFIG.ingest_url) {
			fetch(window.UP_CONFIG.ingest_url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': window.UP_CONFIG.nonce || ''
				},
				body: JSON.stringify({
					event_name: eventName,
					event_id: 'btn_' + Date.now(),
					event_time: Math.floor(Date.now() / 1000),
					source_url: window.location.href,
					custom_data: payload
				}),
				keepalive: true
			}).catch(function(e) { /* ignore */ });
		}
	});
	
	/**
	 * Track Accordion/Tab interactions
	 */
	jQuery(document).on('click', '.elementor-tab-title, .elementor-accordion .elementor-tab-title', function(e) {
		var $title = jQuery(this);
		var tabIndex = $title.data('tab');
		var tabTitle = $title.text().trim();
		
		window.dataLayer.push({
			event: 'up_event',
			event_name: 'tab_click',
			event_id: 'tab_' + Date.now(),
			event_time: Math.floor(Date.now() / 1000),
			source_url: window.location.href,
			custom_data: {
				tab_index: tabIndex,
				tab_title: tabTitle,
				widget_type: 'elementor_tabs'
			}
		});
	});
	
	/**
	 * Track Video Widget play/pause
	 */
	if (typeof YT !== 'undefined' && YT.Player) {
		// YouTube videos in Elementor
		jQuery('.elementor-widget-video iframe[src*="youtube"]').each(function() {
			var $iframe = jQuery(this);
			var videoId = $iframe.attr('src').match(/embed\/([^?]+)/);
			if (videoId && videoId[1]) {
				// Track via GTM YouTube trigger instead
			}
		});
	}
	
})();
</script>
		<?php
	}

	/**
	 * Track Elementor Pro form submissions
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public static function on_form_submit( $record, $ajax_handler ) {
		$form_name = $record->get_form_settings( 'form_name' );
		$form_id   = $record->get_form_settings( 'id' );
		$fields    = $record->get( 'fields' );

		// Build form data (don't include sensitive fields)
		$form_data = array(
			'form_name'   => $form_name,
			'form_id'     => $form_id,
			'field_count' => count( $fields ),
		);

		// Check if email field exists (for user data)
		$email = '';
		foreach ( $fields as $field_id => $field ) {
			if ( in_array( $field['type'], array( 'email' ), true ) ) {
				$email = $field['value'];
				break;
			}
		}

		// Push to dataLayer via inline script
		add_action(
			'wp_footer',
			function () use ( $form_name, $form_id, $email ) {
				?>
<script>
if (window.dataLayer) {
	window.dataLayer.push({
		event: 'up_event',
		event_name: 'form_submit',
		event_id: 'form_<?php echo esc_js( $form_id ); ?>_<?php echo time(); ?>',
		event_time: <?php echo time(); ?>,
		source_url: window.location.href,
		user_data: {
				<?php if ( $email ) : ?>
			email_hash: '<?php echo hash( 'sha256', strtolower( trim( $email ) ) ); ?>'
			<?php endif; ?>
		},
		custom_data: {
			form_name: '<?php echo esc_js( $form_name ); ?>',
			form_id: '<?php echo esc_js( $form_id ); ?>',
			form_type: 'elementor'
		}
	});
}
</script>
				<?php
			},
			999
		);

		// Send to CAPI queue
		$payload = array(
			'event_id'    => 'form_' . $form_id . '_' . time(),
			'event_time'  => time(),
			'custom_data' => array(
				'form_name' => $form_name,
				'form_id'   => $form_id,
				'form_type' => 'elementor',
			),
		);

		if ( $email && class_exists( 'UP_CAPI' ) ) {
			$payload['email_hash'] = hash( 'sha256', strtolower( trim( $email ) ) );
			UP_Events::send_to_capi_for_platforms( 'form_submit', $payload );
		}
	}
}
