<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeItem;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/** F9.2: the only source of facts. Editing an item withdraws its approval, so changed facts are re-checked. */
class KnowledgeItemsRelationManager extends RelationManager
{
    use ScopedToClient;

    protected static string $relationship = 'knowledgeItems';

    protected static ?string $title = 'Knowledge base';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('kind')->label('Jenis')->required()->default(KnowledgeKind::Fact->value)
                ->options(collect(KnowledgeKind::cases())->mapWithKeys(fn ($k) => [$k->value => $k->label()])),
            Select::make('offering_id')->label('Penawaran (opsional)')
                ->relationship('offering', 'name', fn ($query) => $query->where('client_id', $this->getOwnerRecord()->getKey())),
            TextInput::make('title')->label('Judul')->required()->maxLength(160)->columnSpanFull(),
            Textarea::make('body')->label('Isi')->required()->rows(4)->maxLength(4000)->columnSpanFull(),
            TextInput::make('source')->label('Sumber')->maxLength(200)->placeholder('mis. brosur 2026, pemilik, website'),
            DatePicker::make('valid_until')->label('Berlaku sampai (opsional)'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Ref')->formatStateUsing(fn ($state) => "kb:{$state}"),
                TextColumn::make('kind')->label('Jenis')->formatStateUsing(fn (KnowledgeKind $state) => $state->label())->badge(),
                TextColumn::make('title')->label('Judul')->searchable()->description(fn (KnowledgeItem $r) => mb_strimwidth($r->body, 0, 90, '…')),
                TextColumn::make('valid_until')->label('Berlaku s.d.')->date('d M Y')->placeholder('—'),
                IconColumn::make('approved_at')->label('Disetujui')->boolean()->state(fn (KnowledgeItem $r) => $r->approved_at !== null),
            ])
            ->filters([TernaryFilter::make('approved_at')->label('Disetujui')->nullable()])
            ->headerActions([$this->scopedCreateAction()])
            ->recordActions([
                Action::make('approve')->label('Setujui')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (KnowledgeItem $r) => $r->approved_at === null)
                    ->action(fn (KnowledgeItem $r) => $r->update(['approved_at' => now(), 'approved_by' => Auth::id()])),
                EditAction::make()->mutateDataUsing(fn (array $data) => $data + ['approved_at' => null, 'approved_by' => null]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkAction::make('approve')->label('Setujui terpilih')->icon('heroicon-o-check')
                    ->action(fn (Collection $records) => $records->each(fn (KnowledgeItem $r) => $r->approved_at === null
                        ? $r->update(['approved_at' => now(), 'approved_by' => Auth::id()]) : null)),
            ]);
    }
}
