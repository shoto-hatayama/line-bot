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

    //ぐるなびAPIから飲食店情報を取得
    public static function getGnaviShopData($accessKey, $genreData){
        try {
            \Log::info('ぐるなびAPI飲食店情報取得処理開始');
            $curl = curl_init();

            $gnaviParam = substr($genreData['gnaviGenreCode'], -3) == '000' ? "&category_l" : "&category_s";
            $curlUrl =  'https://api.gnavi.co.jp/RestSearchAPI/v3/?keyid='.$accessKey.$gnaviParam.$genreData['gnaviGenreCode'].'&latitude='.$genreData['latitude'].'&longitude='.$genreData['longitude'].'&range='.$genreData['range'].'&offset='.$genreData['gnaviListStart'].'&hit_per_page='.$genreData['gnaviListCount'];
            $curlOption = array(
                CURLOPT_URL => $curlUrl,
                CURLOPT_RETURNTRANSFER => true,
                );
            curl_setopt_array($curl, $curlOption);
            $results = json_decode(curl_exec($curl), true);

            curl_close($curl);

            \Log::info('ぐるなびAPI飲食店情報取得処理終了');
            return $results;
        } catch (Exception $e) {
            \Log::info('ぐるなびAPIでの飲食店情報取得でエラーが発生しました。');
            \Log::info($e);
        }

    }

    //ホットペッパーAPIから飲食店情報を取得
    public static function getHotpepperShopData($accessKey, $genreData){
        try {
            \Log::info('ホットペッパーAPI飲食店情報取得処理開始');
            $curl = curl_init();

            $curlUrl = 'http://webservice.recruit.co.jp/hotpepper/gourmet/v1/?key='.$accessKey.'&genre='.$genreData['hotpepperGenreCode'].'&lat='.$genreData['latitude'].'&lng='.$genreData['longitude'].'&range='.$genreData['range'].'&start='.$genreData['hotpepperListStart'].'&count='.$genreData['hotpepperListCount'].'&format=json';
            $curlOption = array(
                CURLOPT_URL => $curlUrl,
                CURLOPT_RETURNTRANSFER => true,
            );
            curl_setopt_array($curl, $curlOption);
            $results = json_decode(curl_exec($curl), true);
            curl_close($curl);

            \Log::info('ホットペッパーAPI飲食店情報取得処理終了');
            return $results;
        } catch (Exception $e) {
            \Log::info('ホットペッパーAPIでの飲食店情報取得でエラーが発生しました。');
            \Log::info($e);
        }
    }

}