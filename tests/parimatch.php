<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-parimatch.php' );
require( __DIR__ . '/../auto-checker.php' );


$obj = new Parimatch();

print "<pre>";
#$sports = $obj->get_sports();
#print_r( $sports );

#$leagues = $obj->get_leagues(2);
#print_r( $leagues );

$events = $obj->get_events( 2, null, true );
print_r( $events );

echo "</pre>";
