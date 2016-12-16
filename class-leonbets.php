<?php

class Leonbets extends Bookmakers {
	protected $log_file		= '/tmp/leonbets.log';

	function __construct() {
		libxml_use_internal_errors( true );

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: www.leonbets.net\r\n"
				)
			)
		);

		$this->apiServer = 'https://www.leonbets.net/sportlinexmlall';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url = $this->apiServer;

		if ( empty( $params ) )
			throw new Exception( 'Unknown command' );

		if ( defined( 'USE_LOCAL_DATA_FILES' ) )
			$this->command = __DIR__ . '/data/leon-bets-doc.txt';
		else
			$this->command = $url . "?" . http_build_query( $params );
	}

	// TODO: switch to English
	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		$this->prepare_command( $params );
		$this->log_action( "New command: {$this->command} from " . debug_backtrace_summary() );

		$result = file_get_contents( $this->command, false, $this->ctx );
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
	 * @param string $events
	 * @param int $league_id
	 * @param int $is_live
	 * @return array Returns array of events
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_events_data( $events, $league_id, $is_live = null ) {
		if ( empty( $events ) || empty( $league_id ) )
			throw new Exception( 'Please enter the required parameters!' );

		$events_data = array();
		foreach ( $events as $event ) {
			$start_date = str_replace( "/", "-", "{$event->attributes()['kt']}:00" );
			$event_id = (string) $event->attributes()['n'];
			if ( empty( $event_id ) )
				$event_id = sprintf( "%u", crc32( (string) $event->es->ey[3]->attributes()['v'] ) );

			$events_data[] = array(
				'name'			 => (string) $event->es->ey[3]->attributes()['v'],
				'start_date_gmt' => convert_to_gmt_date( $start_date, 'GMT+2' ),
				'end_date_gmt'	 => null,
				'is_live'		 => is_null( $is_live ) ? 0 : 1,
				'status'		 => null,
				'meta' => array(
					'event_id'	=> $event_id,
					'tree_id'	=> null,
					'league_id' => $league_id
				),
				'markets' => $this->extract_markets_data( $event->m, $start_date )
			);
		}

		return $events_data;
	}

	/**
	 * Extract markets data
	 *
	 * @since 1.0.0
	 * @param string $markets
	 * @return array Returns array of event markets
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_markets_data( $markets, $start_date ) {
		if ( empty( $markets ) )
			throw new Exception( 'Please enter the required parameter!' );

		$market_data = array();
		foreach ( $markets as $key => $market ) {
			$market_type   = preg_replace( '/[^a-zA-Z0-9\s]/i', '', (string) $market->ms->my[3]->attributes()['o'] );
			$market_type   = strtoupper( preg_replace( '!\s+!', '_', $market_type ) );
			$market_data[] = array(
				'name'			 => (string) $market->ms->my[3]->attributes()['v'],
				'market_type'	 => $market_type,
				'start_date_gmt' => convert_to_gmt_date( $start_date, 'GMT+2'),
				'end_date_gmt'	 => null,
				'participants'	 => $this->extract_participants_data( $market, $market_type ),
                'meta' => array(
                    'market_id' => sprintf( "%u", crc32( (string) $market->ms->my[3]->attributes()['v'] ) )
                )
			);
		}

		return $market_data;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param string $market
	 * @param string $market_type
	 * @return array Returns array of event markets
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_participants_data( $market, $market_type ) {
		if ( empty( $market ) )
			throw new Exception( 'Please enter the required parameter!' );

		$participant_data = array();
		foreach ( $market->r as $participant ) {
			$handicap	= $this->get_handicap( $market_type, (string) $participant->rs->ry[3]->attributes()['v'] );
			$price_data = (string) $participant->attributes()['up'];

			$participant_data[] = array(
				'name'		 => (string) $participant->rs->ry[3]->attributes()['v'],
				'handicap'	 => $handicap,
				'price_data' => $price_data,
				'status'	 => null,
                'meta' => array(
                    'participant_id' => sprintf( "%u", crc32( (string) $participant->rs->ry[3]->attributes()['v'] ) )
                )
			);
		}
		return $participant_data;
	}

	protected function get_handicap( $market_type, $participant ) {
		preg_match_all('!\d+(?:\.\d+)?!', $participant, $matches );
		$float = array_map('floatval', $matches[0]);
		$handicap = empty( $float ) ? 0 : $float[0];

		switch ( $market_type ) {
			case stripos( $market_type, 'TOTAL_NUMBER_OF_SETS_BEST_OF') !== false :
			case stripos( $market_type, 'CORRECT_SET_SCORE') !== false :
			case stripos( $market_type, 'CORRECT_SCORE') !== false :
			case stripos( $market_type, 'SET_WINNER') !== false :
				$handicap = 0;
				break;
			case stripos( $market_type, 'IRST_HALF_GOALS') !== false :
			case stripos( $market_type, 'OVERUNDER') !== false :
			case stripos( $market_type, 'TOTAL') !== false :
				if ( strpos( $participant, "Over" ) !== false )
					$handicap = "+$handicap";
				else
					$handicap = "-$handicap";
				break;
		}
		return $handicap;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return array returns array of ( $bookmaker_league_id => $league_name, ... ).
	 */
	public function get_sports() {
		$params = $sports = array();
		$params['login'] = 'leon';
		$params['pwd'] = 'bets';

		$this->send_command( $params );
		foreach ( $this->command_result->body->ss->sp as $sport ) {
			$id = sprintf( "%u", crc32( $sport->sy->sn[3]->attributes()['v'] ) );
			$sports[$id] = (string) $sport->sy->sn[3]->attributes()['v'];
		}
		return $sports;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param int $sport_id
	 * @return array Returns array of leagues belongs to the sport_id
	 * @throws Exception If sport_id is empty or no leagues matches with the sport_id
	 */
	public function get_leagues( $sport_id ) {
		if ( empty( $sport_id ) )
			throw new Exception( 'sport_id is required' );

		$params = $leagues = array();
		$params['login'] = 'leon';
		$params['pwd'] = 'bets';

		$this->send_command( $params );
		foreach ( $this->command_result->body->ss->sp as $sport ) {
			$id = sprintf( "%u", crc32( $sport->sy->sn[3]->attributes()['v'] ) );

			if ( $sport_id != $id )
				continue;

			foreach( $sport->l as $league ) {
				$id = sprintf( "%u", crc32( $league->ls->ly[3]->attributes()['v'] ) );
				$leagues[$id] = (string) $league->ls->ly[3]->attributes()['v'];
			}
		}

		return $leagues;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param int $sport_id
	 * @param int $last_poll
	 * @param int $is_live
	 * @return array Returns array of events belongs to the sport_id
	 * @throws Exception If sport_id is empty or no events found thought sport_id
	 */
	public function get_events( $sport_id, $last_poll = null, $is_live = null )	{
		$params = $events = array();
		$params['login'] = 'leon';
		$params['pwd'] = 'bets';

		$last_poll = is_null( $last_poll ) ? time() : $last_poll;

		$this->send_command( $params );
		foreach ( $this->command_result->body->ss->sp as $sport ) {
			$id = sprintf( "%u", crc32( $sport->sy->sn[3]->attributes()['v'] ) );

			if ( $sport_id && $sport_id != $id )
				continue;

			foreach( $sport->l as $league ) {
				$league_id = sprintf( "%u", crc32( $league->attributes()['n'] ) );

				$events = array_merge(
					$events,
					$this->extract_events_data( $league->e, $league_id, $is_live )
				);
			}
		}

		return array( 'last_poll' => $last_poll, 'events' => $events );
	}

	public function get_results( $event_id ) {
		return false;
	}
}