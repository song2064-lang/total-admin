<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
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

        return collect(OrderStatus::cases())
            ->map(fn (OrderStatus $status) => Stat::make(
                $status->getLabel(),
                $countsByStatus->get($status->value, 0),
            )
                ->color($status->getColor())
                ->url($this->listUrl($status)))
            ->all();
    }

    // 주문 목록(해당 상태 탭)으로 이동
    private function listUrl(?OrderStatus $status = null): string
    {
        return OrderResource::getUrl('index', $status ? ['tab' => $status->value] : []);
    }
}
