<?php

namespace App\Models;

use App\Enums\OfferingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OfferingStatus $status
 * @property ?string $price
 */
class Offering extends Model
{
    protected $fillable = ['workspace_id', 'client_id', 'offering_type', 'name', 'description', 'price', 'currency', 'schedule', 'url', 'status'];

    protected function casts(): array
    {
        return ['status' => OfferingStatus::class, 'schedule' => 'array'];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<OfferingType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(OfferingType::class, 'offering_type', 'key');
    }
}
