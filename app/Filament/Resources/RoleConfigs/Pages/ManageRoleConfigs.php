<?php

namespace App\Filament\Resources\RoleConfigs\Pages;

use App\Filament\Resources\RoleConfigs\RoleConfigResource;
use App\Models\Workspace;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoleConfigs extends ManageRecords
{
    protected static string $resource = RoleConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['workspace_id' => Workspace::current()->id]),
        ];
    }
}
