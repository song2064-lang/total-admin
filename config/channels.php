<?php

use App\Domain\Orders\Adapters\WoocommerceAdapter;
use App\Domain\Orders\Adapters\YoungcartAdapter;

// 채널 목록. 채널 추가시 여기 등록 + 어댑터 구현
return [

    'youngcart' => [
        'label' => '영카트 본점',
        'secret' => env('CHANNEL_YOUNGCART_SECRET'),
        'adapter' => YoungcartAdapter::class,
    ],

    'woocommerce' => [
        'label' => '워드프레스 스토어',
        'secret' => env('CHANNEL_WOOCOMMERCE_SECRET'),
        'adapter' => WoocommerceAdapter::class,
    ],

];
