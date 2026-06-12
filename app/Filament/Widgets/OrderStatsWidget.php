<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrderStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $countsByStatus = Order::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $inProgress = $countsByStatus->get(OrderStatus::PaymentConfirmed->value, 0)
            + $countsByStatus->get(OrderStatus::Purchased->value, 0)
            + $countsByStatus->get(OrderStatus::Inspected->value, 0);

        return [
            Stat::make('오늘 수신', Order::whereDate('created_at', today())->count())
                ->description('오늘 들어온 주문'),
            Stat::make('처리 대기', $countsByStatus->get(OrderStatus::Received->value, 0))
                ->description('접수 상태'),
            Stat::make('진행 중', $inProgress)
                ->description('결제확인·매입·검수'),
            Stat::make('발송 완료', $countsByStatus->get(OrderStatus::Shipped->value, 0))
                ->description('전체 누적'),
        ];
    }
}
