<?php

namespace App\Services;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function issue(User $user, string $name = 'api'): string
    {
        $plainToken = Str::random(80);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return $plainToken;
    }

    public function revokeCurrent(string $plainToken): void
    {
        ApiToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->delete();
    }
}
