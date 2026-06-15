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
        // 상태·송장 역동기화 수신 주소 (비우면 비활성)
        'callback_url' => env('CHANNEL_SAJAPAN_CALLBACK_URL'),
        // 이쪽 상태 → 채널측 상태. null 은 채널에 대응 단계 없음(미전송)
        'status_map' => [
            'received' => 'private',
            'payment_confirmed' => 'sa-paid',
            'purchased' => 'sa-purchased',
            'warehoused' => null,
            'inspected' => null,
            'international_shipping' => 'sa-intl-shipping',
            'domestic_shipping' => 'sa-domestic-shipping',
            'delivered' => 'sa-delivered',
        ],
    ],

];
