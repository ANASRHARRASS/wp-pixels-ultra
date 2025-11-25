<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end pixel output.
 */
class UP_Front {

	/**
	 * Output scripts in the head.
	 *
	 * @return void
	 */
	public static function output_head() {
		$enable_gtm    = 'yes' === UP_Settings::get( 'enable_gtm', 'no' );
		$gtm_id        = UP_Settings::get( 'gtm_container_id', '' );

		$enable_meta   = 'yes' === UP_Settings::get( 'enable_meta', 'no' );
		$meta_id       = UP_Settings::get( 'meta_pixel_id', '' );

		$enable_tiktok = 'yes' === UP_Settings::get( 'enable_tiktok', 'no' );
		$tiktok_id     = UP_Settings::get( 'tiktok_pixel_id', '' );

		if ( $enable_gtm && $gtm_id ) {
			echo '<!-- GTM (head) -->' . "\n";
			printf(
				"<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','%s');</script>\n",
				esc_js( $gtm_id )
			);
			echo '<!-- End GTM (head) -->' . "\n";
		}

		if ( $enable_meta && $meta_id ) {
			echo '<!-- Meta Pixel -->' . "\n";
			printf(
				"<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod? n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','%s');</script>\n",
				esc_js( $meta_id )
			);
			echo '<!-- End Meta Pixel -->' . "\n";
		}

		if ( $enable_tiktok && $tiktok_id ) {
			echo '<!-- TikTok Pixel -->' . "\n";
			printf(
				"<script>!function (w, d, t) { w.TiktokAnalyticsObject = t; var ttq = w[t] = w[t] || []; ttq.methods = ['page','track','identify','instances','debug','on','off','once','ready','alias']; ttq.setAndDefer = function (t, e) { t[e] = function () { t.push([e].concat(Array.prototype.slice.call(arguments, 0))) } }; for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]); ttq.instance = function (t) { for (var e = ttq._i[t] || [], n = 0; n < e.length; n++) ttq[e[n][0]].apply(ttq, e[n][1]) }; ttq.load = function (e, n) { var i = 'https://analytics.tiktok.com/i18n/pixel/events.js'; ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = i; ttq._t = ttq._t || {}; ttq._t[e] = +new Date; ttq._o = ttq._o || {}; ttq._o[e] = n || {}; var o = document.createElement('script'); o.type = 'text/javascript'; o.async = true; o.src = i + '?sdkid=' + e; var a = document.getElementsByTagName('script')[0]; a.parentNode.insertBefore(o, a) }; ttq.load('%s'); ttq.page(); }(window, document, 'ttq');</script>\n",
				esc_js( $tiktok_id )
			);
			echo '<!-- End TikTok Pixel -->' . "\n";
		}
	}

	/**
	 * Output body noscript for GTM.
	 *
	 * @return void
	 */
	public static function output_body() {
		$enable_gtm = 'yes' === UP_Settings::get( 'enable_gtm', 'no' );
		$gtm_id     = UP_Settings::get( 'gtm_container_id', '' );

		if ( $enable_gtm && $gtm_id ) {
			echo '<!-- GTM (body) -->' . "\n";
			printf(
				'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
				esc_attr( $gtm_id )
			);
			echo '<!-- End GTM (body) -->' . "\n";
		}
	}

}

*** End Patch
