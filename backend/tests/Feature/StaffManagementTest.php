<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dg_can_create_supervisor_and_manager(): void
    {
        $dgToken = $this->tokenFor(User::factory()->create(['role' => User::ROLE_DG]));
        $agency = Agency::create([
            'name' => 'Kinshasa Masina',
            'code' => 'KINMA',
            'province' => 'Kinshasa',
            'city' => 'Kinshasa',
            'commune' => 'Masina',
        ]);

        $this->withToken($dgToken)->postJson('/api/staff', [
            'name' => 'Superviseur Test',
            'email' => 'superviseur@example.test',
            'password' => 'password-test',
            'role' => User::ROLE_SUPERVISOR,
        ])->assertCreated();

        $this->withToken($dgToken)->postJson('/api/staff', [
            'name' => 'Gerant Masina',
            'email' => 'gerant@example.test',
            'password' => 'password-test',
            'role' => User::ROLE_MANAGER,
            'agency_id' => $agency->id,
        ])->assertCreated()
            ->assertJsonPath('data.agency.code', 'KINMA');
    }

    public function test_supervisor_cannot_create_another_supervisor(): void
    {
        $supervisorToken = $this->tokenFor(User::factory()->create(['role' => User::ROLE_SUPERVISOR]));

        $this->withToken($supervisorToken)->postJson('/api/staff', [
            'name' => 'Superviseur Bloque',
            'email' => 'blocked@example.test',
            'password' => 'password-test',
            'role' => User::ROLE_SUPERVISOR,
        ])->assertUnprocessable();
    }

    public function test_supervisor_can_create_manager_only_inside_supervised_agency(): void
    {
        $allowedAgency = Agency::create([
            'name' => 'Kinshasa Masina',
            'code' => 'KINMA',
            'province' => 'Kinshasa',
            'city' => 'Kinshasa',
        ]);
        $blockedAgency = Agency::create([
            'name' => 'Lubumbashi Texaco',
            'code' => 'LUBTEX',
            'province' => 'Haut-Katanga',
            'city' => 'Lubumbashi',
        ]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisor->supervisedAgencies()->sync([$allowedAgency->id]);

        $token = $this->tokenFor($supervisor);

        $this->withToken($token)->postJson('/api/staff', [
            'name' => 'Gerant Autorise',
            'email' => 'allowed-manager@example.test',
            'password' => 'password-test',
            'role' => User::ROLE_MANAGER,
            'agency_id' => $allowedAgency->id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/staff', [
            'name' => 'Gerant Bloque',
            'email' => 'blocked-manager@example.test',
            'password' => 'password-test',
            'role' => User::ROLE_MANAGER,
            'agency_id' => $blockedAgency->id,
        ])->assertUnprocessable();
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
