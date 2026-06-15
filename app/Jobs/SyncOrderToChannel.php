<?php

namespace App\Jobs;

use App\Domain\Orders\ChannelNotifier;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

// 상태·송장 변경을 채널 사이트로 역동기화 (백그라운드, 실패 시 점증 재시도)
class SyncOrderToChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 기한까지 재시도 (채널 장기 다운 대비)
    public int $tries = 0;

    // $snapshot: 변경 시점의 상태·송장 (막 변경 시 중간 단계 누락 방지)
    public function __construct(public int $orderId, public array $snapshot = []) {}

    // 복구까지 최대 24시간 재시도
    public function retryUntil(): Carbon
    {
        return now()->addDay();
    }

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

    // 영구 실패 로그 (운영 누락 추적)
    public function failed(Throwable $e): void
    {
        Log::error('채널 동기화 영구 실패', [
            'order_id' => $this->orderId,
            'snapshot' => $this->snapshot,
            'error' => $e->getMessage(),
        ]);
    }
}
