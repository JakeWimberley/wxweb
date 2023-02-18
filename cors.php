<?php
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
$querystring = http_build_query($_GET);
// major hack-o-rama: chop off url= at beginning of query string to get original url
$originalurl = substr(urldecode($querystring),4);
$curl_handle=curl_init();
curl_setopt($curl_handle,CURLOPT_URL,$originalurl);
curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,2);
curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,true);
curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
$buffer = curl_exec($curl_handle);
curl_close($curl_handle);
print($buffer);
?>
