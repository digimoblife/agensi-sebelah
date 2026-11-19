<?php

namespace App\Services\Ops;

final readonly class Check
{
    /** @param  'ok'|'warn'|'fail'  $status */
    public function __construct(
        public string $group,
        public string $label,
        public string $status,
        public string $detail = '',
        public string $hint = '',
    ) {}
}
