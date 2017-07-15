<?php

require_once( 'pdo.php' );
require_once( 'credentials.php' );


define( 'OUTPUT_DIR', './tmp/' );
define( 'CRAWLER_USER_AGENT', 'XXX (Linux)' );
//define( 'CRAWLER_USER_AGENT', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' );
//. Googlebot だとAmazonが拒否する

$records = array();

function trimText( $html, $startArr, $endText ){
	$txt = "";

	$b = true;
	$n = 0;
	for( $i = 0; $i < count( $startArr ) && $b; $i ++ ){
		$m = mb_strpos( $html, $startArr[$i], $n );
		if( $m ){
			$n = $m + mb_strlen( $startArr[$i] ); //. + 1 ?
		}else{
			$b = false;
		}
	}

	if( $b ){
		$m = mb_strpos( $html, $endText, $n );
		if( $m ){
			$txt = mb_substr( $html, $n, $m - $n );
		}
	}

	return $txt;
}

function trimTextNext( $html, $startArr, $endText ){
	$txt = "";
	$next = "";

	$b = true;
	$n = 0;
	for( $i = 0; $i < count( $startArr ) && $b; $i ++ ){
		$m = mb_strpos( $html, $startArr[$i], $n );
		if( $m ){
			$n = $m + mb_strlen( $startArr[$i] );
		}else{
			$b = false;
		}
	}

	if( $b ){
		$m = mb_strpos( $html, $endText, $n );
		if( $m ){
			$txt = mb_substr( $html, $n, $m - $n );
			$next = mb_substr( $html, $m + mb_strlen( $endText ) );
		}
	}

	return array( $txt, $next );
}

function trimPrice( $p ){
	//. 価格が範囲になっているケース
	if( preg_match( '/ - /', $p ) ){
		list($p1,$p2) = split(' - ',$p);
		$p = $p1; //. 安い方
	}

	$p = preg_replace( "/[^0-9]/", "", $p );

	return $p;
}

function trimNL( $w ){
	$w = trim( $w );
        $w = str_replace( "\n", "", $w );
        $w = str_replace( "\r", "", $w );
        $w = str_replace( "\t", "", $w );
        $w = str_replace( "'", "\'", $w );

	return $w;
}

function addLine( $filename, $line ){
	$fno = fopen( OUTPUT_DIR . $filename, 'a' );
	if( $fno ){
		fwrite( $fno, $line . "\n" );
		fclose( $fno );
	}else{
		echo( "Faild to open " . OUTPUT_DIR . $filename ."\n" );
	}
}

function endsWith($haystack, $needle){
	return $needle == "" || substr($haystack, -strlen($needle)) == $needle;
}

function removeTail($line){
	while( endsWith( $line, "\r\n" ) ){
		$line = substr($line,0,strlen($line)-2);
	}
	while( endsWith( $line, "\n" ) ){
		$line = substr($line,0,strlen($line)-1);
	}
	while( endsWith( $line, "\r" ) ){
		$line = substr($line,0,strlen($line)-1);
	}

	return $line;
}


function getCodesFromAmazonAPI($node){
	for( $i = 0; $i < 100000; $i += 1000 ){
		getCodesAmazonNodeMinMax( $node, $i, $i + 999 );
	}
}

function getCodesAmazonNodeMinMax($node,$min,$max){
	//. Page 1
	usleep( 1300000 );
        echo( "node = $node : min = $min , max = $max , page = 1 \n" );
	$totalpages = getItemSearchAmazonAPI($node,$min,$max);

	if( $totalpages < 11 || $max - $min == 9 ){
		if( $totalpages > 1 ){
			//. Page 2+
			$m = ( $totalpages > 10 ) ? 10 : $totalpages;
			for( $p = 2; $p <= $m ; $p ++ ){
				usleep( 1300000 );
                                echo( "node = $node :  min = $min , max = $max , page = $p / $totalpages \n" );
				getItemSearchAmazonAPI($node,$min,$max,$p);
			}
		}
	}else{
		//. Page 1+
		if( $max - $min == 999 ){
			for( $i = $min; $i < $max; $i += 100 ){
				getCodesAmazonNodeMinMax( $node, $i, $i + 99 );
			}
		}else if( $max - $min == 99 ){
			for( $i = $min; $i < $max; $i += 10 ){
				getCodesAmazonNodeMinMax( $node, $i, $i + 9 );
			}
		}else{
			for( $i = $min; $i <= $max; $i ++ ){
				getCodesAmazonNodeMinMax( $node, $i, $i );
			}
		}
	}

	return $totalpages;
}

