<?php

// generate the access and refresh token
$accesstoken = base64_encode((bin2hex(openssl_random_pseudo_bytes(24))).time()); // 24 bytes long random unique string in binary -> converted to hex -> converted to base 64
$refreshtoken = base64_encode((bin2hex(openssl_random_pseudo_bytes(16))).time()); // 16 bytes long random unique string in binary -> converted to hex -> converted to base 64
//the time() funtion is also added to the hex version to further ensure absolute uniqueness

$accesstoken_expiry = 1200; //20mins
$refreshtoken_expiry = 1209600; //14 days (2 weeks)

$max_loginattempts = 3;

$system_dateformat = '\'%d/%m/%Y %H:%i\'';

?>