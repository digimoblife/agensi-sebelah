<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\ResearchInputKind;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** F2.1 MVP: research material gathered by hand from public sources (ANALISIS K7). */
class ResearchInputsRelationManager extends RelationManager
{
    use ScopedToClient;

    protected static string $relationship = 'researchInputs';

    protected static ?string $title = 'Bahan riset';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Jenis')->required()->live()->default(ResearchInputKind::ManualNote->value)->options([
                'website' => 'Website kompetitor', 'ad_library' => 'Meta Ad Library', 'google_trends' => 'Google Trends',
                'serp' => 'Keyword / hasil pencarian', 'social_public' => 'Akun sosial publik', 'manual_note' => 'Catatan admin',
            ]),
            TextInput::make('ref')->label(fn (Get $get) => $get('kind') === 'serp' ? 'Keyword' : 'URL / referensi')->required()->maxLength(500),
            Textarea::make('note')->label('Catatan / temuan')->required()->rows(5)->maxLength(5000)->columnSpanFull(),
            TextInput::make('keyword_volume')->label('Volume pencarian bulanan')->integer()->minValue(0)
                ->visible(fn (Get $get) => $get('kind') === 'serp'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label('Jenis')->badge(),
                TextColumn::make('ref')->label('Referensi')->limit(50),
                TextColumn::make('note')->label('Catatan')->limit(80),
                TextColumn::make('created_at')->label('Dibuat')->since(),
            ])
            ->headerActions([$this->scopedCreateAction()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
