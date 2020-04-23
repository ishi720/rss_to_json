<?php

include './config.php';

$rss_url = filter_input(INPUT_GET, 'rss_url', FILTER_VALIDATE_URL);
$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 0;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 0;

$data = array();
$data['request']['url'] = $rss_url;
$data['request']['page'] = $page;
$data['request']['size'] = $size;

if ( !$rss_url ) {
    //エラー
    response_json('NG', $data['request'], '');
    exit;
}

if (PROXY_REQUEST) {
    // Proxy経由の場合
    $rssdata = simplexml_load_string(file_get_contents($rss_url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'request_fulluri' => true,
                'header' =>
                    'Proxy-Authorization: Basic '.base64_encode(PROXY_USER .':'. PROXY_PASS)."\r\n".
                    'Proxy-Connection: close',
                'proxy'  => PROXY_URL .':'. PROXY_PORT,
            ]
        ])
    ));
} else {
    $rssdata = simplexml_load_string(file_get_contents($rss_url));
}

$format = rss_format_get($rssdata);

//ATOM
if($format == "ATOM"){
    $feed_data = atom_feed_get($rssdata);
    $items_data = atom_items_get($rssdata);
}
//RSS1.0
elseif($format == "RSS1.0"){
    $feed_data = rss1_feed_get($rssdata);
    $items_data = rss1_items_get($rssdata);

}
//RSS2.0
elseif($format == "RSS2.0"){
    $feed_data = rss2_feed_get($rssdata);
    $items_data = rss2_items_get($rssdata);
}
else {
    print("FORMAT ERROR\n");exit;
}


$data['response']['items_all_count'] = count($items_data);
$data['response']['rss_format'] = $format;
$data['response']['feed'] = $feed_data;
$data['response']['items'] = array();
$init_num = $size * ($page - 1);
foreach($items_data as $key => $item){
    if( $page * $size === 0) {
        //ページ・サイズの指定されていなければすべて表示
        array_push($data['response']['items'], $items_data[$key]);
    } else {
        //ページ・サイズが指定して入れば、範囲内のみ表示
        if ( $init_num < $key && $init_num + $size) {
            array_push($data['response']['items'], $items_data[$key]);
        }
    }
}

// 正常終了
response_json('OK', $data['request'], $data['response']);

/*
 function
*/
function rss_format_get($rssdata){
    if($rssdata->entry){
        //ATOM
        return "ATOM";
    } elseif ($rssdata->item){
        //RSS1.0
        return "RSS1.0";
    } elseif ($rssdata->channel->item){
        //RSS2.0
        return "RSS2.0";
    } else {
        print("FORMAT ERROR");
        exit;
    }
}


// feed
function rss1_feed_get($rssdata){
    $data = array();
    foreach ($rssdata->channel as $channel) {
        $work = array();
        foreach ($channel as $key => $value) {
            $work[$key] = (string)$value;
        }
        $data[] = $work;
    }
    return $data;
}
function rss2_feed_get($rssdata){
    $data = array();
    foreach ($rssdata->channel as $channel) {
        $work = array();
        foreach ($channel as $key => $value) {

            $work[$key] = (string)$value;
        }
        $data[] = $work;
    }
    return $data;
}
function atom_feed_get($rssdata){
    $data = array();
    foreach ($rssdata as $item){
        $work = array();
        $work['title'] = (string)$item;
        $data[] = $work;
    }
    return $data;
}

// items
function rss1_items_get($rssdata){
    $data = array();
    foreach ($rssdata->item as $item) {
        $work = array();

        foreach ($item as $key => $value) {
            $work[$key] = (string)$value;
        }

        //dc
        foreach ($item->children('dc',true) as $key => $value) {
            $work['dc:'. $key] = (string)$value;
            if ( $key === "date"){
                $timestamp = strtotime((string)$value);
                $work['date'] = date('Y/m/d H:i:s', $timestamp);
            }
        }

        //content
        foreach ($item->children('content',true) as $key => $value) {
            $work['content:'. $key] = (string)$value;
        }

        $data[] = $work;
    }
    return $data;
}
function rss2_items_get($rssdata){
    $data = array();
    foreach ($rssdata->channel->item as $item) {
        $work = array();
        $work['category'] = array();
        foreach ($item as $key => $value) {
            if ($key === "category") {
                array_push($work[$key], (string)$value);
            } else {
                $work[$key] = (string)$value;

                if ( $key === "pubDate"){
                    $timestamp = strtotime((string)$value);
                    $work['date'] = date('Y/m/d H:i:s', $timestamp);
                 } 
            }
        }
        $data[] = $work;
    }
    return $data;
}
function atom_items_get($rssdata){
    $data = array();
    foreach ($rssdata->entry as $item){
        $work = array();
        foreach ($item as $key => $value) {
            if( $key == "link"){
                $work[$key] = (string)$value->attributes()->href;;
            } else {
                $work[$key] = (string)$value;
            }
        }
        $data[] = $work;
    }
    return $data;
}

function response_json($status, $request, $response){
    $data = array();
    $data['status'] = $status;
    $data['request'] = $request;
    $data['response'] = $response;

    header('Content-type: text/javascript; charset=utf-8');
    echo json_encode($data);
}
