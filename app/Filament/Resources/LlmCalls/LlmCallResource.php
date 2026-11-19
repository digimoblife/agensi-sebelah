<?php

namespace App\Filament\Resources\LlmCalls;

use App\Enums\LlmCallStatus;
use App\Models\LlmCall;
use App\Roles\RoleRegistry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** F8.4: read-only log of every LLM attempt (no prompt contents). */
class LlmCallResource extends Resource
{
    protected static ?string $model = LlmCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|UnitEnum|null $navigationGroup = 'Biaya & log AI';

    protected static ?string $navigationLabel = 'Log panggilan AI';

    protected static ?string $modelLabel = 'panggilan AI';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M H:i:s', 'Asia/Jakarta')->sortable(),
                TextColumn::make('client.name')->label('Klien')->searchable(),
                TextColumn::make('role_key')->label('Peran'),
                TextColumn::make('model_id')->label('Model')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (LlmCallStatus $state) => match ($state) {
                    LlmCallStatus::Ok, LlmCallStatus::CacheHit => 'success',
                    LlmCallStatus::SchemaInvalid, LlmCallStatus::BudgetBlocked => 'warning',
                    default => 'danger',
                }),
                TextColumn::make('error_code')->label('Kode')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('input_tokens')->label('In')->numeric(),
                TextColumn::make('cached_input_tokens')->label('Cache')->numeric(),
                TextColumn::make('output_tokens')->label('Out')->numeric(),
                TextColumn::make('cost_usd')->label('Biaya')->money('USD', 4)->sortable(),
                TextColumn::make('latency_ms')->label('ms')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('prompt_version')->label('Prompt')->formatStateUsing(fn ($state, LlmCall $r) => "{$r->prompt_key}@v{$state}")->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('client_id')->label('Klien')->relationship('client', 'name'),
                SelectFilter::make('role_key')->label('Peran')->options(app(RoleRegistry::class)->options()),
                SelectFilter::make('status')->options(collect(LlmCallStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->value])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLlmCalls::route('/')];
    }
}
