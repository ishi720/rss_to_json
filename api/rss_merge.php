<?php

# 処理時間計測開始
$time_start = microtime(true);


// ライブラリの読み込み
require_once "./../vendor/mibe/feedwriter/Item.php" ;
require_once "./../vendor/mibe/feedwriter/Feed.php" ;
require_once "./../vendor/mibe/feedwriter/RSS2.php" ;

use \FeedWriter\RSS2;


include './../config.php';

# POST
$rss_urls = explode(',',filter_input(INPUT_POST, 'url') );
$noCache = filter_input(INPUT_POST, 'no_cache') === 'true' ? 'true': 'false';

$filter_title = filter_input(INPUT_POST, 'title') ?: '';
$filter_description = filter_input(INPUT_POST, 'description') ?: '';
$filter_category = filter_input(INPUT_POST, 'category') ?: '';

$page = filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: null;
$size = filter_input(INPUT_POST, 'size', FILTER_VALIDATE_INT) ?: null;
$output = filter_input(INPUT_POST, 'output') ?: 'json'; // 出力形式: json/rss2

# フィルター条件
$search_keyword = array(
    'title'=> $filter_title,
    'description'=> $filter_description,
    'category' => $filter_category
);

$response = array();
$response['request']['rss'] = $rss_urls;
$response['request']['filter'] = $search_keyword;

$response['request']['page'] = $page;
$response['request']['size'] = $size;

# データの取得
$rss_data = array();
foreach ($rss_urls as $url) {
    $rss_api_url = API_URL ."/rss_to_json/api/rss_to_json.php?rss_url=". urlencode($url) ."&no_cache=". $noCache;

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


$filter_check = $search_keyword['title'] !== '' ||  $search_keyword['description'] !== '' ||  $search_keyword['category'] !== '';

# フィルタリング
$work = array();
foreach ($marge_data as $k => $v) {

    if ( $filter_check) {
        //OR
        if ($search_keyword['title'] !== '') {
            if ( preg_match('/('. str_replace(',','|',$search_keyword['title']) .')/', $v['title']) === 1 ) {
                array_push($work, $v);
                continue;
            } 
        }
        if ($search_keyword['description'] !== '') {
            if ( preg_match('/('. str_replace(',','|',$search_keyword['description']) .')/', $v['description']) === 1 ) {
                array_push($work, $v);
                continue;
            } 
        }
        if( $search_keyword['category'] !== ''){
            if ( preg_grep('/^('. str_replace(',','|',$search_keyword['category']) .')$/', $v['category']) ) {
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

if ( $page === null || $size === null ) {
    $response['result']['marge_data'] = $marge_data;
} else {
    // ページサイズ指定
    $init_num = $size * ($page - 1);
    $response['result']['marge_data'] = array();
    foreach($marge_data as $key => $item){
        if ( $init_num <= $key && $key <= $init_num + $size -1) {
            array_push($response['result']['marge_data'], $marge_data[$key]);
        }
    }
    $response['result']['count'] = count($response['result']['marge_data']);
}


$response['result']['processingTime'] = microtime(true) - $time_start;

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
    $feed->setTitle("");
    $feed->setLink("");
    $feed->setDescription("");
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
        $item->setDate(strtotime($v['date']));

        // アイテムの追加
        $feed->addItem($item) ;
    }

    header('Content-type: text/xml; charset=utf-8');
    echo($feed->generateFeed());
}