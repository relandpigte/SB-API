<?php

class Ligastavok extends Bookmakers {
	protected $log_file = '/tmp/ligastavok.log';
	private   $cache	= array();

	function __construct() {

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: liga-stavok.com\r\n"
				)
			)
		);

		$this->apiServer = 'http://m.svc.liga-stavok.com';
		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url  = $this->apiServer;

		switch ( $params['command'] ) {
			case 'feed':
				$path = "/PrematchService3.0/JSONF?filter=0";
				break;
			case 'live_feed':
				$path = "/LinesService2.5/JSONF?filter=0";
				$url  = "http://svc.liga-stavok.com/";
				break;
		}

		if ( defined( 'USE_LOCAL_DATA_FILES' ) )
			$this->command = __DIR__ . "/data/ligastavok-prematch.json";
		else
			$this->command = $url . $path;
	}

	protected function send_command( $params = array() ) {
		$this->command = '';
		$this->command_result = '';

		if ( ! empty( $this->cache[$params['command']] ) ) {
			$this->command_result = $this->cache[$params['command']];
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
		$this->cache[$params['command']] = $this->command_result;
		$this->log_action( $this->command );
	}

	protected function parse_command_result( $result ) {
		$result = json_decode( $result, TRUE );

		if ( ! $result ) {
			$this->log_action( $this->command . "\n" . "Invalid JSON format" );
			throw new Exception( $this->command . "\n" . "Invalid JSON format" );
		}

		$this->command_result = $result;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return Array Returns array( $class_id => $class_name, ... )
	 */
	function get_sports() {
		$params = $data = array();
		$params['command'] = 'feed';

		$this->send_command( $params );

		$data = array();
		foreach ( $this->command_result['nsub'] as $sports ) {
			if( isset( $sports['Id'] ) && isset( $sports['Name'] ) && $sports['ttl'] == 'SType' )
				$data[$sports['Id']] = $sports['Name'];
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

		$params = $data = array();
		$params['command'] = 'feed';

		$this->send_command( $params );

		$data = array();
		foreach ( $this->command_result['nsub'] as $sports ) {
			if( isset( $sports['Id'] ) && isset( $sports['Name'] ) && $sports['Id'] == $sport_id )
				foreach ( $sports['nsub'] as $league ) {
					$data[$league['TopicId']] = $league['TopicTitle'];
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
		$params = $data = array();
		$params['command'] = 'feed';

		if ( $is_live )
			$params['command'] = 'live_feed';

        $last_poll = is_null( $last_poll ) ? time() : $last_poll;
		$this->send_command( $params );

		foreach ( $this->command_result['nsub'] as $sports ) {
			if ( ! isset( $sports['Id'] ) || ! isset( $sports['Name'] ) &&
				( isset( $sport_id ) && $sports['Id'] != $sport_id ) )
			{
				continue;
			}

			foreach ( $sports['nsub'] as $league ) {
				$league_id = $league['TopicId'];
				$team1 = isset( $league['Team1'] ) ? $league['Team1'] : "";
				$team2 = isset( $league['Team2'] ) ? " vs " . $league['Team2'] : "";

				$data[] = array(
					'name' => $team1 . $team2,
					'start_date_gmt' => convert_to_gmt_date( $league['FinBetDate'], 'GMT+2'),
					'end_date_gmt' => null,
					'is_live' => $is_live ? 1 : 0,
					'status' => null,
					'meta' => array(
						'event_id' => $league['Id'],
						'tree_id' => null,
						'league_id' => $league_id
					),
					'markets' => ( is_array( $league ) && array_key_exists( 'nsub', $league ) ) ?
					$this->extract_markets_data( $league['nsub'] ) :
					null
				);
			}
		}

        return array( 'last_poll' => $last_poll, 'events' => $data );
	}

	protected function extract_markets_data( $markets ) {
		$data = array();

		foreach ( $markets as $market ) {
			if ( $market['ttl'] != "Block" || ! isset( $market['nsub'] ) )
				continue;

			foreach ( $market['nsub'] as $m ) {
				$data[] = array(
					'name'			 => isset( $market['Part'] ) ? "{$m['Title']} - {$market['Part']}" : $market['Title'],
					'market_type'	 => $m['Type'],
					'start_date_gmt' => null,
					'end_date_gmt'	 => null,
					'participants'	 => ( is_array( $m ) && array_key_exists( 'nsub', $m ) ) ?
						$this->extract_participants_data( $m['nsub'] ) :
						null,
					'meta' => array(
                        'market_id' => $market['Id']
                    )
				);
			}
		}

		return $data;
	}

	protected function extract_participants_data( $participants ) {
		$data = array();
		foreach ( $participants as $participant ) {
			if ( $participant['ttl'] != "Outcome" )
				continue;

			$data[] = array(
				'name'		 => isset( $participant['Name'] ) ? $participant['Name'] : $participant['Title'],
				'handicap'	 => isset( $participant['AdValue'] ) ? $participant['AdValue'] : 0,
				'price_data' => $participant['Value'],
				'status'	 => null,
				'meta'       => array(
                    'participant_id' => $participant['Id']
                )
			);
		}

		return $data;
	}

	public function get_results( $vent_id ) {}

	public function garbage_collect() {
		foreach ( $this->cache as $key => $cache ) {
			$this->cache[$key] = null;
		}
	}
}