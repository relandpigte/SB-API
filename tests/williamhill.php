<?php

require( __DIR__ . '/../functions.php' );
require( __DIR__ . '/../class-bookmakers.php' );
require( __DIR__ . '/../class-williamhill.php' );
require( __DIR__ . '/../auto-checker.php' );

$obj = new Williamhill();

print "<pre>";
$sports = $obj->get_sports();
print_r( $sports );
#automatic_checker( $sports, 'williamhill' );

#$events = $obj->get_events( 424, null, true );
#print_r( $events );

print "</pre>";