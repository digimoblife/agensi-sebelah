<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\KpiPeriod;
use App\Models\Client;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F9.8: goals and the primary KPI per client. */
class KpiGoalsRelationManager extends RelationManager
{
    use ScopedToClient;

    protected static string $relationship = 'kpiGoals';

    protected static ?string $title = 'Goal & KPI';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('metric_key')->label('Metrik')->required()->searchable()
                ->options(function () {
                    /** @var Client $client */
                    $client = $this->getOwnerRecord();
                    $kpis = $client->offeringType->default_kpis ?? [];

                    return array_combine($kpis, $kpis) + ['ig_reach' => 'ig_reach', 'ig_followers' => 'ig_followers', 'sessions' => 'sessions'];
                }),
            TextInput::make('label')->required()->maxLength(120),
            TextInput::make('target_value')->label('Target')->numeric()->required(),
            Select::make('period')->label('Periode')->required()->default(KpiPeriod::Month->value)
                ->options(['week' => 'Mingguan', 'month' => 'Bulanan', 'quarter' => 'Kuartalan']),
            TextInput::make('priority')->label('Prioritas (1 = utama)')->integer()->minValue(1)->default(1)->required(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('priority')->label('#')->sortable(),
                TextColumn::make('label'),
                TextColumn::make('metric_key')->label('Metrik'),
                TextColumn::make('target_value')->label('Target')->numeric(),
                TextColumn::make('period')->label('Periode')->badge(),
            ])
            ->defaultSort('priority')
            ->headerActions([$this->scopedCreateAction()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
