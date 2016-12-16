<?php

define( 'USE_LOCAL_DATA_FILES', true );
define( 'LOG_LEVEL', LOG_DEBUG );
define( 'LOG_FILE', '/tmp/sb-api.log' );

define( 'MAX_TIMEOUT', 10 );

ini_set( 'display_errors' , 1 );
ini_set( 'error_reporting', E_ALL );
ini_set( 'default_socket_timeout', MAX_TIMEOUT );
ini_set( 'memory_limit', '512M' );

if ( LOG_DEBUG === LOG_LEVEL )
	define( 'DEBUG', true );
else
	define( 'DEBUG', false );

abstract class Bookmakers {

	protected $event_status_map = array(
		'EVENT_BETTING_OPEN'                   => 0,
		'EVENT_BETTING_INACTIVE'               => 1,
		'EVENT_BETTING_SUSPENDED'              => 2,
		'EVENT_BETTING_CLOSED'                 => 3,
		'EVENT_BETTING_LINES_HAVE_LOWER_LIMIT' => 4,
	);

	protected $participant_flags_map = array(
		'PARTICIPANT_HOME_TEAM'  => 0,
		'PARTICIPANT_AWAY_TEAM'  => 1,
		'PARTICIPANT_TEAM1'      => 2,
		'PARTICIPANT_TEAM2'      => 4,
	);

	protected $price_types_maps = array(
		'PRICE_SPREAD'            => 0,
		'PRICE_TOTAL_POINTS'      => 1,
		'PRICE_MONEY_LINE'        => 2,
		'PRICE_TEAM_TOTAL_POINTS' => 4,
	);

