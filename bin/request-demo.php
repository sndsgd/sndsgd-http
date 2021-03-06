<?php

require __DIR__."/../vendor/autoload.php";

$environment = new \sndsgd\Environment($_SERVER);
$request = new \sndsgd\http\Request($environment);
switch ($request->getPath()) {
    case "/query":
        $data = [
            '$_GET' => $_GET,
            'sndsgd' => $request->getQueryParameters(),
        ];
        break;
    case "/body":
        $data = [
            '$_POST' => $_POST,
            '$_FILES' => $_FILES,
            'sndsgd' => $request->getBodyParameters(),
        ];
        break;
    default:
        $data = [
            "path" => $request->getPath(),
            "method" => $request->getMethod(),
            "headers" => $request->getHeaders(),
            "query" => $request->getQueryParameters(),
            "body" => $request->getBodyParameters(),
        ];
}

echo json_encode($data, \sndsgd\Json::HUMAN);
