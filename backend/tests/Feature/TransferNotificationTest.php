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

    private function tokenFor(User $user): string
    {
        $token = Str::random(80);

        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }
}
