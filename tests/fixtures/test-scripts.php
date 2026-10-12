<?php
/**
 * Plugin Name: JPX Cookies LGPD — cenário de testes
 * Description: Insere scripts de terceiros simulados (AdSense, Meta Pixel, JSON-LD, módulo de estatísticas) e uma página longa. Só para o site de testes.
 */

add_action(
	'wp_head',
	static function () {
		echo '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-123" crossorigin="anonymous"></script>' . "\n";
		echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){};t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','123');window.__pixelRan=true;</script>\n";
		echo '<script type="application/ld+json">{"@context":"https://schema.org","note":"connect.facebook.net"}</script>' . "\n";
		echo '<script type="module">window.__clarityModule=true; /* clarity.ms */</script>' . "\n";
	},
	5
);

add_action(
	'wp_footer',
	static function () {
		echo '<div id="spacer" style="height:3000px"></div><a id="other-link" href="/politica-de-privacidade/">outra página</a>';
	},
	1
);

add_action(
	'wp_footer',
	static function () {
		echo do_shortcode( '<p>[jpx_cookies_preferencias]</p>' );
	}
);
