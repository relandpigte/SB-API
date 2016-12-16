<?php

class Parimatch extends Bookmakers {
	protected $log_file = '/tmp/parimatch.log';

	function __construct() {
		libxml_use_internal_errors( true );

		$this->host = 'www.parimatch.com';
		$this->apiServer = 'http://www.parimatch.com/mb2/bets.xml';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url = $this->apiServer;

		if ( empty( $params ) )
			throw new Exception( 'Unknown command' );

		$this->command = $url . "?" . http_build_query( $params );
	}

	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		$this->prepare_command( $params );
		$this->log_action( "New command: {$this->command} from " . debug_backtrace_summary() );

		$url   = parse_url( $this->command );
		$port  = isset( $url['port'] ) ? $url['port'] : '';
		$query = isset( $url['query'] ) ? "?{$url['query']}" : "";

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, "{$url['scheme']}://{$url['host']}{$url['path']}$query" );
		curl_setopt( $curl, CURLOPT_PORT, $port );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, array( "Host: {$this->host}" ) );
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, "GET" );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_NOBODY, false );
		curl_setopt( $curl, CURLOPT_HEADER, false );
		curl_setopt( $curl, CURLOPT_TIMEOUT, MAX_TIMEOUT );

		$result = curl_exec( $curl );
		curl_close( $curl );

		if ( $result === false || empty( $result ) ) {
			$this->log_action( $this->command . "\n" . 'Empty result or command failed.' );
			throw new Exception( $this->command . "\n" . 'Empty result or command failed.' );
		}

		$this->parse_command_result( $result );
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
	 * Extract events data
	 *
	 * @since 1.0.0
	 * @param $requests
	 * @return Array Returns array of events
	 * @throws Exception If event_id and league_id is not specified
	 */
	protected function extract_events_data( $requests ) {
		if ( empty( $requests ) )
			throw new Exception( 'requests parameter is required' );

		$data = $market_requests = array();
		foreach ( $requests as $key => $request ) {
			$results = curl_multi( $request, true );

			foreach ( $results as $event ) {
				$this->command_result = simplexml_load_string( $event );
				if ( isset( $this->command_result->gr->item ) ) {
					foreach ( $this->command_result->gr->item as $item ) {
						parse_str( (string) $item->attributes()['url'], $params );

						$str_date = "";
						$datetime = explode( " ", trim( $item->attributes()['date'] ) );
						if ( count( $datetime ) > 1 ) {
							$date = isset( $datetime[0] ) ? str_replace( "/", "-", $datetime[0] . "-" . date('Y') ) : "";
							$time = isset( $datetime[1] ) ? $datetime[1] . ":00" : "";
							$str_date = date( "Y-m-d H:i:s", strtotime( "$date $time" ) );
						}

						$i = preg_replace('/[^A-Za-z0-9]/', '', (string) $item->attributes()['url']);
						$market_requests[] = "http://{$this->host}/mb2/bets.xml?{$item->attributes()['url']}";

						$data[$i] = array(
							'name' => (string) $item,
							'start_date_gmt' => convert_to_gmt_date( $str_date, 'GMT+2' ),
							'end_date_gmt' => null,
							'is_live' => 0,
							'status' => null,
							'meta' => array(
								'event_id' => $params['li'],
								'tree_id' => null,
								'league_id' => $params['gr']
							),
							'markets' => null
						);
					}
				}
			}
		}

		if ( ! empty( $market_requests ) ) {
			$market_requests = array_chunk( $market_requests, 10, true );
			$markets = $this->extract_markets_data( $market_requests );

			foreach ( $markets as $i => $m )
				$data[$i]['markets'] = $m;
		}
		return $data;
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
		if ( empty( $requests ) )
			throw new Exception( 'requests is required' );

		$markets = array();
		foreach ( $requests as $request ) {
			$results = curl_multi( $request, true );
			foreach( $results as $key => $result ) {
				$this->command_result = simplexml_load_string( $result );
				$participant_request = $tmp_market = array();

				if ( ! isset( $this->command_result->ev->item ) )
					continue;

				foreach( $this->command_result->ev->item as $market ) {
					if ( isset( $market->attributes()['url'] ) )
						$participant_request[] = "http://{$this->host}/mb2/bets.xml?{$market->attributes()['url']}";
				}

				$market_data = $this->get_market( $this->command_result->ev->item, $key );
				if ( ! empty( $participant_request ) ) {
					$participant_request = array_chunk( $participant_request, 10, true );
					$participants = $this->extract_participants_data( $participant_request );

					foreach ( $participants as $i => $p )
						$market_data[$i]['participants'] = $p;
				}

				$markets[$key] = array_values( array_merge( $market_data, $tmp_market ) );
			}
		}
		return $markets;
	}

	protected function get_market( $markets, $key ) {
		$market_data = $tmp_market = array();
		foreach( $markets as $market ) {
			$str_date = "";
			$datetime = explode(" ", trim($this->command_result->ev->attributes()['date']));
			if ( count( $datetime) > 1 ) {
				$date = isset($datetime[0]) ? str_replace("/", "-", $datetime[0] . "-" . date('Y')) : "";
				$time = isset($datetime[1]) ? $datetime[1] . ":00" : "";
				$str_date = date("Y-m-d H:i:s", strtotime("$date $time"));
			}

			if ( ! isset( $market->attributes()['url'] ) ) {
				if ( ! array_key_exists( $key, $tmp_market ) ) {
					$tmp_market[$key] = array(
						'name'			 => "Match odds",
						'start_date_gmt' => convert_to_gmt_date( $str_date, 'GMT+2' ),
						'market_type'	 => "MATCH_ODDS",
						'end_date_gmt'	 => null,
						'participants'	 => array(),
                        'meta' => array(
                            'market_id' => sprintf( "%u", crc32( "Match odds" ) )
                        )
					);
				}

				preg_match( '#\((.*?)\)#', (string) $market, $handicap );
				array_push(
					$tmp_market[$key]['participants'],
					array(
						'name'		 => (string) $market,
						'handicap'	 => empty( $handicap[1] ) ? 0 : $handicap[1],
						'price_data' => (string) $market->attributes()['cf'],
						'status'	 => null,
                        'meta' => array(
                            'participant_id' => sprintf( "%u", crc32( (string) $market ) )
                        )
					)
				);
			}
			else {
				$i = preg_replace( '/[^A-Za-z0-9]/', '', (string) $market->attributes()['url'] );
				$market_type = $this->get_market_type( trim( $market->__toString() ) );

				$market_data[$i] = array(
				    'name' => (string) $market,
					'market_type' => $market_type,
					'start_date_gmt' => convert_to_gmt_date( $str_date, 'GMT+2' ),
					'end_date_gmt' => null,
					'participants' => null,
                    'meta' => array(
                        'market_id' => sprintf( "%u", crc32( (string) $market ) )
                    )
				);
			}
		}
		return array_merge( $market_data, $tmp_market );
	}

	protected function get_market_type( $market_name = "" ) {
		$market_name = empty( $market_name ) ? "Match odds" : $market_name;

		switch ( $market_name ) {
			case "Все исходы":
				$market_type = "ALL_OUTCOMES";
				break;
			case "Чистая победа":
				$market_type = "CLEAR_VICTORY";
				break;
			case "Гандикап":
				$market_type = "HANDICAP";
				break;
			case "Тотал":
				$market_type = "TOTAL";
				break;
			case "Инд. тотал":
				$market_type = "INDUS_TOTALS";
				break;
			case "Тотал чет-нечет":
				$market_type = "TOTAL_ODD_EVEN";
				break;
			case "1-я четверть":
				$market_type = "1ST_QUARTER";
				break;
			case "2-я четверть":
				$market_type = "2ND_QUARTER";
				break;
			case "3-я четверть":
				$market_type = "3RD_QUARTER";
				break;
			case "4-я четверть":
				$market_type = "4TH_QUARTER";
				break;
			case "Первая половина":
				$market_type = "1ST_QUARTER";
				break;
			case "Двойной шанс":
				$market_type = "DOUBLE_CHANCE";
				break;
			case "1-й тайм":
				$market_type = "1ST_HALF";
				break;
			case "2-й тайм":
				$market_type = "2ND_HALF";
				break;
			case "Счет матча":
				$market_type = "SCORE";
				break;
			case "Тайм/Матч":
				$market_type = "HALFTIME_FULLTIME";
				break;
			case "Голы в таймах":
				$market_type = "HALF_TIME_GOALS";
				break;
			case "Забьет ли команда гол":
				$market_type = "WILL_TEAM_GOAL";
				break;
			case "Первый гол":
				$market_type = "FIRST_GOAL";
				break;
			case "Второй гол":
				$market_type = "THE_SECOND_GOAL";
				break;
			case "Последний гол":
				$market_type = "THE_LAST_GOAL";
				break;
			case "Тотал матча":
				$market_type = "TOTAL_MATCH";
				break;
			case "Результат команд":
				$market_type = "THE_OUTPUT_OF_COMMANDS";
				break;
			case "Угловые, пенальти, удаления":
				$market_type = "CORNERS_PENALTIES_REMOVAL";
				break;
			case "Показатели игроков":
				$market_type = "INDICATORS_PLAYERS";
				break;
			case "Результативность периодов":
				$market_type = "THE_PERFORMANCE_PERIODS";
				break;
			case "Проходы":
				$market_type = "PASSES";
				break;
			case "6.50 Победа 1":
				$market_type = "6_50_VICTORY_1";
				break;
			case "5.00 Ничья ":
				$market_type = "5_00_DRAW";
				break;
			case "1.33 Победа 2":
				$market_type = "1_33_VICTORY_2";
				break;
			case "2.30 Победа 2":
				$market_type = "2_30_Victory_2";
				break;
			case "3.00 Победа 2":
				$market_type = "3_00_Victory_2";
				break;
			default:
				$market_type = "MATCH_ODDS";
		}
		return $market_type;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param $requests
	 * @return Array Returns array of participants
	 * @throws Exception if market is not specified
	 */
	protected function extract_participants_data( $requests ) {
		$participants = array();
		foreach ( $requests as $request ) {
			$results = curl_multi( $request, true );

			foreach ( $results as $key => $result ) {
				$this->command_result = simplexml_load_string( $result );

				if ( ! isset( $this->command_result->ev->item ) )
					continue;

				$data = array();
				foreach ( $this->command_result->ev->item as $participant ) {
					preg_match( '#\((.*?)\)#', (string) $participant, $handicap );
					$data[] = array(
						'name' => (string) $participant,
						'handicap' => empty( $handicap[1] ) ? 0 : $handicap[1],
						'price_data' => (string) $participant->attributes()['cf'],
						'status' => null,
                        'meta' => array(
                            'participant_id' => sprintf( "%u", crc32( (string) $participant ) )
						)
					);
				}
				$participants[$key] = $data;
			}
		}
		return $participants;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return Array Returns array( $class_id => $class_name, ... )
	 */
	function get_sports() {
		$params = array();
		$params['sk'] = '';

		$this->send_command( $params );
		$data = array();
		foreach ( $this->command_result->children() as $response ) {
			parse_str( (string) $response->attributes()['url'], $params );
			$data[$params['sk']] = (string) $response;
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
		$params['sk'] = $sport_id;

		$this->send_command( $params );
		$data = array();

		foreach ( $this->command_result->sport->item as $item ) {
			parse_str( (string) $item->attributes()['url'], $params );
			$data[$params['gr']] = (string) $item;
		}

		if ( empty( $data ) )
			throw new Exception( "No leagues matches with the sport id $sport_id." );

		return $data;
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
	function get_events( $sport_id = null, $last_poll = null, $is_live = null ) {
		$params = $requests = $results = array();
		$last_poll = is_null( $last_poll ) ? time() : $last_poll;

		if ( $is_live ) {
			$this->apiServer = "http://www.parimatch.com/mb2/livebets.xml";
			$results = curl_multi( array( $this->apiServer ), false );

			foreach ( $results as $result ) {
				$this->command_result = simplexml_load_string( $result );

				foreach ( $this->command_result->item as $item ) {
					$requests[] = $this->apiServer . "?" . $item->attributes()['url'];
				}
			}
			$data = $this->process_live_events_data( $requests );
		}
		else {
			$params['sk'] = $sport_id;

			$this->send_command( $params );
			foreach ( $this->command_result->sport->item as $league ) {
				parse_str( (string) $league->attributes()['url'], $params );
				$requests[] = $this->apiServer . "?" . $league->attributes()['url'];
			}

			$requests = array_chunk( $requests, 10, true );
			$data = $this->extract_events_data( $requests );

			$data = array_values( $data );
		}
		if ( empty( $data ) )
			throw new Exception( "No data retrieved." );

		return array( 'last_poll' => $last_poll, 'events' => $data );
	}

	protected function process_live_events_data( $requests ) {
		$results = curl_multi( $requests );
		$data = $event_request = array();

		foreach ( $results as $result ) {
			$this->command_result = simplexml_load_string( $result );

			foreach ( $this->command_result->sport->gr as $league ) {
				$league_id = sprintf( "%u", crc32( (string) $league->attributes()['name'] ) );
				foreach ( $league->item as $event ) {
					if ( !isset( $event->attributes()['url'] ) )
						continue;

					$event_request[] = $this->apiServer . "?" . $event->attributes()['url'];
				}
			}
		}

		$event_request = array_chunk( $event_request, 10, true );
		$data = $this->extract_live_events_data( $event_request );

		return $data;
	}

	protected function extract_live_events_data( $requests ) {
		$data = array();
		foreach ( $requests as $request ) {
			$results = curl_multi( $request );

			foreach ( $results as $key => $result ) {
				$this->command_result = simplexml_load_string( $result );
				foreach ( $this->command_result->ev as $event ) {
					$start_date = date("Y-m-d") . " " . trim( $event->attributes()['date'] );
					$gmt_start_date = convert_to_gmt_date( $start_date, 'GMT+2' );

					$data[] = array(
						'name' => (string) $event->attributes()['name'],
						'start_date_gmt' => $gmt_start_date,
						'end_date_gmt' => null,
						'is_live' => 1,
						'status' => null,
						'meta' => array(
							'event_id' => str_replace( 'li', '', $key ),
							'tree_id' => null,
							'league_id' => null
						),
						'markets' => array(
							'name'			 => "All Outcome",
							'start_date_gmt' => $gmt_start_date,
							'market_type'	 => "ALL_OUTCOME",
							'end_date_gmt'	 => null,
							'participants'	 => $this->extract_live_participants_data( $event->item ),
							'meta' => array(
								'market_id' => sprintf( "%u", crc32( "All Outcome" ) )
							)
						)
					);
				}
			}
		}
		return $data;
	}

	protected function extract_live_participants_data( $participants ) {
		$data = array();
		foreach ( $participants as $participant ) {
			preg_match( '#\((.*?)\)#', (string) $participant, $handicap );
			$data[] = array(
				'name' => (string) $participant,
				'handicap' => empty( $handicap[1] ) ? 0 : $handicap[1],
				'price_data' => (string) $participant->attributes()['cf'],
				'status' => null,
				'meta' => array(
					'participant_id' => sprintf( "%u", crc32( (string) $participant ) )
				)
			);
		}
		return $data;
	}

	public function garbage_collect() {
		curl_multi_garbage_collect();
	}

	public function get_results( $vent_id ) {}
}