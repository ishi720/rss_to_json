<?php

include './config.php';

$data = array();

$rss_url = htmlspecialchars($_GET["rss_url"],ENT_QUOTES, "UTF-8");

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

$size = filter_input(INPUT_GET, 'size', FILTER_VALIDATE_INT) ?: 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;

//ATOM
if($format == "ATOM"){
	$info_data = atom_info_get($rssdata);
	$feed_data = atom_feed_get($rssdata);
}
//RSS1.0
elseif($format == "RSS1.0"){
	$info_data = rss1_info_get($rssdata);
	$feed_data = rss1_feed_get($rssdata);

}
//RSS2.0
elseif($format == "RSS2.0"){
	$info_data = rss2_info_get($rssdata);
	$feed_data = rss2_feed_get($rssdata);
}
else {
	print("FORMAT ERROR\n");exit;
}

//　ページとサイズの表示計算
$min_size = $size * ($page - 1);
$max_size = ($size * $page) - 1;

$response = array();
$response['error_status'] = "0";
$response['response_feed_count'] = count($feed_data);
$response['request_url'] = $rss_url;
$response['rss_format'] = $format;
$response['response_info'] = $info_data;

$response['response_feed'] = array();
for ($i = $min_size; $i <= $max_size; $i++){
    if($feed_data[$i]){
        array_push($response['response_feed'], $feed_data[$i]);
    } else {
        break;
    }
}

header('Content-type: text/javascript; charset=utf-8');
//echo json_encode($response);
echo sprintf("callback(%s)",json_encode($response));

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


// info_get
function rss1_info_get($rssdata){
	foreach ($rssdata->channel as $channel) {
		$work = array();
		foreach ($channel as $key => $value) {
			$work[$key] = (string)$value;
		}
		//dc
		foreach ($channel->children('dc',true) as $key => $value) {
			$work['dc:'. $key] = (string)$value;
		}
		$data[] = $work;
	}
	return $data;
}
function rss2_info_get($rssdata){
	foreach ($rssdata->channel as $channel) {
		$work = array();
		foreach ($channel as $key => $value) {
			$work[$key] = (string)$value;
		}
		$data[] = $work;
	}
	return $data;
}
function atom_info_get($rssdata){
	foreach ($rssdata as $item){
		$work = array();
		$work['title'] = (string)$item;
		$data[] = $work;
	}
	return $data;
}

// feed_get
function rss1_feed_get($rssdata){
	foreach ($rssdata->item as $item) {
		$work = array();

		foreach ($item as $key => $value) {
			$work[$key] = (string)$value;
		}

		//dc
		foreach ($item->children('dc',true) as $key => $value) {
			$work['dc:'. $key] = (string)$value;
		}

		//content
		foreach ($item->children('content',true) as $key => $value) {
			$work['content:'. $key] = (string)$value;
		}

		$data[] = $work;
	}
	return $data;
}
function rss2_feed_get($rssdata){
	foreach ($rssdata->channel->item as $item) {
		$work = array();
		foreach ($item as $key => $value) {
			$work[$key] = (string)$value;
		}
		$data[] = $work;
	}
	return $data;
}
function atom_feed_get($rssdata){
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
