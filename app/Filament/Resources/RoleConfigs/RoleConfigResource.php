<?php

namespace App\Filament\Resources\RoleConfigs;

use App\Models\ModelCatalogEntry;
use App\Models\RoleConfig;
use App\Models\Workspace;
use App\Roles\RoleRegistry;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Per role: primary model, fallback model, cost cap per call (PRD §Fitur admin untuk model). */
class RoleConfigResource extends Resource
{
    protected static ?string $model = RoleConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Model per peran';

    protected static ?string $modelLabel = 'pengaturan peran';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $models = fn () => ModelCatalogEntry::query()->where('active', true)->orderBy('provider')->orderBy('label')
            ->get()->mapWithKeys(fn (ModelCatalogEntry $m) => [$m->id => $m->provider->label().' · '.$m->label]);

        return $schema->components([
            Select::make('role_key')->label('Peran')->required()->options(app(RoleRegistry::class)->options())
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('workspace_id', Workspace::current()->id)),
            Select::make('primary_model_id')->label('Model utama')->options($models)->required(),
            Select::make('fallback_model_id')->label('Model cadangan')->options($models)->different('primary_model_id'),
            TextInput::make('max_cost_per_call_usd')->label('Batas biaya per panggilan')->numeric()->minValue(0)->prefix('$'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $roles = app(RoleRegistry::class)->options();

        return $table
            ->columns([
                TextColumn::make('role_key')->label('Peran')->formatStateUsing(fn (string $state) => $roles[$state] ?? $state),
                TextColumn::make('primaryModel.label')->label('Utama'),
                TextColumn::make('fallbackModel.label')->label('Cadangan')->placeholder('—'),
                TextColumn::make('max_cost_per_call_usd')->label('Batas/panggilan')->money('USD')->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageRoleConfigs::route('/')];
    }
}
