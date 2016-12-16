<?php

function debug_backtrace_summary( $ignore_class = null, $skip_frames = 0, $pretty = true ) {
	if ( version_compare( PHP_VERSION, '5.2.5', '>=' ) )
		$trace = debug_backtrace( false );
	else
		$trace = debug_backtrace();

	$caller = array();
	$check_class = ! is_null( $ignore_class );
	$skip_frames++; // skip this function

	foreach ( $trace as $call ) {
		if ( $skip_frames > 0 ) {
			$skip_frames--;
		} elseif ( isset( $call['class'] ) ) {
			if ( $check_class && $ignore_class == $call['class'] )
				continue; // Filter out calls

			$caller[] = "{$call['class']}{$call['type']}{$call['function']}";
		} else {
			if ( in_array( $call['function'], array( 'do_action', 'apply_filters' ) ) ) {
				$caller[] = "{$call['function']}('{$call['args'][0]}')";
			} elseif ( in_array( $call['function'], array( 'include', 'include_once', 'require', 'require_once' ) ) ) {
				$caller[] = $call['function'] . "('" . str_replace( array( dirname( __DIR__ ) ) , '', $call['args'][0] ) . "')";
			} else {
				$caller[] = $call['function'];
			}
		}
	}
	if ( $pretty )
		return join( ', ', array_reverse( $caller ) );
	else
		return $caller;
}

/**
 * Curl multiple request
 *
 * @since 1.0.0
 * @param array $requests Array of request url
 * @param bool $persistent
 * @param string $host
 * @return array Returns array of content data from the requested URLs
 */
function curl_multi( $requests, $persistent = false, $host = '' ) {
	static $curl_multi;

	if ( empty( $curl_multi ) )
		$curl_multi = curl_multi_init();

	foreach ( $requests as $i => $req ) {
        $url = parse_url( $req );

        $host  = ! empty( $host ) ? $host : $url['host'];
        $port  = isset( $url['port'] ) ? $url['port'] : '';
        $query = isset( $url['query'] ) ? "?{$url['query']}" : "";

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, "{$url['scheme']}://{$url['host']}{$url['path']}$query" );
		curl_setopt( $curl, CURLOPT_PORT, $port );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: $host" ) );
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "GET" );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_NOBODY, false );
		curl_setopt( $curl, CURLOPT_HEADER, false );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
        curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
            'Connection: Keep-Alive',
            'Keep-Alive: 300'
        ));
		curl_multi_add_handle( $curl_multi, $curl );
	}

    $results = curl_multi_checking( $curl_multi );

	if ( ! $persistent ) {
		curl_multi_close( $curl_multi );
		$curl_multi = null;
	}

    return $results;
}

function curl_multi_checking( $curl_multi ) {
    $running = true;
    $results = array();

    while ( $running ) {
        do {
            $result = curl_multi_exec( $curl_multi, $running );
        } while ( $result == CURLM_CALL_MULTI_PERFORM );

        // Add curl_multi_strerror() on PHP 5.5 also curl_multi_setopt()
        if ( $result != CURLM_OK )
            error_log( 'curl_multi_exec() returned something different than CURLM_OK' );

        curl_multi_select( $curl_multi, 0.1 );
    }

    while ( $completed = curl_multi_info_read( $curl_multi ) ) {
        $info = curl_getinfo( $completed['handle'] );

        if ( ! $info['http_code'] && curl_error( $completed['handle'] ) ) {
            error_log('Error on: ' . $info['url'] . ' error: ' . curl_error($completed['handle']) . "\n");
            continue;
        }

        if ( '200' != $info['http_code'] ) {
            error_log('Request to ' . $info['url'] . ' returned HTTP code ' . $info['http_code'] . "\n");
            continue;
        }

        $i = preg_replace( '/[^A-Za-z0-9]/', '', parse_url( $info['url'], PHP_URL_QUERY ) );
        $results[$i] = curl_multi_getcontent( $completed['handle'] );

        curl_multi_remove_handle( $curl_multi, $completed['handle'] );
    }

    return $results;
}

// TODO: port the changes to the curl functions
function curl_multi_garbage_collect() {
	global $curl_multi_handles;

	if ( ! is_array( $curl_multi_handles ) )
		return;

	foreach ( $curl_multi_handles as $handle )
		curl_close( $handle );
}

function convert_to_gmt_date( $date, $tz, $format = 'Y-m-d H:i:s' ) {
    try {
        $datetime = new DateTime( $date, new DateTimeZone( $tz ) );
        $datetime->setTimezone( new DateTimeZone( 'GMT' ) );
        return $datetime->format( $format );
    }
    catch ( Exception $e ) {
        return false;
    }
}