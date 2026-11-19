<?php

namespace App\Filament\Resources\Clients;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\OfferingType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** F1.1 onboarding + client profile. Facts live in the knowledge base tab, not in free-text answers. */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Klien';

    protected static ?string $modelLabel = 'klien';

    protected static ?int $navigationSort = 1;

    /** Channels whose account access is tracked during onboarding (F1.1 "akses akun"). */
    public const ACCOUNT_CHANNELS = [
        'instagram' => 'Instagram', 'facebook' => 'Facebook Page', 'tiktok' => 'TikTok', 'ga4' => 'Google Analytics 4',
        'search_console' => 'Search Console', 'business_profile' => 'Google Business Profile', 'website_cms' => 'Website / CMS',
    ];

    public static function form(Schema $schema): Schema
    {
        $access = collect(self::ACCOUNT_CHANNELS)->map(fn (string $label, string $key) => Select::make("onboarding.account_access.{$key}")
            ->label($label)->default('none')->selectablePlaceholder(false)
            ->options(['none' => 'Belum', 'requested' => 'Diminta', 'granted' => 'Akses diberikan', 'not_used' => 'Tidak dipakai']))->values()->all();

        return $schema->components([
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Profil')->schema([
                    TextInput::make('name')->label('Nama klien')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('status')->options(collect(ClientStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)]))
                        ->default(ClientStatus::Onboarding->value)->required(),
                    Select::make('primary_offering_type')->label('Jenis penawaran utama')->required()
                        ->options(fn () => OfferingType::query()->pluck('label', 'key'))
                        ->helperText('Menentukan event konversi, KPI, pilar konten, dan CTA bawaan (F9.1).'),
                    TextInput::make('timezone')->default('Asia/Jakarta')->required(),
                    Fieldset::make('Budget AI bulanan (USD)')->schema([
                        TextInput::make('llm_budget_usd')->label('Teks / LLM')->numeric()->minValue(0)->prefix('$')
                            ->placeholder((string) config('llm.budget.default_llm_limit_usd')),
                        TextInput::make('media_budget_usd')->label('Gambar & video')->numeric()->minValue(0)->prefix('$')
                            ->placeholder((string) config('llm.budget.default_media_limit_usd')),
                    ]),
                ])->columns(2),
                Tab::make('Onboarding')->schema([
                    Textarea::make('onboarding.business_description')->label('Deskripsi bisnis')->rows(4)->maxLength(3000)
                        ->helperText('Apa yang dijual, untuk siapa, dan apa yang membuatnya berbeda.')->columnSpanFull(),
                    TextInput::make('onboarding.location')->label('Lokasi')->maxLength(200),
                    TextInput::make('onboarding.website')->label('Website')->url()->maxLength(300),
                    Textarea::make('onboarding.audience')->label('Audiens')->rows(3)->maxLength(2000)->columnSpanFull(),
                    Textarea::make('onboarding.goals_note')->label('Tujuan pemasaran')->rows(2)->maxLength(1000)->columnSpanFull(),
                    Repeater::make('onboarding.competitors')->label('Kompetitor')->maxItems(5)->columns(2)->defaultItems(0)->schema([
                        TextInput::make('name')->label('Nama')->required()->maxLength(120),
                        TextInput::make('url')->label('URL')->url()->maxLength(300),
                    ])->columnSpanFull(),
                    Textarea::make('onboarding.visual_preferences')->label('Preferensi visual')->rows(2)->maxLength(1000)->columnSpanFull(),
                    Textarea::make('onboarding.notes')->label('Catatan lain')->rows(2)->maxLength(2000)->columnSpanFull(),
                    Fieldset::make('Akses akun')->schema($access)->columns(3)->columnSpanFull(),
                ])->columns(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('offeringType.label')->label('Jenis'),
                TextColumn::make('status')->badge()->color(fn (ClientStatus $state) => match ($state) {
                    ClientStatus::Active => 'success', ClientStatus::Onboarding => 'info', ClientStatus::Paused => 'warning', ClientStatus::Archived => 'gray',
                }),
                TextColumn::make('updated_at')->label('Diubah')->since()->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OfferingsRelationManager::class,
            RelationManagers\KnowledgeItemsRelationManager::class,
            RelationManagers\KpiGoalsRelationManager::class,
            RelationManagers\ResearchInputsRelationManager::class,
            RelationManagers\AssetsRelationManager::class,
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\ArtifactVersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
