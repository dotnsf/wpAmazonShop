<?php

require( 'credentials.php' );

function deletePostByID( $id ){
	$pdo = new PDO( 'mysql:dbname='.DB_NAME.';host='.DB_HOST.';charset=utf8', DB_USER, DB_PASSWORD );

	if( $pdo != null ){
		$pdo->query( 'SET NAMES utf8' );

		try{
			$sql = "delete from " . TABLE_PREFIX . "posts where ID = :id";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':id', $id, PDO::PARAM_INT);
			$r = $stmt->execute();
		}catch( Exception $e ){
			echo( " exception -> " . $e->getMessage() . "\n" );
		}catch( PDOException $e ){
			echo( " pdoexception -> " . $e->getMessage() . "\n" );
		}catch( SQLException $e ){
			echo( " sqlexception -> " . $e->getMessage() . "\n" );
		}
	}
}

function setPermalinkStructure( $structure ){
	$pdo = new PDO( 'mysql:dbname='.DB_NAME.';host='.DB_HOST.';charset=utf8', DB_USER, DB_PASSWORD );

	if( $pdo != null ){
		$pdo->query( 'SET NAMES utf8' );

		try{
			$sql = "update " . TABLE_PREFIX . "options set option_value = :option_value where option_name = 'permalink_structure'";
			$stmt = $pdo->prepare($sql);
			$stmt->bindParam(':option_value', $structure, PDO::PARAM_STR);
			$r = $stmt->execute();
		}catch( Exception $e ){
			echo( " exception -> " . $e->getMessage() . "\n" );
		}catch( PDOException $e ){
			echo( " pdoexception -> " . $e->getMessage() . "\n" );
		}catch( SQLException $e ){
			echo( " sqlexception -> " . $e->getMessage() . "\n" );
		}
	}
}

