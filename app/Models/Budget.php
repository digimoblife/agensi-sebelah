<?php

namespace App\Models;

use App\Enums\BudgetState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $llm_limit_usd
 * @property string $media_limit_usd
 * @property string $llm_spent_usd
 * @property string $media_spent_usd
 * @property CarbonImmutable $month
 * @property ?CarbonImmutable $alert_80_sent_at
 * @property ?CarbonImmutable $alert_100_sent_at
 */
class Budget extends Model
{
    protected $fillable = [
        'workspace_id', 'client_id', 'month', 'llm_limit_usd', 'media_limit_usd', 'llm_spent_usd', 'media_spent_usd',
        'alert_80_sent_at', 'alert_100_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'immutable_date',
            'alert_80_sent_at' => 'immutable_datetime',
            'alert_100_sent_at' => 'immutable_datetime',
        ];
    }

    public function llmState(): BudgetState
    {
        $limit = (float) $this->llm_limit_usd;
        if ($limit <= 0) {
            return BudgetState::Blocked;
        }

        $ratio = (float) $this->llm_spent_usd / $limit;

        return match (true) {
            $ratio >= 1.0 => BudgetState::Blocked,
            $ratio >= 0.8 => BudgetState::Degraded,
            default => BudgetState::Normal,
        };
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
