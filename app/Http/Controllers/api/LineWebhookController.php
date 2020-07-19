<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\SignatureValidator;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use Illuminate\Support\Facades\Storage;
use Exception;

class LineWebhookController extends Controller
{

    public function webhook (Request $request)
    {

        $lineAccessToken = env('LINE_ACCESS_TOKEN', "");
        $lineChannelSecret = env('LINE_CHANNEL_SECRET', "");

        // 署名のチェック
        $signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE);
        if (!SignatureValidator::validateSignature($request->getContent(), $lineChannelSecret, $signature)) {
            return;
        }

        $httpClient = new CurlHTTPClient ($lineAccessToken);
        $lineBot = new LINEBot($httpClient, ['channelSecret' => $lineChannelSecret]);

        try {
            //JSON取得
            $raw = file_get_contents('php://input');
            $receive = json_decode($raw, true);

            $event = $receive['events'][0];
            $replyToken = $event['replyToken'];

            $headers = array('Content-Type: application/json',
                            'Authorization: Bearer '. $lineAccessToken);

            if(isset($event['message']['latitude'])){

                //位置情報が送信されたときジャンル名一覧をカルーセルで表示
                \Log::info('ジャンル名取得処理開始');
                //ホットペッパージャンル取得
                $hotpepperAccessKey = env('HOTPEPPER_ACCESS_KEY', "");

                $hotpepperCurl = curl_init();

                $hotpepperCurlOption = array(
                                        CURLOPT_URL => 'https://webservice.recruit.co.jp/hotpepper/genre/v1/?key='.$hotpepperAccessKey.'&format=json',
                                        CURLOPT_RETURNTRANSFER => true,
                                        );
                curl_setopt_array($hotpepperCurl, $hotpepperCurlOption);
                $hotpepperResult = curl_exec($hotpepperCurl);
                curl_close($hotpepperCurl);

                //ぐるなび大ジャンル取得
                $gnaviAccessKey = env('GNAVI_ACCESS_KEY', "");

                $gnaviCurl = curl_init();

                $gnaviCurlOption = array(
                                        CURLOPT_URL => 'https://api.gnavi.co.jp/master/CategoryLargeSearchAPI/v3//?keyid='.$gnaviAccessKey,
                                        CURLOPT_RETURNTRANSFER => true,
                                        );
                curl_setopt_array($gnaviCurl, $gnaviCurlOption);
                $gnaviCategoryLargeResult = curl_exec($gnaviCurl);
                curl_close($gnaviCurl);

                //ぐるなび小ジャンル取得
                $gnaviCurl = curl_init();

                $gnaviCurlOption = array(
                                        CURLOPT_URL => 'https://api.gnavi.co.jp/master/CategorySmallSearchAPI/v3/?keyid='.$gnaviAccessKey,
                                        CURLOPT_RETURNTRANSFER => true,
                                        );
                curl_setopt_array($gnaviCurl, $gnaviCurlOption);
                $gnaviCategorySmallResult = curl_exec($gnaviCurl);
                curl_close($gnaviCurl);

                \Log::info('ジャンル名取得処理終了');

                $gnaviGenreLargeResult = json_decode($gnaviCategoryLargeResult, true);
                $gnaviGenreSmallResult = json_decode($gnaviCategorySmallResult, true);
                $hotpepperGenreResult = json_decode($hotpepperResult, true);

								//ホットペッパーAPIとぐるなびAPIで取得したジャンル名を10個に絞って配列作成
                $shopGenres = [];
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][0]['category_l_code'] => $hotpepperGenreResult['results']['genre'][0]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][16]['category_l_code'] => $hotpepperGenreResult['results']['genre'][1]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][6]['category_l_code'] => $hotpepperGenreResult['results']['genre'][3]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][11]['category_l_code'] => $hotpepperGenreResult['results']['genre'][4]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][4]['category_l_code'] => $hotpepperGenreResult['results']['genre'][7]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreSmallResult['category_s'][131]['category_s_code'] => $hotpepperGenreResult['results']['genre'][8]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][17]['category_l_code'] => $hotpepperGenreResult['results']['genre'][12]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][8]['category_l_code'] => $hotpepperGenreResult['results']['genre'][13]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][7]['category_l_code'] => $hotpepperGenreResult['results']['genre'][14]));
                $shopGenres = array_merge($shopGenres, array($gnaviGenreLargeResult['category_l'][18]['category_l_code'] => $hotpepperGenreResult['results']['genre'][15]));

								$latitude = $event['message']['latitude'];
								$longitude = $event['message']['longitude'];
                //カルーセル作成
                $columns = [];
                foreach($shopGenres as $key => $shopGenre){
                    array_push($columns,
                        array(
                            'text'    => $shopGenre['name'],
                            'actions' => array(
                                array('type' => 'postback',
                                      'label' => 'このジャンルにする',
																			'data' => '{"hotpepperGenreCode":"'.$shopGenre['code'].'","gnaviGenreCode":"'.$key.'","latitude":"'.$latitude.'","longitude":"'.$longitude.'","range": 5,"hotpepperListStart": 1,"gnaviListStart": 1,"hotpepperListCount": 5,"gnaviListCount": 4}',
																			'text' => $shopGenre['name'])
                            )
                        )
                    );
                }

                $template = array('type'    => 'carousel',
                                               'columns'  => $columns,
                                            );

                $message = array('type'     => 'template',
                                               'altText'  => '検索結果',
                                               'template' => $template
                                            );

            } elseif (isset($event['postback']['data'])){
								//選択されたジャンルをもとに飲食店を検索する
								$genreData = json_decode($event['postback']['data'], true);

								\Log::info('飲食店情報取得処理開始');
                $hotpepperAccessKey = env('HOTPEPPER_ACCESS_KEY', "");
								$gnaviAccessKey = env('GNAVI_ACCESS_KEY', "");

								$hotpepperCurl = curl_init();
								$gnaviCurl = curl_init();

								//検索結果の取得位置がマイナスの場合、検索結果の初めからデータを取得
								if($genreData['hotpepperListStart'] < 0 || $genreData['gnaviListStart'] < 0){
                    $genreData['hotpepperListStart'] = 1;
                    $genreData['gnaviListStart'] = 1;
								}

								//ぐるなびのジャンルコードが大分類か小分類か判定してURLを作成
								$genreCodeLast3Digits = substr($genreData['gnaviGenreCode'], -3);
								if($genreCodeLast3Digits == '000'){
										$gnaviCurlUrl = 'https://api.gnavi.co.jp/RestSearchAPI/v3/?keyid='.$gnaviAccessKey.'&category_l='.$genreData['gnaviGenreCode'].'&latitude='.$genreData['latitude'].'&longitude='.$genreData['longitude'].'&range='.$genreData['range'].'&offset='.$genreData['gnaviListStart'].'&hit_per_page='.$genreData['gnaviListCount'];
								} else {
										$gnaviCurlUrl = 'https://api.gnavi.co.jp/RestSearchAPI/v3/?keyid='.$gnaviAccessKey.'&category_s='.$genreData['gnaviGenreCode'].'&latitude='.$genreData['latitude'].'&longitude='.$genreData['longitude'].'&range='.$genreData['range'].'&offset='.$genreData['gnaviListStart'].'&hit_per_page='.$genreData['gnaviListCount'];
								}

								$hotpepperCurlUrl = 'http://webservice.recruit.co.jp/hotpepper/gourmet/v1/?key='.$hotpepperAccessKey.'&genre='.$genreData['hotpepperGenreCode'].'&lat='.$genreData['latitude'].'&lng='.$genreData['longitude'].'&range='.$genreData['range'].'&start='.$genreData['hotpepperListStart'].'&count='.$genreData['hotpepperListCount'].'&format=json';

								$hotpepperCurlOption = array(
									CURLOPT_URL => $hotpepperCurlUrl,
									CURLOPT_RETURNTRANSFER => true,
								);
                $gnaviCurlOption = array(
                    CURLOPT_URL => $gnaviCurlUrl,
                    CURLOPT_RETURNTRANSFER => true,
								);

								curl_setopt_array($hotpepperCurl, $hotpepperCurlOption);
								curl_setopt_array($gnaviCurl, $gnaviCurlOption);

								$hotpepperResults = json_decode(curl_exec($hotpepperCurl), true);
								$gnaviResults = json_decode(curl_exec($gnaviCurl), true);

								curl_close($hotpepperCurl);
								curl_close($gnaviCurl);

                \Log::info('飲食店情報取得処理終了');

								$hotpepperShops = $hotpepperResults['results']['shop'];
								$gnaviShops = isset($gnaviResults['rest']) ? $gnaviResults['rest'] : [];

                $shopDetails = [];
                //ホットペッパーの店舗情報取得
								foreach($hotpepperShops as $hotpepperShop){

                    $shopImage = $hotpepperShop['photo']['pc']['l'] ? $hotpepperShop['photo']['pc']['l'] : Storage::disk('dropbox')->url('no_image.jpg');

										array_push($shopDetails,
												array(
														'shopName' => $hotpepperShop['name'],
														'infoUrl' => $hotpepperShop['urls']['pc'],
														'couponUrl' => $hotpepperShop['coupon_urls']['sp'],
														'imageUrl' => $shopImage,
														)
											);
                }
                //ぐるなびの店舗情報取得
                foreach($gnaviShops as $gnaviShop){

                    $shopImage = $gnaviShop['image_url']['shop_image1'] ? $gnaviShop['image_url']['shop_image1'] : Storage::disk('dropbox')->url('no_image.jpg');
                    $couponUrl = $gnaviShop['coupon_url']['mobile'] ? $gnaviShop['coupon_url']['mobile'] : $gnaviShop['url_mobile'].'/coupon';

                    array_push($shopDetails,
                        array(
                          'shopName' => $gnaviShop['name'],
                          'infoUrl' => $gnaviShop['url_mobile'],
                          'couponUrl' => $couponUrl,
                          'imageUrl' => $shopImage,
                        )
                      );
                }

                if(!empty($shopDetails)){
										//カルーセル作成
										$columns = [];
										foreach($shopDetails as $shopDetail){

												array_push($columns,
														array(
																'thumbnailImageUrl' => $shopDetail['imageUrl'],
																'text'    => $shopDetail['shopName'],
																'actions' => array(
																		array('type' => 'uri',
																					'label' => '詳細ページへ',
																					'uri' => $shopDetail['infoUrl']),
																		array('type' => 'uri',
																					'label' => 'クーポン',
																					'uri' => $shopDetail['couponUrl'])
																				)
																		)
												);
										}

										//検索結果の取得終了位置算出
                    $hotpepperListEnd = $genreData['hotpepperListStart']+$genreData['hotpepperListCount']-1;
                    $gnaviListEnd = $genreData['gnaviListStart']+$genreData['gnaviListCount']-1;

										if(!($hotpepperListEnd >= $hotpepperResults['results']['results_available']) && !($gnaviListEnd >= $gnaviResults['total_hit_count'])){
												array_push($columns,
																array(
																		'thumbnailImageUrl' => Storage::disk('dropbox')->url('page_change.jpg'),
																		'text'    => 'sampletext',
																		'actions' => array(
																						array('type' => 'postback',
																									'label' => '前のページへ',
																									'data' => preg_replace('/start=([0-9]+|-[0-9]+)/', 'start='.($startNumber-$OUTPUT_DATA_NUMBER), $event['postback']['data'])),
																						array('type' => 'postback',
																									'label' => '次のページへ',
																									'data' => preg_replace('/start=([0-9]+|-[0-9]+)/', 'start='.($startNumber+$OUTPUT_DATA_NUMBER), $event['postback']['data']))
																)
														)
												);
										}

										$template = array('type'    => 'carousel',
																									 'columns'  => $columns,
																										);

										$message = array('type'     => 'template',
																									'altText'  => '検索結果',
																									'template' => $template

																										);

								} else {

										$message = array('type'		=>	'text',
																		 'text' 	=>	'近くにお店がないから他を探してね！'
																		);
								}

            } else {
                return;
            }
            //配列をJSONにエンコード
            $body =json_encode(array('replyToken' => $replyToken,
                                     'messages' => array($message)));

            \Log::info('送信処理開始');
            $options = array(CURLOPT_URL => 'https://api.line.me/v2/bot/message/reply',
                                CURLOPT_CUSTOMREQUEST => 'POST',
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => $headers,
                                CURLOPT_POSTFIELDS => $body);
            $curl = curl_init();
            curl_setopt_array($curl, $options);
            curl_exec($curl);
            curl_close($curl);
            \Log::info('送信処理終了');


        } catch (Exception $e) {
            \Log::info('処理がエラーになりました。');
            \Log::info($e);
            return;
        }

        return;
    }

}
