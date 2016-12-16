<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-ligastavok.php' );
require( __DIR__ . '/../auto-checker.php' );


$obj = new Ligastavok();

print "<pre>";

$sports = $obj->get_sports();
automatic_checker( $sports, 'ligastavok' );

#$events = $obj->get_events(28);
#print_r( $events );

echo "</pre>";