	protected $market_types = array(
		'MATCH_ODDS', 'OVER_UNDER_25', 'CORRECT_SCORE', 'OVER_UNDER_45', 'OVER_UNDER_35', 'OVER_UNDER_15', 'OVER_UNDER_05', 'BOTH_TEAMS_TO_SCORE', 'HALF_TIME', 'HALF_TIME_SCORE', 'OVER_UNDER_55', 'OVER_UNDER_65', 'HALF_TIME_FULL_TIME', 'DRAW_NO_BET', 'NEXT_GOAL', 'ASIAN_HANDICAP', 'TEAM_B_2', 'TEAM_B_1', 'TEAM_A_2', 'TEAM_A_1', 'FIRST_HALF_GOALS_15', 'FIRST_HALF_GOALS_05', 'DOUBLE_CHANCE', 'CORRECT_SCORE2', 'SET_WINNER', 'TEAM_B_3', 'TEAM_A_3', 'TOTAL_GOALS', 'TEAM_TOTAL_GOALS', 'OVER_UNDER_85', 'OVER_UNDER_75', 'CLEAN_SHEET', 'WIN_BOTH_HALVES', 'SET_BETTING', 'UNDIFFERENTIATED', 'SPECIAL', 'HALF_MATCH_ODDS', 'HANDICAP', 'WIN', 'PLACE', 'FORECAST', 'FIRST_GOAL_ODDS', 'ODD_OR_EVEN', 'FIRST_HALF_GOALS_25', 'TEAM_B_WIN_TO_NIL', 'TEAM_A_WIN_TO_NIL', 'SENDING_OFF', 'PENALTY_TAKEN', 'HAT_TRICKED_SCORED', 'WINNER', 'SEASON_SPECIALS', 'TO_QUALIFY', 'UNUSED', 'REGULAR_TIME_GOALS', 'OUTRIGHT_WINNER', 'MONEY_LINE', '6-0_SET', 'TOURNAMENT_WINNER', 'TIE_BREAK', 'SET_CORRECT_SCORE', 'PLAYER_B_WIN_A_SET', 'PLAYER_A_WIN_A_SET', 'NUMBER_OF_SETS', 'RUN_LINE_LISTED', 'TOTAL_GAMES', 'TOTAL_POINTS', 'INNINGS_RUNS', 'METHOD_OF_VICTORY', 'HIGHEST_OVER_TOTAL', 'TIED_MATCH', 'GAME_BY_GAME_03_13', 'GAME_BY_GAME_03_12', 'GAME_BY_GAME_03_11', 'GAME_BY_GAME_03_10', 'GAME_BY_GAME_03_09', 'GAME_BY_GAME_03_08', 'GAME_BY_GAME_03_07', 'GAME_BY_GAME_03_06', 'WINNING_MARGIN',
		'GAME_BY_GAME_03_05', 'GAME_BY_GAME_03_04', 'GAME_BY_GAME_03_03', 'GAME_BY_GAME_03_02', 'GAME_BY_GAME_03_01', 'GAME_BY_GAME_02_13', 'GAME_BY_GAME_02_12', 'GAME_BY_GAME_02_11', 'GAME_BY_GAME_02_10', 'GAME_BY_GAME_02_09', 'GAME_BY_GAME_02_08', 'GAME_BY_GAME_02_07', 'GAME_BY_GAME_02_06', 'GAME_BY_GAME_02_05', 'GAME_BY_GAME_02_04', 'GAME_BY_GAME_02_03', 'GAME_BY_GAME_02_02', 'GAME_BY_GAME_02_01', 'GAME_BY_GAME_01_13', 'GAME_BY_GAME_01_12', 'GAME_BY_GAME_01_11', 'GAME_BY_GAME_01_10', 'GAME_BY_GAME_01_09', 'GAME_BY_GAME_01_08', 'GAME_BY_GAME_01_07', 'GAME_BY_GAME_01_06', 'GAME_BY_GAME_01_05', 'GAME_BY_GAME_01_04', 'GAME_BY_GAME_01_03', 'GAME_BY_GAME_01_02', 'GAME_BY_GAME_01_01', 'COMPLETED_MATCH', 'TOTAL_SIXES', 'TOURN_MATCHBET_NOTIE', 'TOP_BATSMAN', 'HEAD_TO_HEAD', 'TO_SCORE_BOTH_HALVES', 'FIRST_TO_SCORE', '1ST_DISMISSAL_METHOD', 'TOTAL_FRAMES', 'OPENING_PARTNERSHIP', 'FIRST_TRY_ODDS', 'FIRST_SCORING_PLAY', 'TO_SCORE_50', 'TO_SCORE_100', 'TOP_N_FINISH', 'ROUND_BETTING', 'GO_THE_DISTANCE', 'FIRST_OVER_RUNS', 'BOOKING_ODDS', 'ANTEPOST_WIN', 'TO_SCORE', 'TOP_WICKETS_TAKER', 'TOP_GOALSCORER', 'OVER_UNDER_105_CORNR', 'MATCH_ODDS_AND_OU_25', 'FIRST_GOAL_SCORER', 'DAILY_GOALS', 'CORNER_ODDS', 'TOTAL_FOURS', 'SERIES_WINNER', 'PLAYER_PROGRESS', 'FIRST_TRY_SCORER', 'TOTAL_CLASS_DRIVERS', 'SESSION_RUNS', 'PRACTICE_SESSIONS', 'CENTURY_SCORED', 'AH_ODDS_MARKET', 'WIN_HALF', 'WIN_FROM_BEHIND', 'WINCAST', 'TO_SCORE_25', 'ROUND_LEADER', 'ROCK_BOTTOM', 'QUALIFYING_WINNER', 'GRAND_SLAM_SPECIALS', 'WINNING_CAR', 'TO_SCORE_HATTRICK', 'TO_SCORE_2_OR_MORE', 'TO_BE_CLASSIFIED', 'TEST_MATCH_END', 'SHOWN_A_CARD', 'SECOND_HALF_GOALS_15', 'SECOND_HALF_GOALS_05', 'SCORE_CAST', 'SAFETY_CAR', 'RACE_TO_3_GOALS', 'RACE_TO_2_GOALS', 'QUALI_WINNER_DOUBLE', 'QUALIFYING_ROUND_3', 'POINTS_FINISH', 'OVER_UNDER_85_CORNR', 'OVER_UNDER_65_CARDS', 'OVER_UNDER_55_CORNR', 'OVER_UNDER_45_CARDS', 'OVER_UNDER_25_CARDS', 'OVER_UNDER_135_CORNR', 'MATCH_ODDS_AND_BTTS', 'MAKE_THE_CUT', 'LAST_TEAM_TO_SCORE', 'HALF_WITH_MOST_GOALS', 'GOAL_BOTH_HALVES', 'FIRST_HALF_CORNERS', 'FIRST_CORNER', 'FASTEST_LAP', 'EXACT_GOALS', 'CORNER_MATCH_BET', 'BOOKING_MATCH_BET', 'BIG_V_FIELD', '2ND_HALF_MATCH_ODDS', '1ST_INNINGS_LEAD',
		'OVER_UNDER', 'TEAM_TOTAL_POINTS', // TODO: convert all Betfair stuff above to a combined OVER_UNDER
	);

