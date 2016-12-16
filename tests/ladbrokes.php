<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-ladbrokes.php' );
require( __DIR__ . '/../auto-checker.php' );

$obj = new Ladbrokes();

print "<pre>";

$sports = $obj->get_sports();
automatic_checker( $sports, 'ladbrokes' );

#$events = $obj->get_events( 210000113 );
#print_r( $events );

print "</pre>";