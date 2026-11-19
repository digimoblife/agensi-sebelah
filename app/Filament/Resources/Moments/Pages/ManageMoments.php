<?php

namespace App\Filament\Resources\Moments\Pages;

use App\Filament\Resources\Moments\MomentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMoments extends ManageRecords
{
    protected static string $resource = MomentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
