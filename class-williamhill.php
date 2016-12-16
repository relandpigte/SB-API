<?php

class Williamhill extends Bookmakers {
	protected $log_file = '/tmp/williamhill.log';

	function __construct() {
		libxml_use_internal_errors( true );

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: cachepricefeeds.williamhill.com\r\n"
				)
			)
		);

		$this->apiServer = 'http://cachepricefeeds.williamhill.com';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url  = $this->apiServer . "/openbet_cdn";
		$path = empty( $params['action'] ) ? "" : "?" . http_build_query( $params );

		switch ( $params['action'] ) {
			case 'GoPriceFeed':
				$this->host = "pricefeeds.williamhill.com";
				$url = "http://pricefeeds.williamhill.com/bet/en-gb";
				break;
			default:
				$this->host = "cachepricefeeds.williamhill.com";
		}

		$this->command = $url . $path;
    }

	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		if ( isset( $params['action'] ) && ! empty( $this->cache[$params['action']] ) ) {
			$this->command_result = $this->cache[$params['action']];
			return;
		}

		$this->prepare_command( $params );
		$this->log_action( "New command: {$this->command} from " . debug_backtrace_summary() );

		$result = file_get_contents( $this->command, false, $this->ctx );
		if ( $result === false || empty( $result ) ) {
			$this->log_action( $this->command . "\n" . 'Empty result or command failed.' );
			throw new Exception( $this->command . "\n" . 'Empty result or command failed.' );
		}

		if ( $params['action'] == 'GoPriceFeed' )
			$this->command_result = $result;
		else
			$this->parse_command_result( $result );

		$this->cache[$params['action']] = $this->command_result;

		$this->log_action( $this->command );
	}

	protected function parse_command_result( $result ) {
		libxml_clear_errors();

		$result = simplexml_load_string( $result );
		if ( ! $result ) {
			$errors = '';
			foreach ( libxml_get_errors() as $error )
				$errors .= $error->message;

			$this->log_action( $this->command . "\n" . 'XML Parsing errors: ' . $errors );
			throw new Exception( $this->command . "\n" . 'XML Parsing errors: ' . $errors );
		}

		$this->command_result = $result;
	}

	/**
	 * Extract market data
	 *
	 * @since 1.0.0
	 * @param $requests
	 * @param $is_live
	 * @param $market_type
	 * @return Array of events
	 */
	function extract_events_data( $requests, $is_live, $market_type ) {
		$events = array();
		foreach ( $requests as $request ) {
			$results = curl_multi( $request, true, '', true );

			foreach ( $results as $m_type => $league ) {
				$this->command_result = simplexml_load_string( $league );
				if( isset( $this->command_result->response->williamhill->class->type ) ) {

					foreach ( $this->command_result->response->williamhill->class->type as $league ) {
						$league_id = (string) $league->attributes()['id'];

						foreach ( $league->market as $market ) {
							$event_id = explode("/", (string) $market->attributes()['url'])[7];
							$event_name = urldecode(explode("/", (string) $market->attributes()['url'])[8]);
							$event_start = "{$market->attributes()['date']} {$market->attributes()['time']}";
							$event_end = "{$market->attributes()['betTillDate']} {$market->attributes()['betTillTime']}";

							$events[$event_id] = array(
								'name' => $event_name,
								'start_date_gmt' => $event_start,
								'end_date_gmt' => $event_end,
								'is_live' => $is_live ? "1" : "0",
								'status' => null,
								'meta' => array(
									'event_id' => $event_id,
									'tree_id' => null,
									'league_id' => $league_id
								),
								'markets' => array()
							);

							array_push($events[$event_id]['markets'], $this->extract_markets_data($market, $market_type[$m_type]));
						}
					}
				}
			}
		}

		return $events;
	}

	/**
	 * Extract market data
	 *
	 * @since 1.0.0
	 * @param $market
	 * @param $market_type
	 * @return Array of markets
	 * @throws Exception If event_id is not specified
	 */
	protected function extract_markets_data( $market, $market_type ) {
		if ( empty( $market ) || empty( $market_type ) )
			throw new Exception( 'market is required' );
		$start_date_gmt = "{$market->attributes()['date']} {$market->attributes()['time']}";
		$start_date_gmt		= "{$market->attributes()['betTillDate']} {$market->attributes()['betTillTime']}";
		$market_data = array(
			'name'			 => (string) $market->attributes()['name'],
			'market_type'	 => $market_type,
			'start_date_gmt' => $start_date_gmt,
			'end_date_gmt'	 => $start_date_gmt,
			'participants'	 => $this->extract_participants_data( $market ),
			'meta' => array(
                'market_id' => (string) $market->attributes()['id']
            )
		);

		return $market_data;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param $participants
	 * @return Array Returns array of participants
	 * @throws Exception if market is not specified
	 */
	protected function extract_participants_data( $participants ) {
		if ( empty( $participants ) )
			throw new Exception( 'participants is required' );

		$data = array();
		foreach ( $participants as $participant ) {
			$handicap = (string) $participant->attributes()['handicap'];
			if ( empty( $handicap ) )
				$handicap = 0;

			$participant_name = (string) $participant->attributes()['name'];
			if ( '' === $participant_name )
				continue; // no empty participants please

			$data[] = array(
				'name'		 => (string) $participant->attributes()['name'],
				'handicap'	 => $handicap,
				'price_data' => (string) $participant->attributes()['oddsDecimal'],
				'status'	 => null,
				'meta' => array(
                    'participant_id' => (string) $participant->attributes()['id']
                )
			);
		}
		return $data;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return Array Returns array( $class_id => $class_name, ... )
	 */
	function get_sports() {
		$params = $data = array();
		$params['action'] = 'GoPriceFeed';

		$this->send_command( $params );
		$doc = new DOMDocument();
		$doc->loadHTML( $this->command_result );

		foreach ( $doc->getElementsByTagName('*') as $element ) {
			if ( $element->hasAttributes() && $element->tagName == 'a' ) {
				$query = parse_url( $element->getAttribute( 'href' ), PHP_URL_QUERY );
				$sport_name = $element->parentNode->parentNode->firstChild->nodeValue;

				parse_str( $query, $vars );
				if ( ! array_key_exists( $vars['classId'], $data ) )
					$data[$vars['classId']] = $sport_name;
			}
		}
		return $data;
	}


	/**
	 * Get leagues
	 *
	 * @since 1.0.0
	 * @param $sport_id
	 * @return Array Returns array( $bookmaker_league_id => $league_name, ... )
	 * @throws Exception if $sport_id and $data is empty
	 */
	function get_leagues( $sport_id ) {
		if ( empty( $sport_id ) )
			throw new Exception( 'sport_id is required' );

		$params = $data = $requests = array();
		$params['action'] = 'GoPriceFeed';

		$this->send_command( $params );
		$doc = new DOMDocument();
		$doc->loadHTML( $this->command_result );

		foreach ( $doc->getElementsByTagName('*') as $element ) {
			if ( $element->hasAttributes() && $element->tagName == 'a' ) {
				$query = parse_url( $element->getAttribute( 'href' ), PHP_URL_QUERY );

				parse_str( $query, $vars );
				if ( $vars['classId'] != $sport_id )
					continue;

				$requests[] = "http://cachepricefeeds.williamhill.com/openbet_cdn?$query";
			}
		}

		$data = array();
		$requests = array_chunk( $requests, 15, true );
		foreach ( $requests as $key => $request ) {
			$results = curl_multi( $request, true, '', true );

			foreach ( $results as $league ) {
				$this->command_result = simplexml_load_string( $league );

				if ( isset( $this->command_result->response->williamhill->class->type ) ) {
					foreach ($this->command_result->response->williamhill->class->type as $league) {
						$data[(string)$league->attributes()['id']] = (string)$league->attributes()['name'];
					}
				}
			}
		}
		return $data;
	}

	/**
	 * Get events
	 *
	 * @since 1.0.0
	 * @param $sport_id
	 * @param $last_poll
	 * @param $is_live
	 * @return Array of leagues
	 * @throws Exception If $sport_id does not exists or if there's not events found.
	 */
	function get_events( $sport_id, $last_poll = null, $is_live = null ) {
		$params = $data = $requests = $market_type = $live = array();
		$params['action'] = 'GoPriceFeed';

        $last_poll = ( $last_poll ) ? time() : $last_poll;
		$filterBIR = ( $is_live ) ? "Y" : "N";

		$this->send_command( $params );
		$doc = new DOMDocument();
		$doc->loadHTML( $this->command_result );

		foreach ( $doc->getElementsByTagName('*') as $element ) {
			if ( $element->hasAttributes() && $element->tagName == 'a' ) {
				$query = parse_url( $element->getAttribute( 'href' ), PHP_URL_QUERY );
				parse_str( $query, $vars );

				if ( ( isset( $sport_id ) && $vars['classId'] != $sport_id ) ||
					$vars['filterBIR'] != $filterBIR )
				{
					continue;
				}

				$i = preg_replace( '/[^A-Za-z0-9]/', '', $query );
				$market_type[$i] = $element->parentNode->previousSibling->previousSibling->textContent;
				$live[$i] = $vars['filterBIR'] == "Y" ? 1 : 0;
				$requests[] = "http://cachepricefeeds.williamhill.com/openbet_cdn?$query";
			}
		}

		$requests = array_chunk( $requests, 15, true );
		$data = array_values( $this->extract_events_data( $requests, $is_live, $market_type ) );

        return array( 'last_poll' => $last_poll, 'events' => $data );
	}

	public function garbage_collect() {
		curl_multi_garbage_collect();

		foreach ( $this->cache as $key => $data )
			$this->cache[$key] = null;
	}

	public function get_results( $event_id ) {}
}