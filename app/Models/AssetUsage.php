<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetUsage extends Model
{
    public $timestamps = false;

    protected $fillable = ['asset_id', 'content_item_id', 'role', 'used_at'];
}
