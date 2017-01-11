<?php

$mail = 'r@brchan.org';
$apiKey = '2484dd69992dbfa50af24f8625c2e300c81cd';
$zoneId = '4514b1293e130a06dd090e3f8207f750';

$headers = array();
$headers[] = 'X-Auth-Email: ' . $mail;
$headers[] = 'X-Auth-Key: ' . $apiKey;
$headers[] = 'Content-Type: application/json';


function developmentMode($activate = true){
	global $zoneId, $headers;

	$data = array(
		"value" => $activate ? "on" : "off",
	);
	$str_data = json_encode($data);

	$ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . $zoneId . '/settings/development_mode');

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $str_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	$response = curl_exec($ch);
	return $response;
}

function purgeCache($everything = false, $files = []){
	global $zoneId, $headers;

	$data = array();
	if($everything){
		$data["purge_everything"] = $everything;
	} else {
		$data["files"] = $files;
	}
	$str_data = json_encode($data);

	$ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . $zoneId . '/purge_cache');

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	curl_setopt($ch, CURLOPT_POSTFIELDS, $str_data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	$response = curl_exec($ch);
	return $response;
}

function purgeEverything(){
	purgeCache(true);
}

function purgeFiles($files = []){
	purgeCache(false, $files);
}

function isDevelopmentModeEnabled(){
	global $zoneId, $headers;

	$ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . $zoneId . '/settings/development_mode');

	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

	$response = curl_exec($ch);
	$response = json_decode($response, true);
	$result = $response['result'];
	$value = $result['value'];

	return $value == 'on' ? true : false;
}

?>
