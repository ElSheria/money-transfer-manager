<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Transfer;
use Illuminate\Support\Str;

class TransferCodeService
{
    public function generate(Agency $sourceAgency, Agency $destinationAgency): string
    {
        do {
            $code = sprintf(
                '%s-%s-%s',
                strtoupper($sourceAgency->code),
                strtoupper(Str::random(10)),
                strtoupper($destinationAgency->code),
            );
        } while (Transfer::query()->where('code', $code)->exists());

        return $code;
    }
}
