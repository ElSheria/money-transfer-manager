<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dg_can_send_pending_transfer_notifications(): void
    {
        $source = Agency::create(['name' => 'Kinshasa Masina', 'code' => 'KINMA', 'province' => 'Kinshasa', 'city' => 'Kinshasa']);
        $destination = Agency::create(['name' => 'Lubumbashi Texaco', 'code' => 'LUBTEX', 'province' => 'Haut-Katanga', 'city' => 'Lubumbashi']);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'agency_id' => $source->id]);
        $dg = User::factory()->create(['role' => User::ROLE_DG]);

        $this->withToken($this->tokenFor($manager))->postJson('/api/transfers', [
            'source_agency_id' => $source->id,
            'destination_agency_id' => $destination->id,
            'amount' => 700,
            'currency' => 'USD',
            'sender' => $this->customerPayload('Elie', 'HANGI', 'elie@example.test'),
            'receiver' => $this->customerPayload('Belamard', 'KIALA', 'belamard@example.test'),
        ])->assertCreated();

        $this->assertDatabaseCount('transfer_notifications', 4);

        $this->withToken($this->tokenFor($dg))
            ->postJson('/api/transfer-notifications/send-pending', ['limit' => 10])
            ->assertOk()
            ->assertJsonPath('data.sent', 2)
            ->assertJsonPath('data.skipped', 2);
    }

    private function customerPayload(string $firstName, string $lastName, string $email): array
    {
        return [
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'phone' => '+243990000000',
            'email' => $email,
            'address' => 'Adresse test',
            'province' => 'Kinshasa',
            'city' => 'Kinshasa',
            'commune' => 'Masina',
        ];
    }

    // Crée un token API valide pour authentifier un utilisateur pendant les tests.
    private function tokenFor(User $user): string {
        // Génère le token brut utilisé dans :
        // Authorization: Bearer <token>
        $token = Str::random(80);

        ApiToken::create([
            // Utilisateur propriétaire du token.
            'user_id' => $user->id,

            // Nom utilisé uniquement pour identifier le token de test.
            'name' => 'test',

            // Comme dans l'application réelle,
            // seul le hash du token est stocké en base.
            'token_hash' => hash('sha256', $token),

            // Le middleware exige maintenant
            // une date d'expiration future.
            'expires_at' => now()->addHours(
                (int) config('auth.api_token_ttl_hours', 12)
            ),
        ]);

        // Retourne le token brut utilisé par withToken().
        return $token;
    }
}
