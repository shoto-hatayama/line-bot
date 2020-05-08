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
            $reply_token = $event['replyToken'];

            $headers = array('Content-Type: application/json',
                            'Authorization: Bearer '. $lineAccessToken);

            $message = '';

            //配列をJSONにエンコード
            $body =json_encode(array('replyToken' => $reply_token,
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
