<?php

namespace App\Filament\Pages;

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\Workspace;
use App\Services\Ops\MvpGate;
use App\Services\Ops\PilotReadiness;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Week 6: pilot readiness checklist and the MVP gate (PRD §Roadmap), including the Paperclip comparison. */
class PilotReadinessPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Kesiapan & gerbang MVP';

    protected static ?string $title = 'Kesiapan pilot & gerbang MVP';

    protected static ?string $slug = 'pilot';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.pilot-readiness';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('baseline')->label('Baseline biaya Paperclip')->icon('heroicon-o-scale')->color('gray')
                ->schema([TextInput::make('usd')->label('Biaya AI per klien per bulan dengan setup Paperclip (USD)')->numeric()->minValue(0)->required()
                    ->helperText('Dipakai untuk menghitung penghematan di gerbang MVP (PRD Minggu 6).')])
                ->fillForm(fn () => ['usd' => Workspace::current()->setting('paperclip_baseline_usd')])
                ->action(function (array $data) {
                    $workspace = Workspace::current();
                    $workspace->update(['settings' => array_merge($workspace->settings ?? [], ['paperclip_baseline_usd' => (float) $data['usd']])]);
                    Notification::make()->title('Baseline disimpan.')->success()->send();
                }),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $readiness = app(PilotReadiness::class);
        $clients = Client::query()->whereIn('status', [ClientStatus::Active, ClientStatus::Onboarding])->orderBy('name')->get();

        return [
            'system' => $readiness->system(),
            'clients' => $clients->map(fn (Client $c) => ['client' => $c, 'checks' => $readiness->client($c), 'gate' => app(MvpGate::class)->metrics($c)]),
            'weeksRequired' => MvpGate::WEEKS_REQUIRED,
            'approvalTarget' => MvpGate::APPROVAL_TARGET,
        ];
    }
}
