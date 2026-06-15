<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    // 상태별 탭 (건수 배지 포함)
    public function getTabs(): array
    {
        $counts = Order::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $tabs = [
            'all' => Tab::make('전체')->badge($counts->sum()),
        ];

        foreach (OrderStatus::cases() as $case) {
            $tabs[$case->value] = Tab::make($case->getLabel())
                ->badge($counts->get($case->value, 0))
                ->badgeColor($case->getColor())
                ->modifyQueryUsing(fn ($query) => $query->where('status', $case->value));
        }

        return $tabs;
    }
}
