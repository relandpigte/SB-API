<?php

class Marathonbet extends Bookmakers {
	protected $log_file		  = '/tmp/marathonbet.log';
	private   $cache		  = array();

	function __construct() {
		libxml_use_internal_errors( true );

		$this->ctx = stream_context_create(
			array(
				'http' => array(
					'timeout' => MAX_TIMEOUT,
					'header'  => "Host: livefeed.marathonbet.com\r\n"
				)
			)
		);

		if ( defined( 'USE_LOCAL_DATA_FILES' ) )
			$this->apiServer = __DIR__ . "/data";
		else
			$this->apiServer = 'http://livefeed.marathonbet.com';

		parent::__construct();
	}

	protected function prepare_command( $params ) {
		$url = $this->apiServer;

		switch( $params['command'] ) {
			case 'live_feed':
				$filename = 'socialbet_line_live_en.xml';
				break;
			case 'results':
				$filename = 'results/socialbet_en';
				$url = 'http://livefeeds.marathonbet.com';
				break;
			case 'feed':
				$filename = 'socialbet_line_en.xml';
				break;
		}
		
		if ( empty( $filename ) )
			throw new Exception( 'Unknown command' );

		$this->command = "{$url}/$filename";
	}

	// TODO: switch to English
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

	protected function extract_events_data( $events, $league_id ) {
		$events_data = array();

		foreach ( $events as $event ) {
			$event_data = (array) $event;
			$tournament = (array) $event_data['Tournament'];
			if ( isset( $tournament['@attributes'] ) )
				$tournament = $tournament['@attributes'];

			$match_info = (array) $event_data['MatchInfo'];
			if ( isset( $match_info['@attributes'] ) )
				$match_info = $match_info['@attributes'];

			$end_date	= ''; // We cannot fetch this from results, as the results has data for past events only
			$markets	= $this->extract_markets_data( $event_data['Markets'],$event_data['Date'], $end_date );

			$events_data[] = array(
				'name'			 => $tournament['name'],
				'start_date_gmt' => convert_to_gmt_date( $event_data['Date'], 'EST'),
				'end_date_gmt'	 => $end_date,
				'is_live'		 => ( $tournament['isLive'] == 'true' ) ? 1 : 0,
				# EVENT_BETTING_OPEN, EVENT_BETTING_INACTIVE, EVENT_BETTING_SUSPENDED,
				# EVENT_BETTING_CLOSED, EVENT_BETTING_LINES_HAVE_LOWER_LIMIT
				'status'		 => null,
				'meta' => array(
					'event_id'	=> $match_info['id'],
					'tree_id'	=> $match_info['treeId'],
					'league_id' => $league_id
				),
				'markets' => $markets
			);
		}

		return $events_data;
	}

	protected function extract_markets_data( $markets, $start_date, $end_date ) {
		$results = array();

		foreach ( $markets as $market ) {
			$market		  = (array) $market;
			$participants = array();

			foreach ( $market as $key => $val ) {
				if ( $key == '@attributes' )
					continue;

				$participant_info = (array) $val;
				$participants = $this->extract_participants_data( $participant_info, $key );
			}

			if ( isset( $market['@attributes'] ) )
				$market = $market['@attributes'];

			$market_name = $market['name'];

			if ( isset( $results[$market_name] ) ) {
				$results[$market_name]['participants'] = array_merge( $results[$market_name]['participants'], $participants );
				continue;
			}

			$results[$market_name] = array(
				'name'			 => $market['name'],
				'market_type'	 => $market['model'],
				'start_date_gmt' => convert_to_gmt_date( $start_date, 'EST'),
				'end_date_gmt'	 => $end_date,
				'participants'	 => $participants,
				'meta' => array(
					'market_id' => sprintf( "%u", crc32( $market['name'] ) )
				)
			);
		}

		return array_values( $results );
	}

