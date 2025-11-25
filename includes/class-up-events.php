<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class UP_Events {
	public static function init() {
		if ( function_exists( 'is_woocommerce' ) ) {
			add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_purchase' ), 10, 1 );
			add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'on_add_to_cart' ), 10, 6 );
			add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'on_begin_checkout' ), 10 );
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_view_item' ) );
	}

	protected static function get_mapping() {
		$raw     = UP_Settings::get( 'event_mapping', '{}' );
		$decoded = json_decode( $raw, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			return $decoded;
		}
		return array();
	}

	public static function push_data_layer( $data = array() ) {
		// Normalize to GTM custom event schema
		if ( ! isset( $data['event'] ) ) {
			$data['event'] = 'up_event';
		}
		// Backwards compatibility: if legacy 'event' value was the business event name, copy into event_name
		if ( ! isset( $data['event_name'] ) && isset( $data['event'] ) && $data['event'] !== 'up_event' ) {
			$data['event_name'] = $data['event'];
		}

		// Add Enhanced Ecommerce data for GA4 compatibility if not already present
		if ( ! isset( $data['ecommerce'] ) && isset( $data['contents'] ) && is_array( $data['contents'] ) ) {
			$ecommerce = array();

			// Build items array in GA4 format
			$items = array();
			foreach ( $data['contents'] as $content ) {
				$item = array(
					'item_id'  => isset( $content['id'] ) ? (string) $content['id'] : '',
					'quantity' => isset( $content['quantity'] ) ? intval( $content['quantity'] ) : 1,
					'price'    => isset( $content['item_price'] ) ? (float) $content['item_price'] : 0,
				);
				// Add product name if available
				if ( isset( $content['name'] ) ) {
					$item['item_name'] = $content['name'];
				} elseif ( isset( $content['id'] ) && function_exists( 'wc_get_product' ) ) {
					$product = wc_get_product( $content['id'] );
					if ( $product ) {
						$item['item_name'] = $product->get_name();
					}
				}
				$items[] = $item;
			}

			$ecommerce['items'] = $items;

			// Add transaction-level data if available
			if ( isset( $data['transaction_id'] ) ) {
				$ecommerce['transaction_id'] = (string) $data['transaction_id'];
			}
			if ( isset( $data['value'] ) ) {
				$ecommerce['value'] = (float) $data['value'];
			}
			if ( isset( $data['currency'] ) ) {
				$ecommerce['currency'] = (string) $data['currency'];
			}

			$data['ecommerce'] = $ecommerce;
		}

		// Ensure event_time is set for server-side tracking
		if ( ! isset( $data['event_time'] ) ) {
			$data['event_time'] = time();
		}

		echo '<script>window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $data ) . ');</script>';
	}

	public static function send_to_capi_for_platforms( $event_key, $payload ) {
		$map                 = self::get_mapping();
		$supported_platforms = array( 'meta', 'tiktok', 'google_ads', 'snapchat', 'pinterest' );

		if ( ! isset( $map[ $event_key ] ) ) {
			// Default: send to enabled platforms with generic event name
			foreach ( $supported_platforms as $platform ) {
				$enable_key = 'enable_' . $platform;
				if ( UP_Settings::get( $enable_key, 'no' ) === 'yes' ) {
					UP_CAPI::enqueue_event( $platform, ucfirst( $event_key ), $payload );
				}
			}
			return;
		}

		$platforms = $map[ $event_key ];
		foreach ( $platforms as $platform => $cfg ) {
			if ( in_array( $platform, $supported_platforms, true ) ) {
				$event_name = isset( $cfg['event_name'] ) ? $cfg['event_name'] : ( isset( $cfg['event'] ) ? $cfg['event'] : ucfirst( $event_key ) );

				// Check if platform is enabled
				$enable_key = 'enable_' . $platform;
				if ( UP_Settings::get( $enable_key, 'no' ) === 'yes' ) {
					if ( method_exists( 'UP_CAPI', 'enqueue_event' ) ) {
						UP_CAPI::enqueue_event( $platform, $event_name, $payload );
					} else {
						UP_CAPI::send_event( $platform, $event_name, $payload, false );
					}
				}
			}
		}
	}

	public static function on_purchase( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$currency = $order->get_currency();
		$total    = (float) $order->get_total();

		$contents = array();
		foreach ( $order->get_items() as $item ) {
			$product    = $item->get_product();
			$contents[] = array(
				'id'         => $product ? $product->get_id() : $item->get_product_id(),
				'quantity'   => $item->get_quantity(),
				'item_price' => (float) $item->get_total() / max( 1, $item->get_quantity() ),
			);
		}

		$dl = array(
			'event'          => 'up_event',
			'event_name'     => 'purchase',
			'event_id'       => 'order_' . $order->get_id(),
			'transaction_id' => $order->get_id(),
			'value'          => $total,
			'currency'       => $currency,
			'contents'       => $contents,
		);
		self::push_data_layer( $dl );

		$payload = array(
			'event_id'       => 'order_' . $order->get_id(),
			'transaction_id' => $order->get_id(),
			'value'          => $total,
			'currency'       => $currency,
			'contents'       => $contents,
			'email_hash'     => self::get_order_email_hash( $order ),
		);

		self::send_to_capi_for_platforms( 'purchase', $payload );
	}

	public static function on_add_to_cart() {
		$args       = func_get_args();
		$product_id = isset( $args[1] ) ? intval( $args[1] ) : ( isset( $args[0] ) ? intval( $args[0] ) : 0 );
		$quantity   = isset( $args[2] ) ? intval( $args[2] ) : 1;

		if ( $product_id ) {
			$product = wc_get_product( $product_id );
			$price   = $product ? (float) $product->get_price() : 0.0;

			$dl = array(
				'event'      => 'up_event',
				'event_name' => 'add_to_cart',
				'product_id' => $product_id,
				'value'      => $price,
				'quantity'   => $quantity,
			);
			self::push_data_layer( $dl );

			$payload = array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
				'value'      => $price,
			);

			self::send_to_capi_for_platforms( 'add_to_cart', $payload );
		}
	}

	public static function on_begin_checkout() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}
		$items    = array();
		$total    = (float) $cart->get_total( 'edit' );
		$currency = get_woocommerce_currency();
		foreach ( $cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			$items[] = array(
				'id'         => $product ? $product->get_id() : ( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 ),
				'quantity'   => isset( $cart_item['quantity'] ) ? intval( $cart_item['quantity'] ) : 1,
				'item_price' => isset( $cart_item['line_total'] ) ? (float) $cart_item['line_total'] / max( 1, $cart_item['quantity'] ) : 0,
			);
		}

		$dl = array(
			'event'      => 'up_event',
			'event_name' => 'begin_checkout',
			'value'      => $total,
			'currency'   => $currency,
			'contents'   => $items,
		);
		self::push_data_layer( $dl );

		$payload = array(
			'value'    => $total,
			'currency' => $currency,
			'contents' => $items,
		);
		self::send_to_capi_for_platforms( 'begin_checkout', $payload );
	}

	public static function maybe_view_item() {
		// product single page
		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = get_the_ID();
			$product    = wc_get_product( $product_id );
			$price      = $product ? (float) $product->get_price() : 0.0;

			$dl = array(
				'event'      => 'up_event',
				'event_name' => 'view_item',
				'product_id' => $product_id,
				'value'      => $price,
			);
			add_action(
				'wp_head',
				function () use ( $dl ) {
					echo '<script>window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $dl ) . ');</script>';
				},
				100
			);

			$payload = array(
				'product_id' => $product_id,
				'value'      => $price,
			);
			self::send_to_capi_for_platforms( 'view_item', $payload );
			return;
		}

		// product listing / shop / category
		if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product_category' ) && is_product_category() ) ) {
			$dl = array(
				'event'      => 'up_event',
				'event_name' => 'view_item_list',
				'page'       => '',
			);
			add_action(
				'wp_head',
				function () use ( $dl ) {
					echo '<script>window.dataLayer = window.dataLayer || []; window.dataLayer.push(' . wp_json_encode( $dl ) . ');</script>';
				},
				100
			);

			$payload = array(
				'page_url' => home_url( add_query_arg( null, null ) ),
			);
			self::send_to_capi_for_platforms( 'view_item_list', $payload );
		}
	}

	protected static function get_order_email_hash( $order ) {
		if ( method_exists( $order, 'get_billing_email' ) ) {
			$email = $order->get_billing_email();
			if ( $email ) {
				return hash( 'sha256', strtolower( trim( $email ) ) );
			}
		}
		return '';
	}
}

// initialize hooks when file loaded
UP_Events::init();
