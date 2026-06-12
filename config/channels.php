<?php

use App\Domain\Orders\Adapters\SajapanAdapter;
use App\Domain\Orders\Adapters\YoungcartAdapter;

// 채널 목록. 채널 추가시 여기 등록 + 어댑터 구현
return [

    'youngcart' => [
        'label' => '영카트 본점',
        'secret' => env('CHANNEL_YOUNGCART_SECRET'),
        'adapter' => YoungcartAdapter::class,
    ],

    'sajapan' => [
        'label' => '일본 구매대행',
        'secret' => env('CHANNEL_SAJAPAN_SECRET'),
        'adapter' => SajapanAdapter::class,
    ],

];
