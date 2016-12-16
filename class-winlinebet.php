<?php

class Winlinebet extends Bookmakers {
	protected $log_file		= '/tmp/winline.log';
	private   $cache		= array();

	function __construct() {
		libxml_use_internal_errors( true );

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: gw.winlinebet.com\r\n"
				)
			)
		);

		$this->apiServer = 'http://gw.winlinebet.com:1072';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url = $this->apiServer;

		if ( empty( $params ) )
			throw new Exception( 'Unknown command' );

		if ( defined( 'USE_LOCAL_DATA_FILES' ) )
			$this->command = __DIR__ . '/data/winlinebet-prematch.xml';
		else
			$this->command = $url . "?" . http_build_query( $params );
	}

	// TODO: switch to English
	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		if ( ! empty( $this->cache[$params['live']] ) ) {
			$this->command_result = $this->cache[$params['live']];
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
		$this->cache[$params['live']] = $this->command_result;
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
	 * @param int $sport_id
	 * @return array Returns array of events
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_events_data( $events, $league_id ) {
		if ( empty( $events ) || empty( $league_id ) )
			throw new Exception( 'Required parameters are empty!' );

		$events_data = array();
		foreach ( $events as $event ) {
			$event_name = "{$event->attributes()['Team1']} vs {$event->attributes()['Team2']}";
			$start_date = substr( str_replace('T', ' ', (string) $event->attributes()['MatchDate'] ), 0, 19);
			$events_data[] = array(
				'name'			 => $event_name,
				'start_date_gmt' => convert_to_gmt_date( $start_date, 'GMT+3'),
				'end_date_gmt'	 => null,
				'is_live'		 => (string) $event->attributes()['islive'],
				'status'		 => null,
				'meta' => array(
					'event_id'	=> sprintf( "%u", crc32( "{$league_id}:{$event_name}" ) ),
					'tree_id'	=> null,
					'league_id' => $league_id
				),
				'markets' => $this->extract_markets_data( $event, $start_date )
			);
		}

		return $events_data;
	}

	/**
	 * Extract markets data
	 *
	 * @since 1.0.0
	 * @param string $markets
	 * @param string $start_date
	 * @return array Returns array of event markets
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_markets_data( $markets, $start_date ) {
		if ( empty( $markets ) )
			throw new Exception( 'Required parameter is empty!' );

		$market_data = array();
		foreach ( $markets as $market ) {
			$market_type   = preg_replace( '/[^a-zA-Z0-9\s]/i', '', (string) $market->attributes()['freetext'] );
			$market_type   = strtoupper( preg_replace( '!\s+!', '_', $market_type ) );
			$market_data[] = array(
				'name'			 => (string) $market->attributes()['freetext'],
				'market_type'	 => $market_type,
				'start_date_gmt' => convert_to_gmt_date( $start_date, 'GMT+3'),
				'end_date_gmt'	 => null,
				'participants'	 => $this->extract_participants_data( $market ),
				'meta' => array(
                    'market_id' => sprintf( "%u", crc32( (string) $market->attributes()['freetext'] ) )
                )
			);
		}

		return $market_data;
	}

	/**
	 * Extract participants data
	 *
	 * @since 1.0.0
	 * @param string $participants
	 * @return array Returns array of event markets
	 * @throws Exception If required parameters are not filled
	 */
	protected function extract_participants_data( $participants ) {
		$i = 1;
		$participant_data = array();
		$handicap = isset( $participants->attributes()['value'] ) ? (string) $participants->attributes()['value'] : 0;

		while ( true ) {
			$participant_name = (string) $participants->attributes()["name$i"];
			if ( empty( $participant_name ) )
				break;

			$participant_data[] = array(
				'name'		 => $participant_name,
				'handicap'	 => $handicap,
				'price_data' => "{$participants->attributes()["odd$i"]}",
				'status'	 => null,
				'meta' => array(
                    'participant_id' => sprintf( "%u", crc32( (string) $participants->attributes()["name$i"] ) )
                )

			 );
			$i++;
		}
		return $participant_data;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return array returns array of ( $bookmaker_league_id => $league_name, ... ).
	 */
	public function get_sports() {
		$params = array();
		$params['live'] = 5;
		$this->send_command( $params );

		$sports = array();

		foreach ( $this->command_result->children() as $sport ) {
			$id = sprintf( "%u", crc32( (string) $sport->attributes()['Name'] ) );
			$sports[$id] = (string) $sport->attributes()['Name'];
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

		$params = array();
		$params['live'] = 5;
		$this->send_command( $params );

		$leagues = array();
		foreach ( $this->command_result->children() as $sport ) {
			$id = sprintf( "%u", crc32( (string) $sport->attributes()['Name'] ) );

			if ( $sport_id != $id )
				continue;

			foreach ( $sport as $country ) {
				foreach ( $country->Tournament as $league ) {
					$id = sprintf( "%u", crc32( (string) $league->attributes()['Name'] ) );
					$leagues[$id] = (string) $league->attributes()['Name'];
				}
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
		$params = array();
		$params['live'] = 5;
		if ( $is_live )
			$params['live'] = 4;

        $last_poll = is_null( $last_poll ) ? time() : $last_poll;

		$this->send_command( $params );
		$events = array();

		foreach ( $this->command_result->children() as $sport ) {
			$id = sprintf( "%u", crc32( (string) $sport->attributes()['Name'] ) );

			if ( $sport_id && $sport_id != $id )
				continue;

			foreach ( $sport as $country ) {
				foreach ( $country->Tournament as $league ) {
					$league_id = sprintf( "%u", crc32( (string) $league->attributes()['Name'] ) );
					$events = array_merge(
						$events,
						$this->extract_events_data( $league, $league_id )
					);
				}
			}
		}

        return array( 'last_poll' => $last_poll, 'events' => $events );
	}

	public function get_results( $event_id ) {
		return false;
	}

	/**
	 * Garbage collect
	 * Clear the cache once called or executed.
	 *
	 * @since 1.0.0
	 */
	public function garbage_collect() {
		foreach ( $this->cache as $key => $value )
			$this->cache[$key] = null;
	}
}