<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\OfferingStatus;
use App\Models\OfferingType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfferingsRelationManager extends RelationManager
{
    use ScopedToClient;

    protected static string $relationship = 'offerings';

    protected static ?string $title = 'Penawaran';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(160),
            Select::make('offering_type')->label('Jenis')->required()->options(fn () => OfferingType::query()->pluck('label', 'key'))
                ->default(fn () => $this->getOwnerRecord()->getAttribute('primary_offering_type')),
            Textarea::make('description')->label('Deskripsi')->rows(3)->maxLength(2000)->columnSpanFull(),
            TextInput::make('price')->label('Harga (opsional)')->numeric()->minValue(0),
            TextInput::make('currency')->label('Mata uang')->default('IDR')->length(3),
            TextInput::make('url')->url()->maxLength(500),
            Select::make('status')->options(collect(OfferingStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)]))
                ->default(OfferingStatus::Active->value)->required(),
            KeyValue::make('schedule')->label('Jadwal (opsional)')->keyLabel('Tanggal / sesi')->valueLabel('Keterangan')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('type.label')->label('Jenis'),
                TextColumn::make('price')->label('Harga')->numeric(0)->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([$this->scopedCreateAction()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
