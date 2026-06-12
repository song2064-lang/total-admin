<?php

namespace App\Observers;

use App\Models\Order;

// 상태 변경 이력 기록
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
        if (! $order->wasChanged('status')) {
            return;
        }

        $order->statusLogs()->create([
            'from_status' => $order->getOriginal('status'),
            'to_status' => $order->status,
            'changed_by' => auth()->id(),
        ]);
    }
}
