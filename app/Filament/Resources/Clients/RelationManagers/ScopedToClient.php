<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Models\Client;
use Filament\Actions\CreateAction;

/** Fills workspace_id on records created under a client (client_id comes from the relationship). */
trait ScopedToClient
{
    protected function scopedCreateAction(): CreateAction
    {
        return CreateAction::make()->mutateDataUsing(function (array $data): array {
            /** @var Client $client */
            $client = $this->getOwnerRecord();

            return $data + ['workspace_id' => $client->workspace_id];
        });
    }
}
