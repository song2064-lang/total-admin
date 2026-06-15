<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::advanceStatusAction(),
            OrderResource::shipInternationalAction(),
            OrderResource::shipDomesticAction(),
            OrderResource::localTrackingAction(),
            OrderResource::revertStatusAction(),
            OrderResource::noteAction(),
            EditAction::make(),
        ];
    }
}