function importToWPDB( $recordArray ){
	deletePostByID( 1 );
	setPermalinkStructure( '' );

	//. $records の中身をデータベースへ
	$pdo = new PDO( 'mysql:dbname='.DB_NAME.';host='.DB_HOST.';charset=utf8', DB_USER, DB_PASSWORD );

	if( $pdo != null ){
		$pdo->query( 'SET NAMES utf8' );

	        //. (1) 全レコードの title, content を wp_posts テーブルの post_title, post_content にインサートして、同時にその際に生成された ID を記録
		//. (2) 全レコードの category をダブりなく取り出す
		$categorys = array();
		$tags = array();
		for( $i = 0; $i < count( $recordArray ); $i ++ ){
			$title = $recordArray[$i]['title'];
			$category = $recordArray[$i]['category'];
			$tag = $recordArray[$i]['tag'];
			$content = $recordArray[$i]['content'];

			if( $category ){
				if( $categorys[$category] ){
					$categorys[$category] = 1;
				}else{
					$categorys[$category] ++;
				}
			}
			if( $tag ){
				if( $tags[$tag] ){
					$tags[$tag] = 1;
				}else{
					$tags[$tag] ++;
				}
			}

			//. Insert
			try{
				$sql = "insert into " . TABLE_PREFIX . "posts(post_title, post_content, post_date ) values( :title, :content, :post_date )";
				$stmt = $pdo->prepare($sql);
				$stmt->bindParam(':title', $title, PDO::PARAM_STR);
				$stmt->bindParam(':content', $content, PDO::PARAM_STR);
				$stmt->bindParam(':post_date', date("Y-m-d H:i:s"), PDO::PARAM_STR);
				$r = $stmt->execute();
				echo( "(1)($i) : " . $title . " -> " . $r . "\n" );

				//. 直前の insert で挿入された ID を取得
				if( $r ){
					$sql0 = "select last_insert_id()as id from " . TABLE_PREFIX . "posts";
					$stmt0 = $pdo->query( $sql0 );
					if( $row = $stmt0->fetch( PDO::FETCH_ASSOC ) ){
						$ID = ( int )$row['id'];
						echo( "(1)($i) :  -> " . $ID . "\n" );
						$recordArray[$i]['ID'] = $ID;
					}
				}
			}catch( Exception $e ){
				echo( "(1)($i) : exception -> " . $e->getMessage() . "\n" );
			}catch( PDOException $e ){
				echo( "(1)($i) : pdoexception -> " . $e->getMessage() . "\n" );
			}catch( SQLException $e ){
				echo( "(1)($i) : sqlexception -> " . $e->getMessage() . "\n" );
			}

		}
        

		//. (3) (2) の結果を wp_terms テーブルの name と slug にインサートして、同時にその際に生成された term_id を記録
		$terms = array();
		while( list( $category, $num ) = each( $categorys ) ){
			//. Insert
			try{
				$sql = "insert into " . TABLE_PREFIX . "terms( name, slug ) values( :name, :slug )";
				$stmt = $pdo->prepare($sql);
				$stmt->bindParam(':name', $category, PDO::PARAM_STR);
				$stmt->bindParam(':slug', urlencode($category), PDO::PARAM_STR);
				$r = $stmt->execute();
				echo( "(3)(" . $category . "): -> " . $r . "\n" );

				//. 直前の insert で挿入された ID を取得
				if( $r ){
					$sql0 = "select last_insert_id() as term_id from " . TABLE_PREFIX . "terms";
					$stmt0 = $pdo->query( $sql0 );
					if( $row = $stmt0->fetch( PDO::FETCH_ASSOC ) ){
						$term_id = ( int )$row['term_id'];
						echo( "(3)(" . $category . ") :  -> " . $term_id . "\n" );

						//. (4) (3) の結果を wp_term_taxonomy テーブルの term_id にインサートして、同時にその際に生成された term_taxonomy_id を記録
						$sql1 = "insert into " . TABLE_PREFIX . "term_taxonomy( term_id, taxonomy, count ) values( :term_id, 'category', :count )";
						$stmt1 = $pdo->prepare($sql1);
						$stmt1->bindParam(':term_id', $term_id, PDO::PARAM_STR);
						$stmt1->bindParam(':count', $num , PDO::PARAM_INT);
						$r = $stmt1->execute();
						echo( "(4)(" . $category . ") :  -> " . $r . "\n" );

						//. 直前の insert で挿入された ID を取得
						if( $r ){
							$sql2 = "select last_insert_id() as term_taxonomy_id from " . TABLE_PREFIX . "term_taxonomy";
							$stmt2 = $pdo->query( $sql2 );
							if( $row = $stmt2->fetch( PDO::FETCH_ASSOC ) ){
								$term_taxonomy_id = ( int )$row['term_taxonomy_id'];
								$terms[$category] = $term_taxonomy_id;
							}
						}
					}
				}
			}catch( Exception $e ){
				echo( "(3)($i) : exception -> " . $e->getMessage() . "\n" );
			}catch( PDOException $e ){
				echo( "(3)($i) : pdoexception -> " . $e->getMessage() . "\n" );
			}catch( SQLException $e ){
				echo( "(3)($i) : sqlexception -> " . $e->getMessage() . "\n" );
			}
		}


		//. (5) (1) の結果と (4) の結果を突き合わせて wp_posts.ID と wp_term_taxonomy.term_taxonomy_id の全ペアを wp_term_relationships テーブルの object_id, term_taxonomy_id にインサートする
		for( $i = 0; $i < count( $recordArray ); $i ++ ){
			$ID = $recordArray[$i]['ID'];
			$category = $recordArray[$i]['category'];
			if( $category ){
				$term_taxonomy_id = $terms[$category];  // 'undefined'??

				//. Insert
				try{
					$sql = "insert into " . TABLE_PREFIX . "term_relationships( object_id, term_taxonomy_id ) values( :object_id, :term_taxonomy_id )";
					$stmt = $pdo->prepare($sql);
					$stmt->bindParam(':object_id', $ID, PDO::PARAM_STR);
					$stmt->bindParam(':term_taxonomy_id', $term_taxonomy_id, PDO::PARAM_STR);
					$r = $stmt->execute();
					echo( "(5)($i) : " . $ID . " -> " . $r . "\n" );
				}catch( Exception $e ){
					echo( "(5)($i) : exception -> " . $e->getMessage() . "\n" );
				}catch( PDOException $e ){
					echo( "(5)($i) : pdoexception -> " . $e->getMessage() . "\n" );
				}catch( SQLException $e ){
					echo( "(5)($i) : sqlexception -> " . $e->getMessage() . "\n" );
				}
			}
		}
	}
}

?>

