<?php
/**
 * Shared helpers for metadata normalization and URL building.
 *
 * These functions never unserialize data: WordPress already returns
 * unserialized values when a meta key is requested with get_post_meta().
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Version string used as the `ver` argument of every enqueued asset.
 *
 * Assets are versioned with the plugin version, never with filemtime(). The
 * release build normalizes every packaged file mtime to 1980-01-01 so that two
 * builds of the same sources produce a byte-identical ZIP; filemtime() would
 * therefore return one frozen value for every release and browsers would keep
 * stale CSS or JavaScript after an update.
 *
 * MY_ECARD_VERSION is the single source of truth. It must stay equal to the
 * `Version:` header of the main plugin file, and scripts/build-release.sh fails
 * the build when the two diverge.
 *
 * @return string
 */
function my_ecard_asset_version(): string {
	return MY_ECARD_VERSION;
}

/**
 * Read a scalar post meta value as a trimmed string.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return string
 */
function get_meta_string( int $post_id, string $key ): string {
	$value = get_post_meta( $post_id, $key, true );

	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Read a post meta value as an array.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return array
 */
function get_meta_array( int $post_id, string $key ): array {
	$value = get_post_meta( $post_id, $key, true );

	return is_array( $value ) ? $value : array();
}

/**
 * Clamp a numeric value to an integer range.
 *
 * @param mixed $value    Raw value.
 * @param int   $min      Minimum.
 * @param int   $max      Maximum.
 * @param int   $fallback Value used when the input is not numeric.
 * @return int
 */
function clamp_int( $value, int $min, int $max, int $fallback ): int {
	if ( is_string( $value ) ) {
		$value = trim( $value );
	}

	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}

	return max( $min, min( $max, (int) $value ) );
}

/**
 * Validate a value against a closed allowlist.
 *
 * @param mixed  $value    Raw value.
 * @param array  $allowed  Allowed values.
 * @param string $fallback Value used when the input is not allowed.
 * @return string
 */
function choice( $value, array $allowed, string $fallback ): string {
	$value = is_scalar( $value ) ? (string) $value : '';

	return in_array( $value, $allowed, true ) ? $value : $fallback;
}

/**
 * Validate an optional hex color.
 *
 * @param mixed  $value    Raw value.
 * @param string $fallback Value used when the input is empty or invalid.
 * @return string
 */
function hex_color( $value, string $fallback ): string {
	$value = is_scalar( $value ) ? trim( (string) $value ) : '';

	if ( '' === $value ) {
		return $fallback;
	}

	$color = sanitize_hex_color( $value );

	return is_string( $color ) ? $color : $fallback;
}

/**
 * Sanitize a URL and enforce an explicit scheme allowlist.
 *
 * @param string $url     Raw URL.
 * @param array  $schemes Allowed schemes.
 * @return string Empty string when the URL is not acceptable.
 */
function safe_url( string $url, array $schemes = array( 'http', 'https' ) ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	$clean = esc_url_raw( $url, $schemes );

	if ( '' === $clean ) {
		return '';
	}

	$scheme = parse_url( $clean, PHP_URL_SCHEME );

	if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), $schemes, true ) ) {
		return '';
	}

	return $clean;
}

/**
 * Normalize a phone number, keeping an optional leading plus sign.
 *
 * @param string $raw Raw phone number.
 * @return string
 */
function normalize_phone( string $raw ): string {
	$raw = trim( $raw );

	if ( '' === $raw ) {
		return '';
	}

	$has_plus = 0 === strpos( $raw, '+' );
	$digits   = preg_replace( '/\D+/', '', $raw );

	if ( ! is_string( $digits ) || '' === $digits ) {
		return '';
	}

	return ( $has_plus ? '+' : '' ) . $digits;
}

/**
 * Build a tel: URL from a stored phone number.
 *
 * @param string $raw Raw phone number.
 * @return string
 */
function tel_url( string $raw ): string {
	$phone = normalize_phone( $raw );

	return '' === $phone ? '' : 'tel:' . $phone;
}

/**
 * Build a wa.me URL from a stored WhatsApp number.
 *
 * Numbers shorter than 8 digits or longer than 15 digits are rejected
 * because they cannot be valid international numbers.
 *
 * @param string $raw Raw WhatsApp number.
 * @return string
 */
function whatsapp_url( string $raw ): string {
	$digits = preg_replace( '/\D+/', '', $raw );

	if ( ! is_string( $digits ) ) {
		return '';
	}

	$length = strlen( $digits );

	if ( $length < 8 || $length > 15 ) {
		return '';
	}

	return 'https://wa.me/' . $digits;
}

/**
 * Extract the src attribute from an iframe string.
 *
 * @param string $html Raw iframe HTML.
 * @return string
 */
function extract_iframe_src( string $html ): string {
	$html = trim( $html );

	if ( '' === $html ) {
		return '';
	}

	if ( preg_match( '/<\s*iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/i', $html, $matches ) ) {
		return trim( $matches[2] );
	}

	return '';
}

/**
 * Build a Google Maps embed URL from an ECard map iframe.
 *
 * @param string $iframe_html Raw iframe HTML.
 * @return string
 */
function map_embed_url( string $iframe_html ): string {
	$src = extract_iframe_src( $iframe_html );

	if ( '' === $src ) {
		return '';
	}

	$url = safe_url( $src );

	if ( '' === $url ) {
		return '';
	}

	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
	$path = (string) parse_url( $url, PHP_URL_PATH );

	if ( ! preg_match( '/(^|\.)google\.[a-z.]{2,}$/', $host ) ) {
		return '';
	}

	if ( false === strpos( $path, '/maps' ) ) {
		return '';
	}

	return $url;
}

