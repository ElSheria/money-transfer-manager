<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => Agency::query()
                ->when($user->isSupervisor(), fn ($query) => $query->whereHas('supervisors', fn ($query) => $query->whereKey($user->id)))
                ->when($user->isManager(), fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $agency = Agency::create($this->validatedData($request));

        $auditLogger->log($request, 'agency.created', $agency);

        return response()->json(['data' => $agency], 201);
    }

    public function show(Request $request, Agency $agency): JsonResponse
    {
        if (! $this->canAccessAgency($request, $agency)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        return response()->json(['data' => $agency->load(['managers', 'supervisors'])]);
    }

    public function update(Request $request, Agency $agency, AuditLogger $auditLogger): JsonResponse
    {
        if (! $this->canAccessAgency($request, $agency)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        $agency->update($this->validatedData($request, $agency));
        $auditLogger->log($request, 'agency.updated', $agency);

        return response()->json(['data' => $agency->refresh()]);
    }

    public function destroy(Request $request, Agency $agency, AuditLogger $auditLogger): JsonResponse
    {
        if (! $this->canAccessAgency($request, $agency)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        $agency->update(['is_active' => false]);
        $auditLogger->log($request, 'agency.deactivated', $agency);

        return response()->json(['message' => 'Agence desactivee.']);
    }

    private function validatedData(Request $request, ?Agency $agency = null): array
    {
        $agencyId = $agency?->id ?? 'NULL';

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'unique:agencies,code,'.$agencyId],
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function canAccessAgency(Request $request, Agency $agency): bool
    {
        $user = $request->user();

        return $user->isDg()
            || $user->supervisesAgency($agency->id)
            || ($user->isManager() && $agency->is_active);
    }
}
