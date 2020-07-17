<?php

include './../config.php';

$hash = filter_input(INPUT_GET, 'hash');
$response = array();

if ( $hash ) {
    // キャッシュを利用する
    $response['request'] = json_decode(file_get_contents(API_URL .'/rss_to_json/api/paraCache/'. $hash, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                "ignore_errors" => true,
                'request_fulluri' => true,
            ]
        ])
    ), true);
}


header('Content-type: text/javascript; charset=utf-8');
echo json_encode($response);