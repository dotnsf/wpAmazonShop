<?php
require( 'common.php' );

$nodes = array(
	//. https://affiliate.amazon.co.jp/gp/associates/help/t100
//	"52905051",  //. ビューティー/スキンケア
//	"52908051",  //. ビューティー/ヘアケア
//	"52907051",  //. ビューティー/ボディケア
//	"52912051"   //. ビューティー/男性化粧品
	"169772011"  //. ビューティー/オーラルケア/ホワイトニング
//	"5263226051" //. ビューティー/スキンケア・ボディケア/アイケア/アイクリーム
);

//$records = json_decode( file_get_contents( "./records.json" ), true );

foreach( $nodes as $node ){
	getCodesFromAmazonAPI( $node );
}

//file_put_contents( "./records.json", json_encode($records) );

//. $records の中身をデータベースへ
echo( "#records = " . count($records) . "¥n" );
importToWPDB( $records );

exit;


?>

