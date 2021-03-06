<?php

include './../config.php';

$rss_url = filter_input(INPUT_GET, 'rss_url', FILTER_VALIDATE_URL);
$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 0;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 0;

$noCache = filter_input(INPUT_GET, 'no_cache') === 'true' ? true: false;

$data = array();
$data['request']['url'] = $rss_url;
$data['request']['page'] = $page;
$data['request']['size'] = $size;

if ( !$rss_url ) {
    //エラー
    response_json('NG', $data['request'], '');
    exit;
}

// キャッシュの有効期限
$cacheTime = '1h';

// キャッシュのPATH
$cachePath = "cache/".md5($rss_url);

// キャッシュ利用のフラグ
$cacheUse = false;
if ( !$noCache ) {
    if (file_exists($cachePath)) {
        //キャッシュファイルが存在する場合
        $timestamp = new DateTime( date("Y/m/d H:i:s", filemtime($cachePath)) );
        $timestamp->add(new DateInterval('PT'.strtoupper($cacheTime)));
        if ( new DateTime() < $timestamp ) {
            $cacheUse = true;
        }
    }
}

if ( $cacheUse ) {
    // キャッシュを利用する
    $contents = file_get_contents(API_URL .'/rss_to_json/api/cache/'.md5($rss_url), false, stream_context_create([
            'http' => [
                'method' => 'GET',
                "ignore_errors" => true,
                'request_fulluri' => true,
            ]
        ])
    );
} else {
    // キャッシュを利用しない
    if (PROXY_REQUEST) {
        $contents = file_get_contents($rss_url, false, stream_context_create([
                'http' => [
                    'method' => 'GET',
                    "ignore_errors" => true,
                    'request_fulluri' => true,
                    'header' => 'Content-Type: application/xml' ."\r\n".
                        'Proxy-Authorization: Basic '.base64_encode(PROXY_USER .':'. PROXY_PASS)."\r\n".
                        'Proxy-Connection: close',
                    'proxy'  => PROXY_URL .':'. PROXY_PORT,
                ]
            ])
        );
    } else {
        $contents = file_get_contents($rss_url, false, stream_context_create([
                'http' => [
                    "ignore_errors" => true
                ]
            ])
        );
    }
    $search = array("\0", "\x01", "\x02", "\x03", "\x04", "\x05","\x06", "\x07", "\x08", "\x0b", "\x0c", "\x0e", "\x0f");
    $contents = str_replace($search, '', $contents);  
    file_put_contents($cachePath,$contents);
}

// file_get_contents結果確認
preg_match("/[0-9]{3}/", $http_response_header[0], $http_status);
if($http_status[0] === '200'){
    $rssdata = simplexml_load_string($contents);
} else {
    //エラー
    response_json('NG', $data['request'], '');
    exit;
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
            if ($key !== 'items') {
                $work[$key] = (string)$value;
            }
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
            if ($key !== 'item') {
                $work[$key] = (string)$value;
            }
        }
        $data[] = $work;
    }
    return $data;
}
function atom_feed_get($rssdata){
    $data = array();
    $work = array();
    foreach ($rssdata as $key => $item){
        if ($key !== 'entry') {
            if ($key === 'link') {
                $work[$key] = (string)$item['href'];
            } else {
                $work[$key] = (string)$item;
            }
        }
    }
    $data[] = $work;
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
            if ( $key === "updated"){
                $timestamp = strtotime((string)$value);
                $work['date'] = date('Y/m/d H:i:s', $timestamp);
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
