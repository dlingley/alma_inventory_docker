<?php
require("key.php");
require_once(__DIR__ . "/login.php");

$lib_id = isset($_GET['lib_id']) ? $_GET['lib_id'] : '';
if ($lib_id === '') {
	http_response_code(400);
	echo json_encode(array('error' => 'Missing lib_id'));
	exit;
}
if (!preg_match('/^[A-Za-z0-9_-]+$/', $lib_id)) {
	http_response_code(400);
	echo json_encode(array('error' => 'Invalid lib_id. Allowed characters: letters, numbers, underscore, hyphen.'));
	exit;
}

$ch = curl_init();
$url = 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/conf/libraries/' . urlencode($lib_id) . '/locations';
$queryParams = '?' . urlencode('lang') . '=' . urlencode('en') . '&' . urlencode('apikey') . '=' . urlencode(trim(ALMA_SHELFLIST_API_KEY));
curl_setopt($ch, CURLOPT_URL, $url . $queryParams);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
$response = curl_exec($ch);
curl_close($ch);

$xml_result = @simplexml_load_string($response);
$locations = [];

if ($xml_result && isset($xml_result->location)) {
	foreach($xml_result->location as $location) {
		$location_obj = new stdClass();
		$location_obj->code = (string) $location->code;
		$location_obj->name = (string) $location->name;
		$locations[trim($location->code)] = $location_obj;
	}
}

// Fallback default locations if Alma API returns error/not allowed
if (empty($locations)) {
	$defaultLocs = [
		['code' => 'main', 'name' => 'General Collection (Stacks)'],
		['code' => 'ref', 'name' => 'Reference Collection'],
		['code' => 'resv', 'name' => 'Reserve Collection'],
		['code' => 'media', 'name' => 'Media & Periodicals']
	];
	foreach ($defaultLocs as $dl) {
		$lObj = new stdClass();
		$lObj->code = $dl['code'];
		$lObj->name = $dl['name'];
		$locations[$dl['code']] = $lObj;
	}
}

$out = array_values($locations);
$main = array('locationData' => $out);
echo json_encode($main);
?>
