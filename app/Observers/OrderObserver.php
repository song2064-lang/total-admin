<?php

namespace App\Observers;

use App\Jobs\SyncOrderToChannel;
use App\Models\Order;

// 상태 변경 이력 기록 + 채널 역동기화
class OrderObserver
{
    public function created(Order $order): void
    {
        $order->statusLogs()->create([
            'from_status' => null,
            'to_status' => $order->status,
            'changed_by' => auth()->id(),
        ]);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $order->statusLogs()->create([
                'from_status' => $order->getOriginal('status'),
                'to_status' => $order->status,
                'changed_by' => auth()->id(),
            ]);
        }

        // 상태·송장이 바뀌면 채널 사이트에 반영 (백그라운드).
        // 변경 시점 값을 스냅샷으로 넘겨 막 변경 시 중간 단계 누락 방지.
        $trackingFields = [
            'tracking_intl_carrier',
            'tracking_intl_no',
            'tracking_carrier',
            'tracking_no',
        ];

        if ($order->wasChanged(array_merge(['status'], $trackingFields))) {
            SyncOrderToChannel::dispatch($order->id, [
                'status' => $order->status->value,
                'tracking_intl_carrier' => $order->tracking_intl_carrier,
                'tracking_intl_no' => $order->tracking_intl_no,
                'tracking_carrier' => $order->tracking_carrier,
                'tracking_no' => $order->tracking_no,
                // 변경 시점 시퀀스 (옛 변경 덮어쓰기 방지)
                'seq' => (int) now()->format('Uu'),
            ]);
        }
    }
}
