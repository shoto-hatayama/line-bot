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

	public function webhook(Request $request)
	{

		$lineAccessToken = env('LINE_ACCESS_TOKEN', "");
		$lineChannelSecret = env('LINE_CHANNEL_SECRET', "");

		// 署名のチェック
		$signature = $request->headers->get(HTTPHeader::LINE_SIGNATURE);
		if (!SignatureValidator::validateSignature($request->getContent(), $lineChannelSecret, $signature)) {
			return;
		}

		$httpClient = new CurlHTTPClient($lineAccessToken);
		$lineBot = new LINEBot($httpClient, ['channelSecret' => $lineChannelSecret]);

		try {
			//JSON取得
			$raw = file_get_contents('php://input');
			$receive = json_decode($raw, true);

			$event = $receive['events'][0];
			$replyToken = $event['replyToken'];

			$headers = array(
				'Content-Type: application/json',
				'Authorization: Bearer ' . $lineAccessToken
			);

			$foodApi = app()->make('CallFoodApi');
			if (isset($event['message']['latitude'])) {

				// ジャンル名のカルーセルを表示
				$message = $this->getGenreCarouselList($event['message']['latitude'], $event['message']['longitude']);
			} elseif (isset($event['postback']['data'])) {

				//選択されたジャンルをもとに飲食店を検索する
				$genreData = json_decode($event['postback']['data'], true);

				//検索結果の取得位置がマイナスの場合、検索結果の初めからデータを取得
				if ($genreData['hotpepperListStart'] < 0 || $genreData['gnaviListStart'] < 0) {
					$genreData['hotpepperListStart'] = 1;
					$genreData['gnaviListStart'] = 1;
				}

				//それぞれのAPIから情報取得
				$gnaviResults = $foodApi->getGnaviShopData(env('GNAVI_ACCESS_KEY', ""), $genreData);
				$hotpepperResults = $foodApi->getHotpepperShopData(env('HOTPEPPER_ACCESS_KEY', ""), $genreData);

				//取得した情報の中から店舗情報のみ取得
				$gnaviShops = isset($gnaviResults['rest']) ? $gnaviResults['rest'] : [];
				$hotpepperShops = $hotpepperResults['results']['shop'];

				$shopDetails = [];
				//ホットペッパーの店舗情報取得
				foreach ($hotpepperShops as $hotpepperShop) {

					$shopImage = $hotpepperShop['photo']['pc']['l'] ? $hotpepperShop['photo']['pc']['l'] : Storage::disk('dropbox')->url('no_image.jpg');

					array_push(
						$shopDetails,
						array(
							'shopName' => $hotpepperShop['name'],
							'infoUrl' => $hotpepperShop['urls']['pc'],
							'couponUrl' => $hotpepperShop['coupon_urls']['sp'],
							'imageUrl' => $shopImage,
						)
					);
				}
				//ぐるなびの店舗情報取得
				foreach ($gnaviShops as $gnaviShop) {

					$shopImage = $gnaviShop['image_url']['shop_image1'] ? $gnaviShop['image_url']['shop_image1'] : Storage::disk('dropbox')->url('no_image.jpg');
					$couponUrl = $gnaviShop['coupon_url']['mobile'] ? $gnaviShop['coupon_url']['mobile'] : $gnaviShop['url_mobile'] . '/coupon';

					array_push(
						$shopDetails,
						array(
							'shopName' => $gnaviShop['name'],
							'infoUrl' => $gnaviShop['url_mobile'],
							'couponUrl' => $couponUrl,
							'imageUrl' => $shopImage,
						)
					);
				}

				if (!empty($shopDetails)) {
					//カルーセル作成
					$columns = [];
					foreach ($shopDetails as $shopDetail) {

						array_push(
							$columns,
							array(
								'thumbnailImageUrl' => $shopDetail['imageUrl'],
								'text'    => $shopDetail['shopName'],
								'actions' => array(
									array(
										'type' => 'uri',
										'label' => '詳細ページへ',
										'uri' => $shopDetail['infoUrl']
									),
									array(
										'type' => 'uri',
										'label' => 'クーポン',
										'uri' => $shopDetail['couponUrl']
									)
								)
							)
						);
					}

					//検索結果の取得終了位置算出
					$hotpepperListEnd = $genreData['hotpepperListStart'] + $genreData['hotpepperListCount'] - 1;
					$gnaviListEnd = $genreData['gnaviListStart'] + $genreData['gnaviListCount'] - 1;

					$pageNext = function ($genreData) {
						$genreData['hotpepperListStart'] += $genreData['hotpepperListCount'];
						$genreData['gnaviListStart'] += $genreData['gnaviListCount'];
						return $genreData;
					};
					$pageBack = function ($genreData) {
						$genreData['hotpepperListStart'] -= $genreData['hotpepperListCount'];
						$genreData['gnaviListStart'] -= $genreData['gnaviListCount'];
						return $genreData;
					};

					if (!($hotpepperListEnd >= $hotpepperResults['results']['results_available']) || !($gnaviListEnd >= $gnaviResults['total_hit_count'])) {
						array_push(
							$columns,
							array(
								'thumbnailImageUrl' => Storage::disk('dropbox')->url('page_change.jpg'),
								'text'    => 'sampletext',
								'actions' => array(
									array(
										'type' => 'postback',
										'label' => '前のページへ',
										'data' => json_encode($pageBack($genreData))
									),
									array(
										'type' => 'postback',
										'label' => '次のページへ',
										'data' => json_encode($pageNext($genreData))
									)
								)
							)
						);
					}

					$template = array(
						'type'    => 'carousel',
						'columns'  => $columns,
					);

					$message = array(
						'type'     => 'template',
						'altText'  => '検索結果',
						'template' => $template

					);
				} else {

					$message = array(
						'type'		=>	'text',
						'text' 	=>	'お店が見つかりませんでした。'
					);
				}
			} else {
				return;
			}
			//配列をJSONにエンコード
			$body = json_encode(array(
				'replyToken' => $replyToken,
				'messages' => array($message)
			));

			\Log::info('送信処理開始');
			$options = array(
				CURLOPT_URL => 'https://api.line.me/v2/bot/message/reply',
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_POSTFIELDS => $body
			);
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


	/**
	 * ジャンル名のカルーセルを取得する
	 *
	 * @param int $latitude 緯度
	 * @param int $longitude　経度
	 * @param string $changePage backまたはnextを記載
	 * @return array $message
	 */
	private function getGenreCarouselList($latitude, $longitude, $changePage = "back")
	{
		// 表示するジャンル名の個数と取得位置を変数に格納
		if ($changePage === env("CHANGE_PAGE_NEXT", "")) {
			$arrayStartPosition = env("NEXT_START_POSITION", "");
			$itemLimit = env("NEXT_ITEM_LIMIT");
			$carouselLavel = env('BACK_CAROUSEL_LABEL', "");
			$carouselText = env('BACK_CAROUSEL_TEXT', "");
			$changePageAction = env('CHANGE_PAGE_BACK', "");
		} elseif ($changePage === env("CHANGE_PAGE_BACK", "")) {
			$arrayStartPosition = env("DEFAULT_START_POSITION", "");
			$itemLimit = env("DEFAULT_ITEM_LIMIT", "");
			$carouselLavel = env('NEXT_CAROUSEL_LABEL', "");
			$carouselText = env('NEXT_CAROUSEL_TEXT', "");
			$changePageAction = env('CHANGE_PAGE_NEXT', "");
		} else {
			\Log::info("不正な値が入力されました。");
			throw new Exception;
		}

		$foodApi = app()->make('CallFoodApi');
		//位置情報が送信されたときジャンル名一覧をカルーセルで表示
		$hotpepperGenreResult = $foodApi->getHotpepperGenre(env('HOTPEPPER_ACCESS_KEY', ""));

		// ホットペッパーAPIで取得したジャンル名を指定の個数だけ配列作成
		$shopGenres = [];
		for ($i = $arrayStartPosition; $i < $itemLimit; $i++) {
			array_push($shopGenres, $hotpepperGenreResult['results']['genre'][$i]);
		}

		//カルーセル作成
		$columns = [];
		foreach ($shopGenres as $key => $shopGenre) {
			array_push(
				$columns,
				array(
					'text'    => $shopGenre['name'],
					'actions' => array(
						array(
							'type' => 'postback',
							'label' => 'このジャンルにする',
							'data' => '{"hotpepperGenreCode":"' . $shopGenre['code'] . '","latitude":"' . $latitude . '","longitude":"' . $longitude . '","range": 5,"hotpepperListStart": 1,"hotpepperListCount": 9}',
							'text' => $shopGenre['name']
						)
					)
				)
			);
		}
		// 「次へ」または「戻る」を表示するカルーセル作成
		array_push(
			$columns,
			array(
				'text'		=> $carouselText,
				'actions'	=> array(
					array(
						'type' => 'postback',
						'label' => $carouselLavel,
						'data' =>	'{"latitude":"' . $latitude . '","longitude":"' . $longitude . '","changePage":"' . $changePageAction . '"}'
					)
				)
			)
		);

		$template = array(
			'type'    => 'carousel',
			'columns'  => $columns,
		);

		$message = array(
			'type'     => 'template',
			'altText'  => '検索結果',
			'template' => $template
		);
		\Log::info($message);
		return $message;
	}
}
