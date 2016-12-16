<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-leonbets.php' );
require( __DIR__ . '/../auto-checker.php' );


$obj = new Leonbets();

print "<pre>";

$sports = $obj->get_sports();
automatic_checker( $sports, 'leonbets' );

#$events = $obj->get_events(3114363264);
#print_r( $events );

print "</pre>";



