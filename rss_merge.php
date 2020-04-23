<?php

include './config.php';

$rss_urls = array(
	"https://www.town.fukaura.lg.jp/categories/bunya/kenkou_iryou/korona/index.rss",
	"http://www.vill.inakadate.lg.jp/shinchaku-portal/index.rss",
    //"https://www.town.imabetsu.lg.jp/rss/feed.rss"
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
	# TODO: 様々な条件に対応できるようにする
    if(
    	true
    	//&& preg_match('/コロナ/',$v['title'])
    	//&& preg_match('/新型/',$v['description'])
    	//&& in_array('くらし・教育', $v['category'])
    ){
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