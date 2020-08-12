<?php
namespace App\Services;

class CallFoodApi{

    //HOTPEPPER分類名取得
    public static function getHotpepperGenre($accessKey){
        try {
            \Log::info('HOTPEPPER分類取得処理開始');
            $curl = curl_init();

            $curlOption = array(
                CURLOPT_URL => 'https://webservice.recruit.co.jp/hotpepper/genre/v1/?key='.$accessKey.'&format=json',
                CURLOPT_RETURNTRANSFER => true,
                );
            curl_setopt_array($curl, $curlOption);
            $result = curl_exec($curl);

            curl_close($curl);

            \Log::info('HOTPEPPER分類取得処理終了');
            return json_decode($result, true);
        } catch (Exception $e) {
            \Log::info('HOTPEPPER分類取得でエラーが発生しました。');
            \Log::info($e);
        }
    }

    //ぐるなび大分類名取得
    public static function getGnaviLargeGenre($accessKey){
        try {
            \Log::info('ぐるなび大分類取得処理開始');
            $curl = curl_init();

            $curlOption = array(
                CURLOPT_URL => 'https://api.gnavi.co.jp/master/CategoryLargeSearchAPI/v3//?keyid='.$accessKey,
                CURLOPT_RETURNTRANSFER => true,
                );
            curl_setopt_array($curl, $curlOption);
            $result = curl_exec($curl);

            curl_close($curl);

            \Log::info('ぐるなび大分類取得処理終了');
            return json_decode($result, true);
        } catch (Exception $e) {
            \Log::info('ぐるなび大分類取得でエラーが発生しました。');
            \Log::info($e);
        }
    }

    //ぐるなび小分類名取得
    public static function getGnaviSmallGenre($accessKey){
        try {
            \Log::info('ぐるなび小分類取得処理開始');
            $curl = curl_init();

            $curlOption = array(
                CURLOPT_URL => 'https://api.gnavi.co.jp/master/CategorySmallSearchAPI/v3/?keyid='.$accessKey,
                CURLOPT_RETURNTRANSFER => true,
                );
            curl_setopt_array($curl, $curlOption);
            $result = curl_exec($curl);

            curl_close($curl);

            \Log::info('ぐるなび小分類取得処理終了');
            return json_decode($result, true);
        } catch (Exception $e) {
            \Log::info('ぐるなび小分類取得でエラーが発生しました。');
            \Log::info($e);
        }
    }

}