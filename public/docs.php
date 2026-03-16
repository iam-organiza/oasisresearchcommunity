<?php

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../src/OpenApi.php';

use OpenApi\Generator;

header('Content-Type: application/json');

// Create the generator and generate docs from your source
$generator = new Generator();
$openapi = $generator->generate([__DIR__ . '/../src']);

// Output as JSON (you can also use toYaml())
echo $openapi->toJson();
