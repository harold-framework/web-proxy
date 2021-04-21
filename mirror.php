<?php

$apiURL = "https://my.internal.website/";

/*
    Simply proxy requests from your publically facing website API
    to your internal/private API at a given route. No main dependencies
    other than cURL.

    TODO:
        - Add support for DELETE & PATCH
        - Preform basic validation on proxy side for POST requests.
*/

function displayError( $errMsg, $code ) {
    header("Content-Type: application/json");
    echo json_encode([
        "success" => false,
        "error_message" => $errMsg,
        "location" => "proxy",
        "code" => $code
    ]);
    die();
}

$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_ENCODED);
$acceptedMethods = [
    "GET",
    "POST"
];
if (!(in_array($method, $acceptedMethods))) { displayError("Unaccepted method.", "PROXY_INVALID_METHOD"); };

try {

    $ch = curl_init();
    $url = $apiURL . $_SERVER["PATH_INFO"] . "?" . $_SERVER['QUERY_STRING'];
    $url = rtrim($url, "/?");

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
    //curl_setopt($ch, CURLOPT_HEADER, 1);

    switch ($method) {
        
        case "GET":
            break;

        case "POST":
            $data = json_decode(file_get_contents('php://input'), true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            break;

        default:
            displayError("Switch default, Invalid method.", "PROXY_INVALID_METHOD");
            break;

    }

    $responseHeaders = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION,
    function($curl, $header) use (&$responseHeaders) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
        return $len;

        $responseHeaders[(trim($header[0]))][] = trim($header[1]);

        return $len;
    }
    );

    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception(curl_error($ch), curl_errno($ch));
    }
    
    curl_close($ch);


} catch(Exception $e) {
    
    displayError($e->getCode() . " : " . $e->getMessage(), "PROXY_CURL_EXCEPTION");

}

// Ignore default headers. We're only looking to set specifically set, unknown
// headers that the API gives us back. Such as a ratelimiting key.
$ignoreHeaders = ["Date", "Server", "Content-Type", "Keep-alive", "Connection"];
foreach ($responseHeaders as $k => $v) {
    foreach ($v as $vk => $vv) {
        if (!(in_array(ucfirst($k), $ignoreHeaders))) {
            header($k . ": " . $vv);
        }
    }
}

header("Content-Type: application/json");
$response = json_decode($response, true);
if (in_array("success", array_keys($response))) {
    if (!($response["success"])) {
        // Verify that a "code" response is given.

        if (!(array_key_exists("code", $response))) {
            // Code error is not given.
            $response["code"] = "UNKNOWN";
        }
        
        // Add location
        $response["location"] = "api";
    };
}
if (is_null($response)) { displayError("Failed to gather any response data.", "PROXY_INVALID_RESPONSE"); };
echo json_encode($response);
die();