function getItemSearchAmazonAPI($node,$min,$max,$item_page = 0,$aws_host = 'ecs.amazonaws.jp'){
	global $records;

	$totalpages = 0;
	$request = "http://" . $aws_host . "/onca/xml?";
	$timestamp = gmdate( "Y-m-d\TH:i:s\Z" );
//	echo( "timestamp = $timestamp\n" );

	$params = "AWSAccessKeyId=" . AWS_KEY . "&AssociateTag=" . AWS_ASSOC_TAG . "&BrowseNode=" . $node;

	if( $item_page > 0 ){
		$params .= ( "&ItemPage=" . $item_page );
	}
	
	$params .= ( "&MaximumPrice=" . $max . "&MinimumPrice=" . $min . "&Operation=ItemSearch&ResponseGroup=ItemAttributes%2CSmall%2CImages&SearchIndex=Beauty&Service=AWSECommerceService&Timestamp=" . urlencode( $timestamp ) . "&Version=2009-01-06" );

	$str = "GET\n" . $aws_host . "\n/onca/xml\n" . $params;

	$hash = hash_hmac( "sha256", $str, AWS_SECRET, true );

	$request .= ( $params . "&Signature=" . urlencode( base64_encode( $hash ) ) );

	$opts = array(
		'http'=>array(
			'method'=>'GET',
			'header'=>"User-Agent: ".CRAWLER_USER_AGENT."\r\n" .
				'Host: ' . $aws_host . "\r\n"
		)
	);
	$context = stream_context_create( $opts );
	$r = file_get_contents( $request, false, $context );
//echo "node = $node -> r = $r \n";

	$exc = false;
	try{
		$xml = new SimpleXMLElement( $r );
	}catch( Exception $e ){
		$exc = true;
	}

	if( !$exc && $xml->Items->Item[0] ){
		$totalpages = $xml->Items->TotalPages;
//echo( "totalpages = $totalpages \n" );
		$idx = 0;
		$item = $xml->Items->Item[$idx];
		while( $item != null && $idx < 10 ){
			$image_url = "";
			$manufacturer = "";
			$brand = "";
			$title = "";
			$listprice = "";
			$ean = "";
			$asin = "";
			try{
				$image_url = trimNL($item->MediumImage->URL);
			}catch( Exception $e ){
			}
			try{
				$manufacturer = trimNL($item->ItemAttributes->Manufacturer);
			}catch( Exception $e ){
			}
			try{
				$brand = trimNL($item->ItemAttributes->Brand);
			}catch( Exception $e ){
			}
			try{
				$title = trimNL($item->ItemAttributes->Title);
			}catch( Exception $e ){
			}
			try{
				$listprice = trimNL($item->ItemAttributes->ListPrice->Amount);
			}catch( Exception $e ){
			}
			try{
				$ean = trimNL($item->ItemAttributes->EAN);
			}catch( Exception $e ){
			}
			try{
				$asin = trimNL($item->ASIN);
			}catch( Exception $e ){
			}

			if( $listprice == '' ){
				$listprice = 0;
			}

			//. Records
                        $link = "http://www.amazon.co.jp/dp/" . $asin . "?linkCode=as1&creative=6339";
			if( AWS_ASSOC_TAG ){
				$link .= ( "&tag=" . AWS_ASSOC_TAG );
			}

                        $category = $manufacturer;
                        $content = '<a target="_blank" href="' . $link . '">'
                          . '<img src="' . $image_url . '"/></a><br/>'
                          . '<table border="0">';
                        if( $category ){
                          $content .= '<tr><td>メーカー</td><td>' . $category . '</td></tr>';
			}
                        if( $brand ){
                          $content .= '<tr><td>ブランド</td><td>' . $brand . '</td></tr>';
			}
                        if( $listprice ){
                          $content .= '<tr><td>価格</td><td>' . $listprice . '</td></tr>';
			}
                        if( $ean ){
                          $content .= '<tr><td>商品コード</td><td>' . $ean . '</td></tr>';
			}
                        $content .= '</table>';
                        $record = array( 'ID' => 0, 'title' => $title, 'content' => $content );
			if( $category ){
				$record['category'] = $category;
			}
			if( $brand ){
				$record['tag'] = $brand;
			}
                        $records[] = $record;

			$idx ++;
			try{
				$item = $xml->Items->Item[$idx];
			}catch( Exception $e ){
			}
		}
	}

	return $totalpages;
}

?>



