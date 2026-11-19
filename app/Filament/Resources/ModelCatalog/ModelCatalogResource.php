<?php

namespace App\Filament\Resources\ModelCatalog;

use App\Enums\LlmProvider;
use App\Models\ModelCatalogEntry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** PRD §Fitur admin untuk model: katalog model + harga, diisi dan diperbarui admin. */
class ModelCatalogResource extends Resource
{
    protected static ?string $model = ModelCatalogEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Katalog model';

    protected static ?string $modelLabel = 'model';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $price = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->minValue(0)->prefix('$');

        return $schema->components([
            Select::make('provider')->label('Penyedia')->required()
                ->options(collect(LlmProvider::cases())->mapWithKeys(fn (LlmProvider $p) => [$p->value => $p->label()])),
            TextInput::make('model_id')->label('Model ID (persis seperti di API)')->required()->maxLength(120),
            TextInput::make('label')->required()->maxLength(120),
            CheckboxList::make('capabilities')->label('Kemampuan')->required()->columns(4)->options([
                'text' => 'Teks', 'vision' => 'Baca gambar', 'image' => 'Buat gambar', 'video' => 'Buat video',
                'json_schema' => 'Output JSON schema', 'prompt_cache' => 'Prompt cache', 'server_fallback' => 'Fallback server (Claude)',
            ]),
            Section::make('Harga (USD)')->description('Model teks tanpa harga input & output tidak akan dipakai gateway.')->columns(3)->schema([
                $price('price_input_per_mtok', 'Input / 1 juta token'),
                $price('price_cached_input_per_mtok', 'Input ter-cache / 1 juta'),
                $price('price_cache_write_per_mtok', 'Tulis cache / 1 juta'),
                $price('price_output_per_mtok', 'Output / 1 juta token'),
                $price('price_per_image', 'Per gambar'),
                $price('price_per_video_second', 'Per detik video'),
            ]),
            TextInput::make('max_output_tokens')->label('Batas output token')->integer()->minValue(256)->default(16000)->required(),
            Toggle::make('active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')->label('Penyedia')->formatStateUsing(fn (LlmProvider $state) => $state->label())->sortable(),
                TextColumn::make('label')->searchable()->description(fn (ModelCatalogEntry $r) => $r->model_id),
                TextColumn::make('price_input_per_mtok')->label('Input $/1M')->placeholder('—'),
                TextColumn::make('price_output_per_mtok')->label('Output $/1M')->placeholder('—'),
                IconColumn::make('routable')->label('Siap dipakai')->boolean()->state(fn (ModelCatalogEntry $r) => $r->isRoutable()),
            ])
            ->defaultSort('provider')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageModelCatalog::route('/')];
    }
}
