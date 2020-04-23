<?php

include './config.php';

$rss_urls = array(
	"https://www.town.fukaura.lg.jp/categories/bunya/kenkou_iryou/korona/index.rss",
	"http://www.vill.inakadate.lg.jp/shinchaku-portal/index.rss",
    //"https://www.town.imabetsu.lg.jp/rss/feed.rss"
);

# TODO: キーワードをカンマ区切りにする
$search_keyword = array(
    'title'=> 'マスク',
    'description'=> '新型',
    'category' => ''
);

# データの取得
$rss_data = array();
foreach ($rss_urls as $url) {
    $rss_api_url = API_URL ."/rss_to_json/?rss_url=". urlencode($url);

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

# フィルタリング
$work = array();
foreach ($marge_data as $k => $v) {

    $filter_check = true;
    //AND
    if ($search_keyword['title'] !== '') {
        if ( $filter_check && preg_match('/'. $search_keyword['title'] .'/', $v['title']) === 1 ) {
        } else {
            $filter_check = false;
        }
    }
    if ($search_keyword['description'] !== '') {
        if ( $filter_check && preg_match('/'. $search_keyword['description'] .'/', $v['description']) === 1 ) {
        } else {
            $filter_check = false;
        }
    }
    if( $search_keyword['category'] !== ''){
        if ( $filter_check && in_array($search_keyword['category'], $v['category']) ) {
        } else {
            $filter_check = false;
        }
    }
    if ( $filter_check ) {
        array_push($work, $v);
    }
}
$marge_data = $work;


# 並び替え
foreach ($marge_data as $k => $v) {
  $id[$k] = $v['date'];
}
array_multisort($id, SORT_DESC, $marge_data);

# 結果を表示
header('Content-type: text/javascript; charset=utf-8');
echo json_encode($marge_data);