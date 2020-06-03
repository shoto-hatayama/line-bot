<?php

namespace App\Http\Controllers\api;

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
                \Log::info('ホットペッパーAPIジャンル名取得処理開始');
                $hotpepperAccessKey = env('HOTPEPPER_ACCESS_KEY', "");

                $hotpepperCurl = curl_init();

                $hotpepperCurlOption = array(
                                        CURLOPT_URL => 'https://webservice.recruit.co.jp/hotpepper/genre/v1/?key='.$hotpepperAccessKey.'&format=json',
                                        CURLOPT_RETURNTRANSFER => true,
                                        );
                curl_setopt_array($hotpepperCurl, $hotpepperCurlOption);
                $result = curl_exec($hotpepperCurl);
                curl_close($hotpepperCurl);
                \Log::info('ホットペッパーAPIジャンル名取得処理終了');

                $hotpepperGenreResult = json_decode($result, true);

                //ホットペッパーAPIで取得したジャンル名を10個に絞って配列作成
                $hotpepperGenres = [];
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][0]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][1]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][3]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][4]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][7]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][8]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][12]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][13]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][14]);
                array_push($hotpepperGenres, $hotpepperGenreResult['results']['genre'][15]);

                //カルーセル作成
                $columns = [];
                foreach($hotpepperGenres as $hotpepperGenre){
                    array_push($columns,
                        array(
                            'text'    => $hotpepperGenre['name'],
                            'actions' => array(
                                array('type' => 'postback',
                                      'label' => 'このジャンルにする',
                                      'data' => '&genre='.$hotpepperGenre['code'].'&lat='.$event['message']['latitude'].'&lng='.$event['message']['longitude'].'&range=3&start=1&count=9&format=json' ,
                                      'text' => $hotpepperGenre['name'])
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
                \Log::info('ホットペッパーAPI飲食店取得処理開始');
                $hotpepperAccessKey = env('HOTPEPPER_ACCESS_KEY', "");

                $hotpepperCurl = curl_init();

								//検索結果の取得開始位置算出
								preg_match('/start=([0-9]+|-[0-9]+)/', $event['postback']['data'], $Matches);
								$startNumber = preg_replace('/start=/', '', $Matches[0]);
								$OUTPUT_DATA_NUMBER = config('const.HotPeppar.OUTPUT_DATA_NUMBER');

								//検索結果の取得位置がマイナスの場合、検索結果の初めからデータを取得
								if($startNumber < 0){
										$startNumber = 1;
										$curlUrl = preg_replace('/start=([0-9]+|-[0-9]+)/', 'start=1', 'http://webservice.recruit.co.jp/hotpepper/gourmet/v1/?key='.$hotpepperAccessKey.$event['postback']['data']);
								} else {
										$curlUrl = 'http://webservice.recruit.co.jp/hotpepper/gourmet/v1/?key='.$hotpepperAccessKey.$event['postback']['data'];
								}

                $hotpepperCurlOption = array(
                    CURLOPT_URL => $curlUrl,
                    CURLOPT_RETURNTRANSFER => true,
                );
                curl_setopt_array($hotpepperCurl, $hotpepperCurlOption);
                $result = curl_exec($hotpepperCurl);
                curl_close($hotpepperCurl);
                \Log::info('ホットペッパーAPI飲食店取得処理終了');

								$hotpepperShopResult = json_decode($result, true);
								$hotpepperShops = $hotpepperShopResult['results']['shop'];

                if(!empty($hotpepperShops)){
										//カルーセル作成
										$columns = [];
										foreach($hotpepperShops as $hotpepperShop){

												$shopImage = $hotpepperShop['photo']['pc']['l'] ? $hotpepperShop['photo']['pc']['l'] : Storage::disk('dropbox')->url('no_image.jpg');

												array_push($columns,
														array(
																'thumbnailImageUrl' => $shopImage,
																'text'    => $hotpepperShop['name'],
																'actions' => array(
																		array('type' => 'uri',
																					'label' => '詳細ページへ',
																					'uri' => $hotpepperShop['urls']['pc']),
																		array('type' => 'uri',
																					'label' => 'クーポン',
																					'uri' => $hotpepperShop['coupon_urls']['sp'])
																				)
																		)
												);
										}

										//検索結果の取得終了位置算出
										$endNumber = $startNumber+$OUTPUT_DATA_NUMBER-1;
										if(!($endNumber >= $hotpepperShopResult['results']['results_available'])){
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

            }else{
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
