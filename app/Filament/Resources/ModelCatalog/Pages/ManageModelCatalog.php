<?php

namespace App\Filament\Resources\ModelCatalog\Pages;

use App\Filament\Resources\ModelCatalog\ModelCatalogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageModelCatalog extends ManageRecords
{
    protected static string $resource = ModelCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
