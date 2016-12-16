<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-winlinebet.php' );
require( __DIR__ . '/../auto-checker.php' );


$obj = new Winlinebet();

print "<pre>";
$sports = $obj->get_sports();
automatic_checker( $sports, 'winline' );

#$events = $obj->get_events( 1035532225 );
#print_r( $events );
echo "</pre>";
