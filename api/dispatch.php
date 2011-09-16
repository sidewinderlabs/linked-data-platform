<?php

// load Tonic library
require_once './lib/tonic/tonic.php';

// load Observation resource
require_once './observation.php';

// handle request
$request = new Request();
try {
	$resource = $request->loadResource();
    $response = $resource->exec($request);
} catch (ResponseException $e) {
	$response = $e->response($request);
}
$response->output();

