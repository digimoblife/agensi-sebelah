<?php

namespace App\Filament\Resources\ContentItems\Pages;

use App\Filament\Resources\ContentItems\ContentItemResource;
use Filament\Resources\Pages\ListRecords;

class ListContentItems extends ListRecords
{
    protected static string $resource = ContentItemResource::class;
}
