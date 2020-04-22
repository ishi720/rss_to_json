<?php

include './config.php';

$rss_url1 = urlencode("https://www.town.fukaura.lg.jp/categories/bunya/kenkou_iryou/korona/index.rss");
$rss_url2 = urlencode("http://www.vill.inakadate.lg.jp/shinchaku-portal/index.rss");

# データの取得
$rss_api1 = API_URL ."/rss_to_json/?rss_url=". $rss_url1;
$rss_data1 = json_decode(file_get_contents($rss_api1),true);

$rss_api2 = API_URL ."/rss_to_json/?rss_url=". $rss_url2;
$rss_data2 = json_decode(file_get_contents($rss_api2),true);

# マージ
$marge_data = array();
foreach ($rss_data1['response']['items'] as $k => $v) {
    $v['feed'] = $rss_data1['response']['feed'][0];
    array_push($marge_data, $v);
}
foreach ($rss_data2['response']['items'] as $k => $v) {
    $v['feed'] = $rss_data2['response']['feed'][0];
    array_push($marge_data, $v);
}

# 並び替え
foreach ($marge_data as $key => $value) {
  $id[$key] = $value['date'];
}
array_multisort($id, SORT_DESC, $marge_data);

# 結果を表示
header('Content-type: text/javascript; charset=utf-8');
echo json_encode($marge_data);