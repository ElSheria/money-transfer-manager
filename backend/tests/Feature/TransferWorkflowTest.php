<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ApiToken;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_transfer_with_agency_based_code_and_notifications(): void
    {
        $source = Agency::create([
            'name' => 'Kinshasa Masina',
            'code' => 'KINMA',
            'province' => 'Kinshasa',
            'city' => 'Kinshasa',
            'commune' => 'Masina',
        ]);
        $destination = Agency::create([
            'name' => 'Lubumbashi Texaco',
            'code' => 'LUBTEX',
            'province' => 'Haut-Katanga',
            'city' => 'Lubumbashi',
            'commune' => 'Texaco',
        ]);
        $manager = User::factory()->create([
            'role' => User::ROLE_MANAGER,
            'agency_id' => $source->id,
        ]);

        $response = $this->withToken($this->tokenFor($manager))->postJson('/api/transfers', [
            'source_agency_id' => $source->id,
            'destination_agency_id' => $destination->id,
            'amount' => 700,
            'currency' => 'USD',
            'sender' => $this->customerPayload('Elie', 'HANGI', 'el.sender@example.test'),
            'receiver' => $this->customerPayload('Belamard', 'KIALA', 'receiver@example.test'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.source_agency.code', 'KINMA')
            ->assertJsonPath('data.destination_agency.code', 'LUBTEX')
            ->assertJsonPath('data.fee', '14.00');

        $code = $response->json('data.code');

        $this->assertMatchesRegularExpression('/^KINMA-[A-Z0-9]{10}-LUBTEX$/', $code);
        $this->assertDatabaseCount('transfer_notifications', 4);
    }

    public function test_destination_manager_can_mark_transfer_as_withdrawn(): void
    {
        $source = Agency::create(['name' => 'Kinshasa Masina', 'code' => 'KINMA', 'province' => 'Kinshasa', 'city' => 'Kinshasa']);
        $destination = Agency::create(['name' => 'Lubumbashi Texaco', 'code' => 'LUBTEX', 'province' => 'Haut-Katanga', 'city' => 'Lubumbashi']);
        $sourceManager = User::factory()->create(['role' => User::ROLE_MANAGER, 'agency_id' => $source->id]);
        $destinationManager = User::factory()->create(['role' => User::ROLE_MANAGER, 'agency_id' => $destination->id]);

        $transferId = $this->withToken($this->tokenFor($sourceManager))->postJson('/api/transfers', [
            'source_agency_id' => $source->id,
            'destination_agency_id' => $destination->id,
            'amount' => 20000,
            'currency' => 'CDF',
            'sender' => $this->customerPayload('Elie', 'HANGI', 'elie@example.test'),
            'receiver' => $this->customerPayload('Belamard', 'KIALA', 'belamard@example.test'),
        ])->json('data.id');

        $this->withToken($this->tokenFor($destinationManager))
            ->patchJson("/api/transfers/{$transferId}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_WITHDRAWN);
    }

    public function test_manager_can_export_transfer_history_as_csv(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $this->createTransfer($source, $destination, $manager, 'USD');

        $response = $this->withToken($this->tokenFor($manager))
            ->get('/api/transfers/export/csv?currency=USD');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('Code transfert', $response->getContent());
        $this->assertStringContainsString('KINMA-', $response->getContent());
    }

    public function test_manager_can_export_transfer_history_as_pdf(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $this->createTransfer($source, $destination, $manager, 'CDF');

        $response = $this->withToken($this->tokenFor($manager))
            ->get('/api/transfers/export/pdf?currency=CDF');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('%%EOF', $response->getContent());
    }

    public function test_manager_can_export_transfer_history_as_excel(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $this->createTransfer($source, $destination, $manager, 'USD');

        $response = $this->withToken($this->tokenFor($manager))
            ->get('/api/transfers/export/excel?currency=USD');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringStartsWith('PK', $response->getContent());
    }

    public function test_transfer_can_be_found_by_code_and_cancelled(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $transferId = $this->createTransfer($source, $destination, $manager, 'USD');
        $transfer = Transfer::query()->findOrFail($transferId);

        $this->withToken($this->tokenFor($manager))
            ->getJson("/api/transfers/code/{$transfer->code}")
            ->assertOk()
            ->assertJsonPath('data.id', $transferId);

        $this->withToken($this->tokenFor($manager))
            ->patchJson("/api/transfers/{$transferId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_CANCELLED);
    }

    public function test_cancelled_transfer_cannot_be_withdrawn(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();
        $destinationManager = User::factory()->create(['role' => User::ROLE_MANAGER, 'agency_id' => $destination->id]);

        $transferId = $this->createTransfer($source, $destination, $manager, 'USD');

        $this->withToken($this->tokenFor($manager))
            ->patchJson("/api/transfers/{$transferId}/cancel")
            ->assertOk();

        $this->withToken($this->tokenFor($destinationManager))
            ->patchJson("/api/transfers/{$transferId}/withdraw")
            ->assertUnprocessable();
    }

    public function test_manager_can_download_transfer_receipt_pdf(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $transferId = $this->createTransfer($source, $destination, $manager, 'USD');

        $response = $this->withToken($this->tokenFor($manager))
            ->get("/api/transfers/{$transferId}/receipt");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_manager_dashboard_returns_scoped_totals(): void
    {
        [$source, $destination, $manager] = $this->transferSetup();

        $this->createTransfer($source, $destination, $manager, 'USD');

        $this->withToken($this->tokenFor($manager))
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.totals.agencies', 1)
            ->assertJsonPath('data.totals.transfers', 1);
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

    private function transferSetup(): array
    {
        $source = Agency::create(['name' => 'Kinshasa Masina', 'code' => 'KINMA', 'province' => 'Kinshasa', 'city' => 'Kinshasa']);
        $destination = Agency::create(['name' => 'Lubumbashi Texaco', 'code' => 'LUBTEX', 'province' => 'Haut-Katanga', 'city' => 'Lubumbashi']);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'agency_id' => $source->id]);

        return [$source, $destination, $manager];
    }

    private function createTransfer(Agency $source, Agency $destination, User $manager, string $currency): int
    {
        return $this->withToken($this->tokenFor($manager))->postJson('/api/transfers', [
            'source_agency_id' => $source->id,
            'destination_agency_id' => $destination->id,
            'amount' => $currency === 'USD' ? 700 : 20000,
            'currency' => $currency,
            'sender' => $this->customerPayload('Elie', 'HANGI', 'elie-'.$currency.'@example.test'),
            'receiver' => $this->customerPayload('Belamard', 'KIALA', 'belamard-'.$currency.'@example.test'),
        ])->json('data.id');
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

        return $token;
    }
}
