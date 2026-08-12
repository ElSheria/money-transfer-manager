<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Transfer;
use App\Models\User;
use App\Services\TransferExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TransferExportService $transferExportService): JsonResponse
    {
        $user = $request->user();
        $transferQuery = $transferExportService->queryFor($user, $request);

        return response()->json([
            'data' => [
                'totals' => [
                    'agencies' => $this->agencyCount($user),
                    'supervisors' => $user->isDg() ? User::query()->where('role', User::ROLE_SUPERVISOR)->count() : null,
                    'managers' => $this->managerCount($user),
                    'transfers' => (clone $transferQuery)->count(),
                    'validated_transfers' => (clone $transferQuery)->where('status', Transfer::STATUS_VALIDATED)->count(),
                    'withdrawn_transfers' => (clone $transferQuery)->where('status', Transfer::STATUS_WITHDRAWN)->count(),
                    'cancelled_transfers' => (clone $transferQuery)->where('status', Transfer::STATUS_CANCELLED)->count(),
                ],
                'amounts_by_currency' => (clone $transferQuery)
                    ->select('currency', DB::raw('SUM(amount) as total_amount'), DB::raw('SUM(fee) as total_fee'))
                    ->groupBy('currency')
                    ->get(),
                'recent_transfers' => (clone $transferQuery)
                    ->limit(10)
                    ->get(),
            ],
        ]);
    }

    private function agencyCount(User $user): int
    {
        if ($user->isDg()) {
            return Agency::query()->count();
        }

        if ($user->isSupervisor()) {
            return $user->supervisedAgencies()->count();
        }

        return $user->agency_id ? 1 : 0;
    }

    private function managerCount(User $user): int
    {
        if ($user->isDg()) {
            return User::query()->where('role', User::ROLE_MANAGER)->count();
        }

        if ($user->isSupervisor()) {
            return User::query()
                ->where('role', User::ROLE_MANAGER)
                ->whereIn('agency_id', $user->supervisedAgencies()->pluck('agencies.id'))
                ->count();
        }

        return 0;
    }
}