/**
 * Detect the video provider from a URL host.
 *
 * @param string $url Video URL.
 * @return string One of youtube, vimeo, other or an empty string.
 */
function video_provider( string $url ): string {
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

	if ( '' === $host ) {
		return '';
	}

	$host = (string) preg_replace( '/^www\./', '', $host );

	if ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com' ), true ) ) {
		return 'youtube';
	}

	if ( in_array( $host, array( 'vimeo.com', 'player.vimeo.com' ), true ) ) {
		return 'vimeo';
	}

	return 'other';
}

/**
 * Extract a YouTube video ID from a supported URL shape.
 *
 * Supported shapes: watch?v=ID, youtu.be/ID, /shorts/ID and /embed/ID.
 *
 * @param string $url Video URL.
 * @return string Valid 11-character ID or an empty string.
 */
function youtube_video_id( string $url ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	if ( 0 === strpos( $url, '//' ) ) {
		$url = 'https:' . $url;
	} elseif ( ! is_string( parse_url( $url, PHP_URL_SCHEME ) ) ) {
		$url = 'https://' . $url;
	}

	$parts = parse_url( $url );

	if ( ! is_array( $parts ) ) {
		return '';
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';

	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return '';
	}

	$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$host = (string) preg_replace( '/^www\./', '', $host );

	if ( ! in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com' ), true ) ) {
		return '';
	}

	$path     = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	$segments = array_values( array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' ) );
	$first    = isset( $segments[0] ) ? (string) $segments[0] : '';
	$second   = isset( $segments[1] ) ? (string) $segments[1] : '';
	$id       = '';

	if ( 'youtu.be' === $host ) {
		$id = $first;
	} elseif ( 'watch' === $first ) {
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';
		$args  = array();

		parse_str( $query, $args );

		$id = isset( $args['v'] ) && is_string( $args['v'] ) ? trim( $args['v'] ) : '';
	} elseif ( in_array( $first, array( 'shorts', 'embed' ), true ) ) {
		$id = $second;
	}

	return preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? $id : '';
}

/**
 * Extract a Vimeo video ID from a supported URL shape.
 *
 * Accepts vimeo.com/{id} and player.vimeo.com/video/{id}.
 *
 * @param string $url Video URL.
 * @return string Valid numeric ID or an empty string.
 */
function vimeo_video_id( string $url ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	if ( 0 === strpos( $url, '//' ) ) {
		$url = 'https:' . $url;
	} elseif ( ! is_string( parse_url( $url, PHP_URL_SCHEME ) ) ) {
		$url = 'https://' . $url;
	}

	$parts = parse_url( $url );

	if ( ! is_array( $parts ) ) {
		return '';
	}

	$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';

	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return '';
	}

	$host = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$host = (string) preg_replace( '/^www\./', '', $host );

	if ( ! in_array( $host, array( 'vimeo.com', 'player.vimeo.com' ), true ) ) {
		return '';
	}

	$path     = isset( $parts['path'] ) ? (string) $parts['path'] : '';
	$segments = array_filter( explode( '/', trim( $path, '/' ) ), 'strlen' );

	foreach ( $segments as $segment ) {
		if ( preg_match( '/^\d{6,12}$/', $segment ) ) {
			return (string) $segment;
		}
	}

	return '';
}

/**
 * Build a privacy-friendly embed URL from a normalized video array.
 *
 * @param array $video Video data with url and provider keys.
 * @return string Embed URL or an empty string when the data is not usable.
 */
function video_embed_url( array $video ): string {
	$url      = isset( $video['url'] ) && is_string( $video['url'] ) ? safe_url( $video['url'] ) : '';
	$provider = isset( $video['provider'] ) && is_scalar( $video['provider'] ) ? sanitize_key( (string) $video['provider'] ) : '';

	if ( '' === $url || '' === $provider ) {
		return '';
	}

	if ( 'youtube' === $provider ) {
		$id = youtube_video_id( $url );

		return '' === $id ? '' : 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0&modestbranding=1';
	}

	if ( 'vimeo' === $provider ) {
		$id = vimeo_video_id( $url );

		return '' === $id ? '' : 'https://player.vimeo.com/video/' . $id . '?autoplay=1';
	}

	return '';
}

/**
 * Check whether a URL points directly to a supported video file.
 *
 * @param string $url Video URL.
 * @return bool
 */
function is_direct_video_url( string $url ): bool {
	$url = safe_url( $url );

	if ( '' === $url ) {
		return false;
	}

	$path = parse_url( $url, PHP_URL_PATH );

	if ( ! is_string( $path ) || '' === $path ) {
		return false;
	}

	$extension = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

	return in_array( $extension, array( 'mp4', 'webm', 'ogg', 'ogv', 'm4v' ), true );
}

/**
 * Check a plugin version requirement such as ">=1.0.0".
 *
 * Supported formats: "*", "1.2.3", ">=1.2.3", ">1.2.3", "<=1.2.3", "<1.2.3", "=1.2.3".
 *
 * @param string $version    Installed version.
 * @param string $constraint Requirement.
 * @return bool
 */
function requirement_satisfied( string $version, string $constraint ): bool {
	$constraint = trim( $constraint );

	if ( '' === $constraint || '*' === $constraint ) {
		return true;
	}

	if ( ! preg_match( '/^(>=|<=|>|<|=)?\s*v?([0-9]+(?:\.[0-9]+){0,2}(?:-[0-9A-Za-z.-]+)?)$/', $constraint, $matches ) ) {
		return false;
	}

	$operator = '' === $matches[1] ? '=' : $matches[1];

	return version_compare( $version, $matches[2], $operator );
}
