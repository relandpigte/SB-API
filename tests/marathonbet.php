<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-marathonbet.php' );
require( __DIR__ . '/../auto-checker.php' );

$obj = new Marathonbet();

print "<pre>";

$sports = $obj->get_sports();
automatic_checker( $sports, 'marathonbet' );
#$events = $obj->get_events( 2183521 );
#print_r( $events );
print "</pre>";
