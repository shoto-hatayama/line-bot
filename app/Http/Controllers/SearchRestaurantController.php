<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class SearchRestaurantController extends Controller
{
    public function detail($id)
    {
        $foodApi = app()->make('CallFoodApi');
        $results = $foodApi->hotpepperSearchStoreid(env('HOTPEPPER_ACCESS_KEY'), $id);

        // view側で表示を楽にするため表示ラベル名と値をセットにする
        $storeDetails = [];
        $i = 0;
        foreach (config('const.StoreDetailLabel') as $key => $labelName) {
            $storeDetails[$i]['label'] = $labelName;
            $storeDetails[$i]['value'] = $results[$key];
            $i++;
        }

        return view('detail', compact('storeDetails', 'id'));
    }
}