	protected function extract_participants_data( $participants, $key = '' ) {
		$results = array();

		foreach ( $participants as $p ) {
			$p_info = (array) $p;
			if ( isset( $p_info['@attributes'] ) )
				$p_info = $p_info['@attributes'];

			$args = array(
				'key'	 => $key,
				'value'  => isset( $p_info['value'] ) ? $p_info['value'] : null,
				'value1' => isset( $p_info['value1'] ) ? $p_info['value1'] : null,
				'value2' => isset( $p_info['value2'] ) ? $p_info['value2'] : null,
				'totalRelation' => isset( $p_info['totalRelation'] ) ?
					$p_info['totalRelation'] : null,
			);

			$handicap = $this->get_handicap( $args );
			$price	  = isset( $p_info['price'] ) ? $p_info['price'] : "";
			$name	  = isset( $p_info['name'] ) ? $p_info['name'] : "";

			$results[] = array(
				'name'		 => $name,
				'handicap'	 => $handicap,
				'price_data' => $price,
				'status'	 => 'ACTIVE', # ACTIVE, WINNER, LOSER, REMOVED_VACANT, REMOVED, HIDDEN
				'meta' => array(
					'participant_id' => sprintf( "%u", crc32( $name ) )
				)
			);
		}
		return $results;
	}

	protected function get_handicap( $args ) {
		if ( empty( $args ) || ! is_array( $args ) )
			return 0;
		$handicap = 0;

		switch ( $args['key'] ) {
			case "Total":
				$values   = explode("_", $args['value']);
				if ( count( $values ) > 1 )
					$handicap = $values[0] == 'Over' ? "+{$values[1]}" : "-{$values[1]}";
				break;
			case "Handicap":
				$handicap = $args['value'];
				break;
			case "AsianHandicap":
				$handicap = "{$args['value1']}, {$args['value2']}";
				break;
			case "AsianTotal":
				$handicap = $args['totalRelation'] == 'Over' ?
					"+{$args['value1']}, +{$args['value2']}" :
					"-{$args['value1']}, -{$args['value2']}";
				break;
		}

		return $handicap;
	}

	/**
	 * Get Sports
	 *
	 * @since 1.0.0
	 * @return Array( $bookmaker_league_id => $league_name, ... ), False if no data to be retrieve.
	 */
	public function get_sports() {
		$params = array();
		$params['command'] = 'feed';
		$this->send_command( $params );

		$data = array();
		foreach ( $this->command_result->children() as $child )
			$data[(string) $child->attributes()['id']] = (string) $child->attributes()['name'];

		return $data;
	}

	public function get_leagues( $sport_id ) {
		if ( empty( $sport_id ) )
			throw new Exception( 'sport_id is required' );

		$params = array();
		$params['command'] = 'feed';
		$this->send_command( $params );

		$data = array();
		foreach ( $this->command_result->children() as $child ) {
			if ( $sport_id != $child->attributes()['id'] )
				continue;

			foreach ( $child as $key => $value )
				$data[(string) $value->attributes()['id']] = (string) $value->attributes()['name'];
		}

		return $data;
	}

	public function get_events( $sport_id, $last_poll = null, $is_live = null )	{
		$params = array();
		$params['command'] = 'feed';
		if ( $is_live )
			$params['command'] = 'live_feed';

		$last_poll = time();

		$this->send_command( $params );

		$data = $result = array();
		foreach ( $this->command_result->children() as $child ) {
			if ( $sport_id && $sport_id != $child->attributes()['id'] )
				continue;

			foreach ( $child->Category as $category ) {
				$league = (array) $category;
				if ( isset( $league['@attributes'] ) )
					$league = $league['@attributes'];

				$data = array_merge(
					$data,
					$this->extract_events_data( $category->Events->Event, $league['id'] )
				);
			}
		}

		return array( 'last_poll' => $last_poll, 'events' => $data );
	}

	public function get_results( $event_id ) {
		$params = array();
		$params['command'] = 'results';
		$this->send_command( $params );

		$PSport = $this->command_result->sports->children();

		$result = array();
		foreach ( $PSport as $groups ) {
			foreach ( $groups->Groups->children() as $PGroup ) {
				foreach ( $PGroup->children() as $event ) {
					foreach ( $event->children() as $PEvent ) {
						$details	= (array) $PEvent->attributes();
						$attribute	= $details['@attributes'];
						$score_data = array();

						if ( $attribute['eventId'] != $event_id )
							continue;

						if ( isset( $PEvent->scores ) ) {
							foreach( $PEvent->scores as $scores ) {
								$score = (array) $scores->PScore;
								$score_data = $score['@attributes'];
							}
						}
						$attribute['scores'] = ( ! empty( $score_data ) ) ? $score_data : null;
						$result[] = $attribute;
					}
				}
			}
		}

		return $result;
	}

	public function garbage_collect() {
		$this->cache['feed'] = null;
		$this->cache['results'] = null;
		$this->cache['live_feed'] = null;
	}
}