	//Internal functions
	protected function get_command() {
		return $this->command;
	}

	protected function get_command_result() {
		return $this->command_result;
	}

	public function get_errors() {
		return $this->errors;
	}

	protected function log_action($text) {
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) && DEBUG )
			echo date('Y-m-d H:i:s') . '  -  ' . $text . "\n";

		file_put_contents( __DIR__ . $this->log_file, date('Y-m-d H:i:s') . '  -  ' . $text . "\n", FILE_APPEND );
	}

	function readable_market_type( $market_type ) {
		return ucwords( strtolower( str_replace( '_', ' ', $market_type ) ) );
	}

	function validate( $what, $asWhat ) {
		switch ($asWhat) {
		case 'address':
			mb_regex_encoding('utf-8');
			if (mb_eregi('^[[:alpha:]\w\d\s\.\,\#\(\)\/\-]+$', $what))
				return true;
			break;
		case 'city':
			mb_regex_encoding('utf-8');
			if (mb_eregi('^[[:alpha:]_ -]{2,30}$', $what))
				return true;
			break;
		case 'company':
			mb_regex_encoding('utf-8');
			if (mb_eregi('^[[:alpha:]a-z0-9 \/\.\(\),&_-]{2,255}$', $what))
				return true;
			break;
		case 'country':
			if (preg_match('/^[a-z]{2}$/i', $what))
				return true;
			break;
		case 'date':
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $what))
				return true;
			break;
		case 'domain_period':
			if (preg_match('/^\d{1,10}$/', $what) && ($what >= 1) && ($what <= 10))
				return true;
			break;
		case 'email':
			if (preg_match('/^[a-zA-Z0-9][A-Za-z0-9_\.-]*@([\w\d_-]+\.)+\w{2,5}$/', $what))
				return true;
			break;
		case 'epp':
			if (preg_match('/^[^\s]{3,30}$/', $what))
				return true;
			break;
		case 'host':
			if (preg_match('/^(?:(?:[\w\d]+|[\w\d]+[\w\d-]*[\w\d]+)\.)*(?:((?:[\w\d]+|[\w\d-]*[\w\d]+[\w\d]*))\.)([\w]{2,16})$/', $what))
				return true;
			break;
		case 'id':
			if (preg_match('/^\d{1,15}$/', $what))
				return true;
			break;
		case 'ip':
			if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $what))
				return true;
			break;
		case 'name':
			mb_regex_encoding('utf-8');
			if (mb_eregi('^[[:alpha:]0-9\s\.-]{2,60}$', $what))
				return true;
			break;
		case 'password':
			if (preg_match('/^[\w_@-]{3,32}$/', $what))
				return true;
			break;
		case 'password_strong':
			if (preg_match('/^[a-z0-9@`!"#\$%&\'\(\)\*\+,\-\.\/\:;\[\{\<\|\=\]\}\>\^~?\\\\_]{3,20}$/i', $what))
				return true;
			break;
		case 'phone':
			if (preg_match('/^\+\d{1,3}\.\d{4,12}$/', $what))
				return true;
			break;
		case 'phonecode':
			if (preg_match('/^\d{1,3}$/', $what))
				return true;
			break;
		case 'phoneno':
			if (preg_match('/^\d{4,12}$/', $what))
				return true;
		case 'sld':
			if (preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $what) && (strlen($what) <= 63))
				return true;
			break;
		case 'state':
			mb_regex_encoding('utf-8');
			if (mb_eregi('^[[:alpha:]0-9 -]{2,32}$', $what))
				return true;
			break;
		case 'stateprovincechoice':
			if (in_array($what, array('S', 'P')))
				return true;
			break;
		case 'tld':
			if (preg_match('/^([a-z]{2,6})(\.[a-z]{2,4})?$/', $what))
				return true;
			break;
		case 'username':
			if (preg_match('/^[a-zA-Z0-9][\w_-]{1,16}$/', $what))
				return true;
			break;
		case 'zip':
			if (preg_match('/^[0-9a-z -]{2,15}$/i', $what))
				return true;
			break;
		}

		return false;
	}

	function __construct() {
	}
	// Internal functions END


	// Used to format a request to the bookmaker API
	abstract protected function prepare_command( $param );

	// Used to actually send a request to a bookmaker API
	abstract protected function send_command( $param );

	// Used to parse the data we have received from the bookmaker API
	abstract protected function parse_command_result( $param );

	/*
     * Returns: array( $bookmaker_sport_id => $sport_name, ... )
     *
	 * Sample output:
     * Array
     *(
     *    [1] => Soccer
     *    [468328] => Handball
     *    [2] => Tennis
     *)
     */
	abstract function get_sports();

	/*
     * Returns: array( $bookmaker_league_id => $league_name, ... )
     *
     * Sample output:
     *Array
     * (
     *    [16295] => Featherweight
     *    [11715] => Light Heavyweight
     *    [16287] => Middleweight
     *)
     */
	abstract function get_leagues( $sport_id );

	/* get_events()
	*
	* Returns:
	*
	* array(
	*  'name'       => $event_name,
	*  'start_date_gmt' => $start_date_gmt,
	*  'end_date_gmt'   => $start_date_gmt,
	*  'is_live'        => $is_live,
	*  'status'         => $status,
	*  'meta'           => array(
	*      'event_id'          => $bookmaker_event_id,
	*      'league_id'         => $bookmaker_league_id,
	*      'draw_rotation_num' => $draw_rotation_num,
	*  )
	*
	*  'markets'      => array(
	*      'market_id'    => $bookmaker_market_id,
	*      'name'         => $market_name,
	*      'number'       => $period_number,
	*      'market_type'  => ( --, FIRST_HALF, MATCH_RESULT, ... ) // http://pricefeeds.williamhill.com/bet/en-gb?action=GoPriceFeed
	*      'start_date_gmt' => $end_date_gmt,
	*      'end_date_gmt'   => $end_date_gmt,
	*      'participants'   => array(
	*          'name'         => $name,
	*          'handicap'     => $handicap,
	*          'price_data'   => $price_data,
	*          'status'       => $status, // Contains result, ACTIVE, WINNER, LOSER, REMOVED_VACANT, REMOVED, HIDDEN
	*          'meta'         => array(
	*              'selection_id' => $selection_id,
	*              'flags'        => $flags, // home team, away team, etc
	*              'rotation_num' => $rotation_num,
	*              'pitcher'      => $pitcher,
	*              'score'        => $score,
	*              'red_cards'    => $red_cards,
	*              ...
	*          )
	*      )
	*      'meta'           => array(
	*          'market_id'    => $bookmaker_market_id,
	*      )
	*  )
	* )
	*
	* Sample Output:
	*[24] => Array
	*    (
	*        [name] => Cotto v Alvarez
	*        [start_date_gmt] => 2015-11-22 04:00:00
	*        [end_date_gmt] => 
	*        [is_live] => 0
	*        [status] => 0
	*        [meta] => Array
	*            (
	*                [event_id] => 27486539
	*                [league_id] => 215152
	*            )
	*
	*        [markets] => Array
	*            (
	*                [0] => Array
	*                    (
	*                        [name] => Match Odds
	*                        [market_type] => MATCH_ODDS
	*                        [start_date_gmt] => 2015-11-22 04:00:00
	*                        [end_date_gmt] => 
	*                        [participants] => Array
	*                            (
	*                                [0] => Array
	*                                    (
	*                                        [name] => Miguel Cotto
	*                                        [handicap] => 0
	*                                        [price_data] => 2
	*                                        [status] => ACTIVE
	*                                        [meta] => Array
	*                                            (
	*                                                [selection_id] => 3656147
	*                                                [runner_id] => 3656147
	*                                            )
	*
	*                                    )
	*
	*                                [1] => Array
	*                                    (
	*                                        [name] => Saul Alvarez
	*                                        [handicap] => 0
	*                                        [price_data] => 1.4
	*                                        [status] => ACTIVE
	*                                        [meta] => Array
	*                                            (
	*                                                [selection_id] => 4686417
	*                                                [runner_id] => 4686417
	*                                            )
	*
	*                                    )
	*
	*                                [2] => Array
	*                                    (
	*                                        [name] => Draw
	*                                        [handicap] => 0
	*                                        [price_data] => 16
	*                                        [status] => ACTIVE
	*                                        [meta] => Array
	*                                            (
	*                                                [selection_id] => 31162
	*                                                [runner_id] => 31162
	*                                            )
	*
	*                                    )
	*
	*                            )
	*
	*                        [meta] => Array
	*                            (
	*                                [market_id] => 1.120102837
	*                            )
	*
	*                    )
	*
	*            )
	*
	*    )
	*
	*
	*
	*/
	abstract function get_events( $sport_id, $last_poll = null, $is_live = null );

	/*
     * Returns: array( $events ) from result files
     *
     */
	abstract  function get_results( $event_id );
}