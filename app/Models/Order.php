<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    protected $fillable = [
        'channel',
        'channel_order_no',
        'customer_name',
        'customer_phone',
        'shipping_postcode',
        'shipping_address1',
        'shipping_address2',
        'items',
        'payment',
        'pccc',
        'status',
        'tracking_local_carrier',
        'tracking_local_no',
        'tracking_intl_carrier',
        'tracking_intl_no',
        'tracking_carrier',
        'tracking_no',
        'raw',
        'admin_note',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'payment' => 'array',
            'raw' => 'array',
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
        ];
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)->latest('id');
    }

    public function channelLabel(): string
    {
        return config("channels.{$this->channel}.label", $this->channel);
    }

    public function paymentAmount(): ?int
    {
        $amount = data_get($this->payment, 'amount');

        return $amount === null ? null : (int) $amount;
    }

    // 결제 통화 표시 단위
    public function currencyUnit(): string
    {
        return data_get($this->payment, 'currency') === 'JPY' ? '엔' : '원';
    }

    // 전체 배송 주소 (복사·발송용)
    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->shipping_postcode ? "[{$this->shipping_postcode}]" : null,
            $this->shipping_address1,
            $this->shipping_address2,
        ]);

        return implode(' ', $parts);
    }

    // 국내(한국) 택배 배송조회 URL. 미지원 택배사는 네이버 통합검색으로 대체
    public function trackingUrl(): ?string
    {
        if (blank($this->tracking_no)) {
            return null;
        }

        $no = preg_replace('/\D/', '', $this->tracking_no);
        $carrier = (string) $this->tracking_carrier;

        return match (true) {
            str_contains($carrier, 'CJ'), str_contains($carrier, '대한통운') =>
                "https://trace.cjlogistics.com/next/tracking.html?wblNo={$no}",
            str_contains($carrier, '우체국') =>
                "https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1={$no}",
            str_contains($carrier, '한진') =>
                "https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2={$no}",
            str_contains($carrier, '롯데') =>
                "https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo={$no}",
            str_contains($carrier, '로젠') =>
                "https://www.ilogen.com/m/personal/trace/{$no}",
            default => 'https://search.naver.com/search.naver?query='.urlencode(trim($carrier.' '.$no)),
        };
    }

    // 국제 구간 배송조회 URL. EMS 는 우체국 국제우편, 그 외는 네이버 검색
    public function intlTrackingUrl(): ?string
    {
        if (blank($this->tracking_intl_no)) {
            return null;
        }

        $no = preg_replace('/[^A-Za-z0-9]/', '', (string) $this->tracking_intl_no);
        $carrier = (string) $this->tracking_intl_carrier;

        if ($no !== '' && str_contains($carrier, 'EMS')) {
            return "https://service.epost.go.kr/trace.RetrieveEmsRigiTraceList.comm?POSTCODE={$no}";
        }

        return 'https://search.naver.com/search.naver?query='.urlencode(trim($carrier.' '.$no));
    }
}
