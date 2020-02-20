<?php
require __DIR__ . '/../vendor/autoload.php';
require '../src/Core/autoload.php';

$credential = new \Certificate\Credentials;

$credential->owner      = "grouposanti";
$credential->data       = "grouposanti.com.py";
$credential->expired    = "30 days";

//$credential = $credential->generate_keys();
//print_r( $credential );
$site = "";
//$site = $credential->save_credentials( 1 );
print_r( $site );
$access_token = 'TGVfbXFka0lGenFzMFB3TldFVnRyVm11OmdDc19uVmh3d250ZkRxbWJyNURTZGxDSk1sU0ZWZS1KN2RtT3J1aVM5SmQwX253TA==';
$rr = "";
$rr = $credential->valid_access_token( $access_token );
print_r($rr);
print "\n\r";