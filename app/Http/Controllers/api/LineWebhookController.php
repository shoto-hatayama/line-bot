<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use LINE\LINEBot;
use LINE\LINEBot\Constant\HTTPHeader;
use LINE\LINEBot\SignatureValidator;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
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

			// postbackされた時にデータをjsonから配列に変換
			$postBackData = isset($event['postback']['data']) ? json_decode($event['postback']['data'], true) : "";

			if (isset($event['message']['latitude'])) {
				// ジャンル名のカルーセルを表示

				$message = $this->getGenreCarouselList($event['message']['latitude'], $event['message']['longitude']);
			} elseif (isset($postBackData['changePage'])) {
				// ジャンル名カルーセルの切り替えを行う

				$message = $this->getGenreCarouselList($postBackData['latitude'], $postBackData['longitude'], $postBackData['changePage']);
			} elseif (isset($postBackData['latitude'])) {
				// ジャンル名が選択された時の処理

				$foodApi = app()->make('CallFoodApi');

				//APIで情報取得
				$hotpepperResults = $foodApi->getHotpepperShopData(env('HOTPEPPER_ACCESS_KEY', ""), $postBackData);

				//取得した情報の中から店舗情報のみ取得
				$hotpepperShops = $hotpepperResults['results']['shop'];

				$shopDetails = [];
				//ホットペッパーの店舗情報取得
				foreach ($hotpepperShops as $hotpepperShop) {

					$shopImage = $hotpepperShop['photo']['pc']['l'] ? $hotpepperShop['photo']['pc']['l'] : Storage::disk('dropbox')->url('no_image.jpg');

					array_push(
						$shopDetails,
						array(
							'storeId' => $hotpepperShop['id'],
							'shopName' => mb_strimwidth($hotpepperShop['name'], 0, 60, '...', 'utf-8'),
							'infoUrl' => $hotpepperShop['urls']['pc'],
							'imageUrl' => $shopImage,
						)
					);
				}

				if (!empty($shopDetails)) {
					// 飲食店情報一覧のカルーセル作成
					$columns = $this->getShopDetailsCarousel($shopDetails, $postBackData, $hotpepperResults);

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
		if ($changePage === config('const.ChangePage.CHANGE_PAGE_NEXT')) {
			$arrayStartPosition = config('const.StartPosition.NEXT_START_POSITION');
			$itemLimit = config('const.ItemLimit.NEXT_ITEM_LIMIT');
			$carouselLavel = config('const.CarouselLabel.BACK_CAROUSEL_LABEL');
			$carouselText = config('const.CarouselText.BACK_CAROUSEL_TEXT');
			$changePageAction = config('const.ChangePage.CHANGE_PAGE_BACK');
		} elseif ($changePage === config('const.ChangePage.CHANGE_PAGE_BACK')) {
			$arrayStartPosition = config('const.StartPosition.DEFAULT_START_POSITION');
			$itemLimit = config('const.ItemLimit.DEFAULT_ITEM_LIMIT');
			$carouselLavel = config('const.CarouselLabel.NEXT_CAROUSEL_LABEL');
			$carouselText = config('const.CarouselText.NEXT_CAROUSEL_TEXT');
			$changePageAction = config('const.ChangePage.CHANGE_PAGE_NEXT');
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
		if ($changePage === config('const.ChangePage.CHANGE_PAGE_BACK')) {
			// ジャンルを指定しない検索
			array_push(
				$columns,
				array(
					'text' => 'ジャンル指定なし',
					'actions' => array(
						array(
							'type' => 'postback',
							'label' => '選択',
							'data' => '{"latitude":"' . $latitude . '","longitude":"' . $longitude . '","range": 5,"hotpepperListStart": 1,"hotpepperListCount": 8}',
							'text' => 'ジャンル指定なし'
						)
					)
				)
			);
		}

		foreach ($shopGenres as $key => $shopGenre) {
			// ジャンルを指定した検索
			array_push(
				$columns,
				array(
					'text'    => $shopGenre['name'],
					'actions' => array(
						array(
							'type' => 'postback',
							'label' => '選択',
							'data' => '{"hotpepperGenreCode":"' . $shopGenre['code'] . '","latitude":"' . $latitude . '","longitude":"' . $longitude . '","range": 5,"hotpepperListStart": 1,"hotpepperListCount": 8}',
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

		return $message;
	}

	/**
	 * 飲食店情報一覧のカルーセル作成
	 *
	 * @param array $shopDetails
	 * @param array $postBackData
	 * @param array $hotpepperResults
	 * @return array $columns
	 */
	private function getShopDetailsCarousel($shopDetails, $postBackData, $hotpepperResults)
	{

		//カルーセル作成
		$columns = [];
		// 「前に戻る」カルーセル作成
		if (!($hotpepperResults['results']['results_start'] == config('const.HOTPEPPER_RESULTS_START'))) {
			$columns = $this->makeChangePageCarousel($columns, $postBackData, config('const.ChangePage.CHANGE_PAGE_BACK'));
		}

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
							'uri' => url('/detail', [$shopDetail['storeId']])
						),
						array(
							'type' => 'uri',
							'label' => 'googleMapを開く',
							'uri' => 'https://www.google.com/maps/search/?api=1&query=' . str_replace([" ", "`"], "", $shopDetail['shopName']) //スペースとバッククオートが入るとエラーになるため消す

						),
					)
				)
			);
		}
		//検索結果の取得終了位置算出
		$hotpepperListEnd = $postBackData['hotpepperListStart'] + $postBackData['hotpepperListCount'] - 1;
		// 「次へ進む」カルーセル作成
		if (!($hotpepperListEnd >= $hotpepperResults['results']['results_available'])) {
			$columns = $this->makeChangePageCarousel($columns, $postBackData, config('const.ChangePage.CHANGE_PAGE_NEXT'));
		}

		return $columns;
	}

	/**
	 * 「次へ進む」または「前に戻る」カルーセルを追加する
	 *
	 * @param array $columns
	 * @param array] $postBackData
	 * @param string $changeType
	 * @return array $columns
	 */
	private function makeChangePageCarousel($columns, $postBackData, $changePage)
	{

		// 飲食店情報の開始位置を次のリスト取得用に設定
		$pageNext = function ($postBackData) {
			$postBackData['hotpepperListStart'] += $postBackData['hotpepperListCount'];
			return $postBackData;
		};
		// 飲食店情報の開始位置を前のリスト取得用に設定
		$pageBack = function ($postBackData) {
			$postBackData['hotpepperListStart'] -= $postBackData['hotpepperListCount'];
			return $postBackData;
		};

		if ($changePage === config('const.ChangePage.CHANGE_PAGE_NEXT')) {
			// 「次のページ」用
			$imgName = config('const.PageImg.NEXT_PAGE_IMG');
			$labelName = '次のページへ';
			$data = $pageNext($postBackData);
			$carouselText = config('const.CarouselText.NEXT_CAROUSEL_TEXT');
		} elseif ($changePage === config('const.ChangePage.CHANGE_PAGE_BACK')) {
			// 「前のページ」用
			$imgName = config('const.PageImg.BACK_PAGE_IMG');
			$labelName = '前のページへ';
			$data = $pageBack($postBackData);
			$carouselText = config('const.CarouselText.BACK_CAROUSEL_TEXT');
		} else {
			\Log::info("ページの切り替えタイプが不正です。");
			throw new \Exception;
		}

		// カルーセルの追加
		array_push(
			$columns,
			array(
				'thumbnailImageUrl' => Storage::disk('dropbox')->url($imgName),
				'text'    => $carouselText,
				'actions' => array(
					array(
						'type' => 'postback',
						'label' => $labelName,
						'data' => json_encode($data)
					),
					array(
						'type' => 'postback',
						'label' => 'ジャンル選択',
						'data' => '{"latitude":"' . $postBackData['latitude'] . '","longitude":"' . $postBackData['longitude'] . '","changePage":"' .  config('const.ChangePage.CHANGE_PAGE_BACK') . '"}'
					),
				)
			)
		);

		return $columns;
	}
}
