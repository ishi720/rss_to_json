<?php

# 処理時間計測開始
$time_start = microtime(true);


// ライブラリの読み込み
require_once "./../vendor/mibe/feedwriter/Item.php" ;
require_once "./../vendor/mibe/feedwriter/Feed.php" ;
require_once "./../vendor/mibe/feedwriter/RSS2.php" ;

use \FeedWriter\RSS2;


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

    $output = filter_input(INPUT_GET, 'output') ?: 'json'; // 出力形式: json/rss2
    $response['result']['hash'] = $hash;
} else {

    if ( !empty($_POST) ) {
        $response['request']['feed_title'] = filter_input(INPUT_POST, 'feed_title') ?: '';
        $response['request']['feed_description'] = filter_input(INPUT_POST, 'feed_description') ?: '';

        $response['request']['rss'] = explode(',',filter_input(INPUT_POST, 'url') );
        $response['request']['filter'] = array(
            'title'=> filter_input(INPUT_POST, 'title') ?: '',
            'description'=> filter_input(INPUT_POST, 'description') ?: '',
            'category' => filter_input(INPUT_POST, 'category') ?: ''
        );

        $response['request']['page'] = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: null;
        $response['request']['size'] = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_INT) ?: null;

        $response['request']['noCache'] = filter_input(INPUT_POST, 'no_cache') === 'true' ? 'true': 'false';
        $output = filter_input(INPUT_POST, 'output') ?: 'json'; // 出力形式: json/rss2

    } else {
        $response['request']['feed_title'] = filter_input(INPUT_GET, 'feed_title') ?: '';
        $response['request']['feed_description'] = filter_input(INPUT_GET, 'feed_description') ?: '';

        $response['request']['rss'] = explode(',',filter_input(INPUT_GET, 'url') );
        $response['request']['filter'] = array(
            'title'=> filter_input(INPUT_GET, 'title') ?: '',
            'description'=> filter_input(INPUT_GET, 'description') ?: '',
            'category' => filter_input(INPUT_GET, 'category') ?: ''
        );

        $response['request']['page'] = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: null;
        $response['request']['size'] = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: null;

        $response['request']['noCache'] = filter_input(INPUT_GET, 'no_cache') === 'true' ? 'true': 'false';
        $output = filter_input(INPUT_GET, 'output') ?: 'json'; // 出力形式: json/rss2
    }

    $response['result']['hash'] = md5(json_encode($response['request']));
    file_put_contents('paraCache/'. $response['result']['hash'], json_encode($response['request']));
} 

# データの取得
$rss_data = array();
foreach ($response['request']['rss'] as $url) {
    $rss_api_url = API_URL ."/rss_to_json/api/rss_to_json.php?rss_url=". urlencode($url) ."&no_cache=". $response['request']['noCache'];

    $api_data = json_decode(file_get_contents($rss_api_url),true);
    if ( $api_data['status'] === 'OK') {
        array_push($rss_data, $api_data);
    }
}

# マージ
$marge_data = array();
foreach ($rss_data as $json) {
    foreach ($json['response']['items'] as $k => $v) {
        $v['feed'] = $json['response']['feed'][0];
        array_push($marge_data, $v);
    }
}


$filter_check = $response['request']['filter']['title'] !== '' ||  $response['request']['filter']['description'] !== '' ||  $response['request']['filter']['category'] !== '';

# フィルタリング
$work = array();
foreach ($marge_data as $k => $v) {

    if ( $filter_check) {
        //OR
        if ($response['request']['filter']['title'] !== '') {
            if ( preg_match('/('. str_replace(',','|',$response['request']['filter']['title']) .')/', $v['title']) === 1 ) {
                array_push($work, $v);
                continue;
            } 
        }
        if ($response['request']['filter']['description'] !== '') {
            if ( preg_match('/('. str_replace(',','|',$response['request']['filter']['description']) .')/', $v['description']) === 1 ) {
                array_push($work, $v);
                continue;
            } 
        }
        if( $response['request']['filter']['category'] !== ''){
            if ( preg_grep('/^('. str_replace(',','|',$response['request']['filter']['category']) .')$/', $v['category']) ) {
                array_push($work, $v);
                continue;
            } 
        }
    } else {
        array_push($work, $v);
    }
}
$marge_data = $work;

# 並び替え
foreach ($marge_data as $k => $v) {
    if ( array_key_exists('date', $v)) {
        $id[$k] = $v['date'];
    } else {
        $id[$k] = '1970/01/01 00:00:00';
    }
}
if (!empty($marge_data)) {
    array_multisort($id, SORT_DESC, $marge_data);
}

$response['result']['countAll'] = count($marge_data);

if ( $response['request']['page'] === null || $response['request']['size'] === null ) {
    $response['result']['marge_data'] = $marge_data;
} else {
    // ページサイズ指定
    $init_num = $response['request']['size'] * ($response['request']['page'] - 1);
    $response['result']['marge_data'] = array();
    foreach($marge_data as $key => $item){
        if ( $init_num <= $key && $key <= $init_num + $response['request']['size'] -1) {
            array_push($response['result']['marge_data'], $marge_data[$key]);
        }
    }
    $response['result']['count'] = count($response['result']['marge_data']);
}

# APIの結果URLを格納
$response['result']['output']['rss'] = API_URL .'/rss_to_json/api/rss_merge.php?hash='. $response['result']['hash'] .'&output=rss2';
$response['result']['output']['json'] = API_URL .'/rss_to_json/api/rss_merge.php?hash='. $response['result']['hash'] .'&output=json';

# 処理時間計測
$response['result']['processingTime'] = microtime(true) - $time_start;

# 結果を表示
if ($output === "rss2") {
    outputRSS2($response);
} else {
    # 結果を表示
    header('Content-type: text/javascript; charset=utf-8');
    echo json_encode($response);
}

/**
 * json形式で結果を返す
 */
function outputJSON($response){
    header('Content-type: text/javascript; charset=utf-8');
    echo json_encode($response);
}


/**
 * rss2形式(xml)で結果を返す
 */
function outputRSS2($response){
    
    $feed = new RSS2;

    // チャンネル情報の登録
    $feed->setTitle($response['request']['feed_title']);
    $feed->setLink("http://socialdatagw.com");
    $feed->setDescription($response['request']['feed_description']);
    $feed->setDate(date(DATE_RSS, time()));
    $feed->setChannelElement("language", "ja-JP");
    $feed->setChannelElement("pubDate", date(DATE_RSS, time()));
    $feed->setChannelElement("category", "");

    foreach ($response['result']['marge_data'] as $k => $v) {

        // インスタンスの作成
        $item = $feed->createNewItem();

        // アイテムの情報
        $item->setTitle($v['title']);
        $item->setLink($v['link']);
        $item->setDescription($v['description']);
        if ( array_key_exists( 'date', $v ) ) {
            $item->setDate(strtotime($v['date']));// 更新日時
        }
        //$item->addElement('category', $v['category']);

        // アイテムの追加
        $feed->addItem($item) ;
    }

    header('Content-type: text/xml; charset=utf-8');
    echo($feed->generateFeed());
}