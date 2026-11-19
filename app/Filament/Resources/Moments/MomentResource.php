<?php

namespace App\Filament\Resources\Moments;

use App\Models\Moment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** F9.7: the admin corrects estimated dates (e.g. Lebaran after the SKB) and adds moments. */
class MomentResource extends Resource
{
    protected static ?string $model = Moment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Kalender momen';

    protected static ?string $modelLabel = 'momen';

    protected static ?int $navigationSort = 4;

    private const KINDS = ['religious' => 'Hari raya', 'shopping' => 'Belanja', 'national' => 'Nasional', 'payday' => 'Gajian', 'school' => 'Sekolah', 'season' => 'Musim'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(120),
            TextInput::make('key')->label('Kunci')->required()->alphaDash()->maxLength(60)->helperText('Dipakai Content Plan, mis. idul_fitri.'),
            Select::make('kind')->label('Jenis')->required()->options(self::KINDS),
            DatePicker::make('date_start')->label('Mulai')->required(),
            DatePicker::make('date_end')->label('Selesai')->required()->afterOrEqual('date_start'),
            Toggle::make('approximate')->label('Tanggal perkiraan'),
            TextInput::make('notes')->label('Catatan')->maxLength(300)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_start')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('date_end')->label('Selesai')->date('d M Y'),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('kind')->label('Jenis')->formatStateUsing(fn (string $state) => self::KINDS[$state] ?? $state)->badge(),
                IconColumn::make('approximate')->label('Perkiraan')->boolean(),
            ])
            ->defaultSort('date_start')
            ->filters([SelectFilter::make('kind')->label('Jenis')->options(self::KINDS)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMoments::route('/')];
    }
}
