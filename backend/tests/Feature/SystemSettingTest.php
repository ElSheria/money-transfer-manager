<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dg_can_update_transfer_fee_settings(): void
    {
        $this->seed();
        $dg = User::factory()->create(['role' => User::ROLE_DG]);
        $setting = SystemSetting::query()->where('key', 'transfer_fees')->firstOrFail();

        $this->withToken($this->tokenFor($dg))
            ->patchJson("/api/system-settings/{$setting->id}", [
                'value' => [
                    'USD' => ['type' => 'percentage', 'rate' => 3, 'minimum' => 2],
                    'CDF' => ['type' => 'fixed', 'amount' => 1500, 'minimum' => 0],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.value.USD.rate', 3);
    }

    // Crée un token API valide pour authentifier un utilisateur pendant les tests.
    private function tokenFor(User $user): string {
        // Génère le token brut utilisé dans :
        // Authorization: Bearer <token>
        $token = Str::random(80);

        ApiToken::create([
            // Utilisateur auquel appartient le token.
            'user_id' => $user->id,

            // Nom permettant d'identifier le token de test.
            'name' => 'test',

            // On stocke uniquement le hash du token.
            'token_hash' => hash('sha256', $token),

            // Rend le token valide pour le middleware
            // qui vérifie maintenant son expiration.
            'expires_at' => now()->addHours(
                (int) config('auth.api_token_ttl_hours', 12)
            ),
        ]);

        // Retourne le token brut à withToken().
        return $token;
    }
}
