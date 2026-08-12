<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $staff = User::query()
            ->with(['agency', 'supervisedAgencies'])
            ->when($user->isSupervisor(), function ($query) use ($user) {
                $query->where('role', User::ROLE_MANAGER)
                    ->whereIn('agency_id', $user->supervisedAgencies()->pluck('agencies.id'));
            })
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $staff]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $actor = $request->user();
        $data = $this->validatedData($request);

        $supervisedAgencyIds = $data['supervised_agency_ids'] ?? [];
        unset($data['supervised_agency_ids']);

        if (! $actor->canManageRole($data['role'])) {
            throw ValidationException::withMessages([
                'role' => ['Vous ne pouvez pas attribuer ce role.'],
            ]);
        }

        if ($actor->isSupervisor() && ! $actor->supervisesAgency((int) $data['agency_id'])) {
            throw ValidationException::withMessages([
                'agency_id' => ['Cette agence ne fait pas partie de votre supervision.'],
            ]);
        }

        $staff = User::create([...$data, 'created_by' => $actor->id]);

        if ($staff->isSupervisor()) {
            $staff->supervisedAgencies()->sync($supervisedAgencyIds);
        }

        $auditLogger->log($request, 'staff.created', $staff);

        return response()->json(['data' => $staff->load(['agency', 'supervisedAgencies'])], 201);
    }

    public function update(Request $request, User $staff, AuditLogger $auditLogger): JsonResponse
    {
        $actor = $request->user();
        $data = $this->validatedData($request, $staff);
        $supervisedAgencyIds = $data['supervised_agency_ids'] ?? [];
        unset($data['supervised_agency_ids']);

        if ($staff->isDg() || ! $actor->canManageRole($staff->role) || ! $actor->canManageRole($data['role'])) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        if ($actor->isSupervisor() && ! $actor->supervisesAgency((int) $data['agency_id'])) {
            return response()->json(['message' => 'Cette agence ne fait pas partie de votre supervision.'], 403);
        }

        $staff->update($data);

        if ($staff->isSupervisor()) {
            $staff->supervisedAgencies()->sync($supervisedAgencyIds);
        } else {
            $staff->supervisedAgencies()->detach();
        }

        $auditLogger->log($request, 'staff.updated', $staff);

        return response()->json(['data' => $staff->refresh()->load(['agency', 'supervisedAgencies'])]);
    }

    public function destroy(Request $request, User $staff, AuditLogger $auditLogger): JsonResponse
    {
        $actor = $request->user();

        if ($staff->isDg() || ! $actor->canManageRole($staff->role)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        if ($actor->isSupervisor() && ! $actor->supervisesAgency((int) $staff->agency_id)) {
            return response()->json(['message' => 'Cette agence ne fait pas partie de votre supervision.'], 403);
        }

        $staff->update(['is_active' => false]);
        $auditLogger->log($request, 'staff.deactivated', $staff);

        return response()->json(['message' => 'Utilisateur desactive.']);
    }

    private function validatedData(Request $request, ?User $staff = null): array
    {
        $staffId = $staff?->id ?? 'NULL';

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$staffId],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => [$staff ? 'sometimes' : 'required', 'string', 'min:8'],
            'role' => ['required', 'in:'.implode(',', [User::ROLE_SUPERVISOR, User::ROLE_MANAGER])],
            'agency_id' => ['nullable', 'required_if:role,'.User::ROLE_MANAGER, 'exists:agencies,id'],
            'supervised_agency_ids' => ['sometimes', 'array'],
            'supervised_agency_ids.*' => ['integer', 'exists:agencies,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
