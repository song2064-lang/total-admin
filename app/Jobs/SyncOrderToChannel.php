<?php

namespace App\Jobs;

use App\Domain\Orders\ChannelNotifier;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

// 상태·송장 변경을 채널 사이트로 역동기화 (백그라운드, 실패 시 점증 재시도)
class SyncOrderToChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    // $snapshot: 변경 시점의 상태·송장 (막 변경 시 중간 단계 누락 방지)
    public function __construct(public int $orderId, public array $snapshot = []) {}

    // 재시도 간격(초)
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(ChannelNotifier $notifier): void
    {
        $order = Order::find($this->orderId);

        if ($order === null) {
            return;
        }

        // notify 가 false(전송 실패)면 예외로 재시도 유발
        if (! $notifier->notify($order, $this->snapshot)) {
            throw new RuntimeException("채널 동기화 실패 (order {$this->orderId})");
        }
    }
}
