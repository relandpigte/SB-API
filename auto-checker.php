<?php

function automatic_checker( $sports, $bookmaker = 'leonbets' ) {
    $event_status = array(
        'event_name' => true,
        'event_start_date' => true,
        'event_id' => true,

        'market_id' => true,
        'market_type' => true,
        'market_name' => true,

        'participant_name' => true,
        'handicap' => true,
        'price_data' => true,
        'participant_id' => true,
    );

    $sport_status = array( 'sport_id' => true );
    $league_status = array( 'league_id' => true );

    $event_result  = event_checker( $sports, $event_status, $bookmaker );
    $sport_result  = sport_checker( $sports, $sport_status );
    $league_result = league_checker( $sports, $league_status, $bookmaker );

    echo "<strong>Sports</strong><br/>\n";
    echo ( $sport_result['sport_id'] ? "TRUE" : "FALSE" ) . " - Does every sport return at least 1 meta value?<br/>\n<br/>\n";

    echo "<strong>Leagues</strong><br/>\n";
    echo ( $league_result['league_id'] ? "TRUE" : "FALSE" ) . " - Does every league return at least 1 meta value?<br/>\n<br/>\n";

    echo "<strong>Event</strong><br/>\n";
    echo ( $event_result['event_name'] ? "TRUE" : "FALSE" ) . " - Do we return the name?<br/>\n";
    echo ( $event_result['event_start_date'] ? "TRUE" : "FALSE" ) . " - Do we return the start date?<br/>\n";
    echo ( $event_result['event_id'] ? "TRUE" : "FALSE" ) . " - Does every event return at least 1 meta value?<br/>\n<br/>\n";

    echo "<strong>Market</strong><br/>\n";
    echo ( $event_result['market_type'] ? "TRUE" : "FALSE" ) . " - Do we have market type for all?<br/>\n";
    echo ( $event_result['market_name'] ? "TRUE" : "FALSE" ) . " - Are we returning the name?<br/>\n";
    echo ( $event_result['market_id'] ? "TRUE" : "FALSE" ) . " - Does every market return at least 1 meta value?<br/>\n<br/>\n";

    echo "<strong>Participants</strong><br/>\n";
    echo ( $event_result['participant_name'] ? "TRUE" : "FALSE" ) . " - Do we return the name?<br/>\n";
    echo ( $event_result['handicap'] ? "TRUE" : "FALSE" ) . " - Are we returning handicap?<br/>\n";
    echo ( $event_result['price_data'] ? "TRUE" : "FALSE" ) . " - Are we returning price data?<br/>\n";
    echo ( $event_result['participant_id'] ? "TRUE" : "FALSE" ) . " - Does every participant return at least 1 meta value?<br/>\n<br/>\n";

}

function event_checker( $sports, $status, $bookmaker = 'winlinebet' ) {
    if ( empty( $sports ) || empty( $status ) )
        throw new Exception('Please enter required parameters.');

    switch ( $bookmaker ) {
        case 'ladbrokes':   $obj = new Ladbrokes(); break;
        case 'leonbets':    $obj = new Leonbets(); break;
        case 'ligastavok':  $obj = new Ligastavok(); break;
        case 'marathonbet': $obj = new Marathonbet(); break;
        case 'parimatch':   $obj = new Parimatch(); break;
        case 'williamhill': $obj = new Williamhill(); break;
        default:            $obj = new Winlinebet();
    }

    foreach ( $sports as $key => $sport ) {
        $events = $obj->get_events( $key );

        if ( ! empty( $events ) ) {
            foreach ( $events['events'] as $e ) {
                if ( ! isset( $e['name'] ) && $status['event_name'] )
                    $status['event_name'] = false;

                if ( empty( $e['start_date_gmt'] ) && $status['event_start_date'] )
                    $status['event_start_date'] = false;

                if ( empty( $e['meta']['event_id'] ) && $status['event_id'] )
                    $status['event_id'] = false;

                if ( isset( $e['markets'] ) ) {

                    foreach ( $e['markets'] as $market) {

                        if ( empty( $market['market_type'] ) && $status['market_type'] )
                            $status['market_type'] = false;

                        if ( ! isset( $market['name'] ) && $status['market_name'] )
                            $status['market_name'] = false;

                        if ( is_null( $market['meta']['market_id'] ) && $status['market_id'] )
                            $status['market_id'] = false;

                        if ( isset( $market['participants'] ) ) {
                            foreach ( $market['participants'] as $p ) {
                                if (!isset( $p['name'] ) && $status['participant_name'] )
                                    $status['participant_name'] = false;

                                if ( ! isset( $p['handicap'] ) && $status['handicap'] )
                                    $status['handicap'] = false;

                                if ( ! isset( $p['price_data'] ) && $status['price_data'] )
                                    $status['price_data'] = false;

                                if ( ! isset( $p['meta']['participant_id'] ) && $status['participant_id'] )
                                    $status['participant_id'] = false;
                            }
                        }
                        else {
                            $status['participant_name'] = false;
                            $status['handicap'] = false;
                            $status['price_data'] = false;
                        }
                    }
                }
                else {
                    $status['market_type'] = false;
                    $status['market_name'] = false;
                    $status['participant_name'] = false;
                    $status['handicap'] = false;
                    $status['price_data'] = false;
                }
            }
        }
    }
    return $status;
}

function sport_checker( $sports, $status ) {
    foreach ( $sports as $key => $sport ) {
        if ( empty( $key ) )
            $status['sport_id'] = false;
    }

    return $status;
}

function league_checker( $sports, $status, $bookmaker ) {

    switch ( $bookmaker ) {
        case 'ladbrokes':   $obj = new Ladbrokes(); break;
        case 'leonbets':    $obj = new Leonbets(); break;
        case 'ligastavok':  $obj = new Ligastavok(); break;
        case 'marathonbet': $obj = new Marathonbet(); break;
        case 'parimatch':   $obj = new Parimatch(); break;
        case 'williamhill': $obj = new Williamhill(); break;
        default:            $obj = new Winlinebet();
    }

    foreach ( $sports as $key => $sport ) {
        $leagues = $obj->get_leagues( $key );
        foreach ( $leagues as $i => $league ) {
            if ( empty( $i ) )
                $status['league_id'] = false;
        }

        if ( ! $status['league_id'] )
            break;
    }
    return $status;
}