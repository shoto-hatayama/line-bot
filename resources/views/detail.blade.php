<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="ie=edge" />
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  <title>店舗情報詳細</title>
</head>

<body>
  <!-- ホットペッパーにメニューページが存在しない場合は表示されない -->
  <iframe class="font-sans h-3/4 w-full bg-cover text-center flex flex-col items-center justify-center" src="https://www.hotpepper.jp/str{{ $id }}/food/"></iframe>

  <section class=" text-gray-600 body-font overflow-hidden">
    <div class="container px-5 pb-24 mx-auto">
      <div class="-my-8 divide-y-2 divide-gray-100">
        @foreach($storeDetails as $storeDetail)
        <div class="pt-8 flex flex-wrap md:flex-nowrap">
          <div class="md:flex-grow">
            <span class="font-semibold title-font text-gray-700">{{$storeDetail['label']}}</span>
            <p class="leading-relaxed">{{$storeDetail['value']}}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </section>
  <div class="text-center">
    【画像提供：ホットペッパー グルメ】
  </div>
  <div class="text-center">
    Powered by <a href=" http://webservice.recruit.co.jp/">ホットペッパー Webサービス</a>
  </div>

</body>

</html>