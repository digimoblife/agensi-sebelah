<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Workspace extends Model
{
    protected $fillable = ['name'];

    /** v1 runs a single workspace (seeded). SaaS later replaces this with the authenticated tenant. */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? throw new RuntimeException('No workspace exists. Run `php artisan db:seed`.');
    }

    /** @return HasMany<Client, $this> */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
