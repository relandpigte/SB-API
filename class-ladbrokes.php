<?php

class Ladbrokes extends Bookmakers {
	protected $log_file = '/tmp/ladbrokes.log';
	private   $cache	= array();

	function __construct() {
		libxml_use_internal_errors( true );

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: www.ladbrokes.com\r\n"
				)
			)
		);

		$this->apiServer = 'http://ire.socialbet.ru:4080';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url = "{$this->apiServer}/oxi/external/dbPublish";

		if ( empty( $params ) )
			throw new Exception( 'Unknown command' );

		$this->command = $url . "?" . http_build_query( $params );
	}

	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		$cache = preg_replace( '/[^A-Za-z0-9]/', '', http_build_query( $params ) );

		if ( ! empty( $this->cache[$cache] ) ) {
			$this->command_result = $this->cache[$cache];
			return;
		}

		$this->prepare_command( $params );
		$this->log_action( "New command: {$this->command} from " . debug_backtrace_summary() );

		$result = file_get_contents( $this->command, false, $this->ctx );
		if ( $result === false || empty( $result ) ) {
			$this->log_action( $this->command . "\n" . 'Empty result or command failed.' );
			throw new Exception( $this->command . "\n" . 'Empty result or command failed.' );
		}

		$this->parse_command_result( $result );
		$this->cache[$cache] = $this->command_result;
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
	 * @return Array of markets
	 * @throws Exception If event_id is not specified
	 */
	protected function extract_markets_data( $requests ) {
		if ( ! empty( $requests ) ) {
			$markets = array();
			foreach ( $requests as $request ) {
				$results = curl_multi( $request, true );
				foreach ( $results as $key => $result ) {
					$this->command_result = simplexml_load_string( $result );
					if ( ! isset( $this->command_result->response->event ) )
						continue;

					$data = array();
					foreach ( $this->command_result->response->event as $event ) {
						foreach ( $event->market as $market ) {

							$start_date = $this->command_result->response->event->attributes()['date'] . " " .
								$this->command_result->response->event->attributes()['time'];

							$end_date = $this->command_result->response->event->attributes()['betTillDate'] . " " .
								$this->command_result->response->event->attributes()['betTillTime'];

							$data[] = array(
								'name' => (string) $market->attributes()['name'],
								'market_type' => (string) $market->attributes()['channels'],
								'start_date_gmt' => $start_date,
								'end_date_gmt' => $end_date,
								'participants' => $this->extract_participants_data( $market ),
								'meta' => array(
                                    'market_id' => (string) $market->attributes()['id']
                                )
							);
						}
					}
					$markets[$key] = $data;
				}
			}
			return $markets;
		}
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param $markets
	 * @return Array Returns array of participants
	 * @throws Exception if market is not specified
	 */
	protected function extract_participants_data( $markets ) {
		if ( ! empty( $markets ) ) {
			$data = array();
			foreach ( $markets->outcome as $participant ) {
				$handicap = (string) $participant->attributes()['handicap'];
				if ( empty( $handicap ) )
					$handicap = 0;

				$data[] = array(
					'name' => (string) $participant->attributes()['name'],
					'handicap' => $handicap,
					'price_data' => (string) $participant->attributes()['oddsDecimal'],
					'status' => (string) $participant->attributes()['status'],
					'meta' => array(
                        'participant_id' => (string) $participant->attributes()['id']
                    )
				);
			}
			return $data;
		}
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return Array Returns array( $class_id => $class_name, ... )
	 */
	function get_sports() {
		$params = array();
		$params['action']	= 'template';
		$params['template'] = 'getClasses';

		$this->send_command( $params );
		$data = array();
		foreach ( $this->command_result->children() as $response ) {
			foreach ( $response->class as $class ) {
				$data[(string) $class->attributes()['id']] = (string) $class->attributes()['name'];
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

		$params = array();
		$params['action']	= 'template';
		$params['template'] = 'getEventsByClass';
		$params['class']	= $sport_id;

		$this->send_command( $params );

		$leagues = array();
		foreach ( $this->command_result->response->class->type as $league ) {
			$leagues[(string) $league->attributes()['id']] = (string) $league->attributes()['name'];
		}

		if ( empty( $leagues ) )
			throw new Exception( "No leagues matches with the sport id $sport_id." );

		return $leagues;
	}

	/**
	 * Get leagues
	 *
	 * @since 1.0.0
	 * @param $sport_id
	 * @param $last_poll
	 * @param $is_live
	 * @return Array of leagues
	 * @throws Exception If $sport_id does not exists or if there's not events found.
	 */
	function get_events( $sport_id, $last_poll = null, $is_live = null ) {
		if ( empty( $sport_id ) )
			throw new Exception( 'sport_id is required' );

		$params = array();
		$params['action']	= 'template';
		$params['template'] = 'getEventsByClass';
		$params['class']	= $sport_id;

        $last_poll = is_null( $last_poll ) ? time() : $last_poll;

		$this->send_command( $params );
		$data = $requests = array();

		foreach ( $this->command_result->response->class->type as $league ) {
			$league_id = (string) $league->attributes()['id'];

			foreach ( $league->event as $event ) {
				$start_date	= (string) $event->attributes()['date'] . " " . (string) $event->attributes()['time'];
				$end_date = (string) $event->attributes()['betTillDate'] . " " . (string) $event->attributes()['betTillTime'];
				$requests[] = $url = "{$this->apiServer}/oxi/external/dbPublish?action=template&template=getEventDetails&event=" .
					$event->attributes()['id'];

				$i = preg_replace( '/[^A-Za-z0-9]/', '', parse_url( $url, PHP_URL_QUERY ) );
				$data[$i] = array(
					'name'			 => (string) $event->attributes()['name'],
					'start_date_gmt' => $start_date,
					'end_date_gmt'	 => $end_date,
					'is_live'		 => null,
					'status'		 => (string) $event->attributes()['status'],
					'meta' => array(
						'event_id'	=> (string) $event->attributes()['id'],
						'tree_id'	=> null,
						'league_id' => $league_id
					),
					'markets' => null
				);
			}
		}

		$requests = array_chunk( $requests, 15, false );
		$markets  = $this->extract_markets_data( $requests );
		if ( ! empty( $markets ) ) {
			foreach ( $markets as $key => $market ) {
				$data[$key]['markets'] = $market;
			}
		}

		$data = array_values( $data );

        return array( 'last_poll' => $last_poll, 'events' => $data );
	}

	public function get_results( $vent_id ) {}

	public function garbage_collect() {
		curl_multi_garbage_collect();

		foreach ( $this->cache as $key => $value ) {
			$this->cache[$key] = null;
		}
	}
}