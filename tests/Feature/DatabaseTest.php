<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('migrates the dedicated PostgreSQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('agensi_test')
        ->and(Schema::hasTable('users'))->toBeTrue();
});
