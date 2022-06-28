<!DOCTYPE html>
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if(config('app.env') === 'production')
    <link rel="stylesheet" href="{{ secure_asset('css/app.css') }}">
    @else
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @endif
    <title>umaccocco!
    </title>
</head>

<body>
    <!-- ヘッダー -->
    <header class="text-gray-600 body-font">
        <div class="container mx-auto flex flex-wrap p-5 flex-col md:flex-row items-center border-b-2 border-gray-500">
            <a class="flex title-font font-medium items-center text-gray-900 mb-4 md:mb-0">
                <span class="ml-3 text-xl">umacocco!</span>
            </a>
        </div>
    </header>
    <!-- コンテンツ表示 -->


    <section class="text-gray-600 body-font">
        <div class="container px-5 py-24 mx-auto">
            <div id="store-info" class="flex flex-wrap -m-4">
                <info></info>
            </div>
        </div>
    </section>
</body>
@if(config('app.env') === 'production')
<script src="{{ secure_asset('js/app.js') }}"></script>
@else
<script src="{{ asset('js/app.js') }}"></script>
@endif

</html>