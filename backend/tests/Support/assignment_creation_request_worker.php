<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$backendPath = dirname(__DIR__, 2);

require $backendPath.'/vendor/autoload.php';

$application = require $backendPath.'/bootstrap/app.php';
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$kernel = $application->make(Kernel::class);
$kernel->bootstrap();
DB::connection()->selectOne(
    'SELECT set_config(?, ?, false)',
    ['application_name', $input['application_name']],
);
$request = Request::create(
    '/api/v1/assignments',
    'POST',
    [],
    [],
    [],
    [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$input['token'],
        'CONTENT_TYPE' => 'application/json',
    ],
    json_encode($input['payload'], JSON_THROW_ON_ERROR),
);
$response = $kernel->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
], JSON_THROW_ON_ERROR);

$kernel->terminate($request, $response);
