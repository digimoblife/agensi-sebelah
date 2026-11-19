<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Enums\AssetKind;
use App\Models\Asset;
use App\Models\Client;
use App\Services\Assets\AssetLibrary;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** F9.3 asset library: client-owned files, tags, consent and license (PRD §Sumber aset). */
class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Pustaka aset';

    private const KINDS = ['logo' => 'Logo', 'photo' => 'Foto', 'screenshot' => 'Screenshot', 'illustration' => 'Ilustrasi', 'video' => 'Video'];

    public function form(Schema $schema): Schema
    {
        /** @var Client $client */
        $client = $this->getOwnerRecord();

        return $schema->components([
            FileUpload::make('path')->label('File')->required()->disk('assets')->directory($client->ulid)->visibility('private')
                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'video/mp4'])->maxSize(51200)
                ->hiddenOn('edit')->columnSpanFull(),
            TextInput::make('title')->label('Judul / deskripsi singkat')->required()->maxLength(160),
            Select::make('kind')->label('Jenis')->required()->options(self::KINDS)->default('photo'),
            Select::make('source')->label('Sumber')->required()->default('client')->live()
                ->options(['client' => 'Aset klien', 'ugc' => 'Konten pengguna (UGC)', 'stock' => 'Stok berlisensi', 'auto' => 'Otomatis (screenshot)']),
            Select::make('offering_id')->label('Penawaran')->relationship('offering', 'name', fn ($q) => $q->where('client_id', $client->id)),
            TagsInput::make('tags')->label('Tag (suasana, objek, kegiatan)')->columnSpanFull(),
            Toggle::make('has_people')->label('Ada orang')->live(),
            Toggle::make('has_children')->label('Ada anak-anak')->live(),
            Select::make('consent_status')->label('Izin')->required()->default('not_needed')
                ->options(['not_needed' => 'Tidak perlu', 'granted' => 'Izin ada', 'pending' => 'Menunggu izin', 'refused' => 'Ditolak'])
                ->helperText(fn (Get $get) => $get('has_children') ? 'Foto anak hanya dipakai dengan izin orang tua, atau dari stok berlisensi.' : null),
            TextInput::make('license')->label('Lisensi / kredit')->maxLength(200)->visible(fn (Get $get) => $get('source') === 'stock'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('kind', '!=', AssetKind::Render))
            ->columns([
                ImageColumn::make('preview')->label('')->state(fn (Asset $r) => str_starts_with($r->mime, 'image/') ? $r->previewUrl() : null)
                    ->imageHeight(56)->checkFileExistence(false),
                TextColumn::make('title')->label('Judul')->searchable()->description(fn (Asset $r) => implode(', ', $r->tags ?? [])),
                TextColumn::make('kind')->label('Jenis')->badge()->formatStateUsing(fn (AssetKind $state) => self::KINDS[$state->value] ?? $state->value),
                TextColumn::make('source')->label('Sumber'),
                IconColumn::make('publishable')->label('Boleh dipakai')->boolean()->state(fn (Asset $r) => $r->isPublishable()),
                TextColumn::make('usages_count')->counts('usages')->label('Dipakai'),
            ])
            ->filters([SelectFilter::make('kind')->label('Jenis')->options(self::KINDS)])
            ->headerActions([
                CreateAction::make()->label('Unggah aset')->mutateDataUsing(function (array $data): array {
                    /** @var Client $client */
                    $client = $this->getOwnerRecord();

                    return $data + app(AssetLibrary::class)->describeUpload((string) $data['path']) + ['workspace_id' => $client->workspace_id];
                }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->after(fn (Asset $r) => Storage::disk('assets')->delete($r->path)),
            ]);
    }
}
