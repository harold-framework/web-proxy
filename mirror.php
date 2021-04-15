<?php

$apiURL = "https://api.myinternal.website";

function displayError( $errMsg ) {
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error_message" => $errMsg,
        "location" => "proxy"
    ]);
    die();
}

$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_ENCODED);
$acceptedMethods = [
    "GET",
    "POST"
];
if (!(in_array($method, $acceptedMethods))) { displayError("Unaccepted method."); };

$ch = curl_init();
$url = $apiURL . $_SERVER["PATH_INFO"] . "?" . $_SERVER['QUERY_STRING'];
curl_setopt($ch, CURLOPT_URL, $url);

// Forward headers
$headers = [];
$disallowedHeaders = [
    "Host",
    "Accept",
    "Method"
];
foreach (getallheaders() as $k => $v) {
    if (!(in_array($k, $disallowedHeaders))) { $headers[$k] = $v; };
}

// Forcefully require authentication if we're on a whitelisted server.
$headers["X-Require-Authentication"] = true;

// Headers to string based array
$headersArray = [];
foreach ($headers as $k => $v) { array_push($headersArray, $k.":".$v); };

curl_setopt($ch, CURLOPT_HTTPHEADER, $headersArray);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FAILONERROR, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

switch ($method) {
    
    case "GET":
        break;

    case "POST":
        $data = json_decode(file_get_contents('php://input'), true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;

    default:
        displayError("Switch default, Invalid method.");
        break;

}

$response = curl_exec($ch);
curl_close($ch);

if (!($response)) { displayError("Response is unset."); };

header("Content-Type: application/json");
$response = json_decode($response, true);
if (in_array("success", array_keys($response))) {
    if (!($response["success"])) { $response["location"] = "api"; };
}
if (is_null($response)) { displayError("Failed to gather any response data."); };
echo json_encode($response);
die();
