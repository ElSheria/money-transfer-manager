<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Customer;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TransferCodeService;
use App\Services\TransferExportService;
use App\Services\TransferFeeService;
use App\Services\TransferNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TransferController extends Controller
{
    public function index(Request $request, TransferExportService $exportService): JsonResponse
    {
        $request->validate($this->exportRules());

        $transfers = $exportService->queryFor($request->user(), $request)
            ->paginate(20);

        return response()->json($transfers);
    }

    public function store(
        Request $request,
        TransferCodeService $codeService,
        TransferNotificationService $notificationService,
        TransferFeeService $feeService,
        AuditLogger $auditLogger
    ): JsonResponse {
        $data = $request->validate($this->storeRules());
        $manager = $request->user();

        if (! $manager->isManager() || (int) $data['source_agency_id'] !== (int) $manager->agency_id) {
            return response()->json(['message' => 'Un gerant cree seulement les transferts de son agence.'], 403);
        }

        $transfer = DB::transaction(function () use ($data, $manager, $codeService, $feeService) {
            $sourceAgency = Agency::query()->findOrFail($data['source_agency_id']);
            $destinationAgency = Agency::query()->findOrFail($data['destination_agency_id']);

            $sender = Customer::create($data['sender']);
            $receiver = Customer::create($data['receiver']);

            return Transfer::create([
                'code' => $codeService->generate($sourceAgency, $destinationAgency),
                'sender_customer_id' => $sender->id,
                'receiver_customer_id' => $receiver->id,
                'source_agency_id' => $sourceAgency->id,
                'destination_agency_id' => $destinationAgency->id,
                'manager_id' => $manager->id,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'fee' => $feeService->calculate((float) $data['amount'], $data['currency']),
                'reason' => $data['reason'] ?? null,
                'status' => Transfer::STATUS_VALIDATED,
                'sent_at' => now(),
            ]);
        });

        $notificationService->queueFor($transfer);
        $auditLogger->log($request, 'transfer.created', $transfer);

        return response()->json([
            'data' => $transfer->load(['sender', 'receiver', 'sourceAgency', 'destinationAgency', 'notifications']),
        ], 201);
    }

    public function show(Request $request, Transfer $transfer): JsonResponse
    {
        if (! $this->canAccessTransfer($request->user(), $transfer)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        return response()->json([
            'data' => $transfer->load(['sender', 'receiver', 'sourceAgency', 'destinationAgency', 'manager', 'withdrawnBy', 'notifications']),
        ]);
    }

    public function findByCode(Request $request, string $code): JsonResponse
    {
        $transfer = Transfer::query()
            ->where('code', strtoupper($code))
            ->firstOrFail();

        if (! $this->canAccessTransfer($request->user(), $transfer)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        return response()->json([
            'data' => $transfer->load(['sender', 'receiver', 'sourceAgency', 'destinationAgency', 'manager', 'withdrawnBy']),
        ]);
    }

    public function withdraw(Request $request, Transfer $transfer, AuditLogger $auditLogger): JsonResponse
    {
        $manager = $request->user();

        if (! $manager->isManager() || (int) $transfer->destination_agency_id !== (int) $manager->agency_id) {
            return response()->json(['message' => 'Retrait non autorise pour cette agence.'], 403);
        }

        if ($transfer->status === Transfer::STATUS_WITHDRAWN) {
            return response()->json(['message' => 'Ce transfert est deja retire.'], 422);
        }

        if ($transfer->status === Transfer::STATUS_CANCELLED) {
            return response()->json(['message' => 'Un transfert annule ne peut pas etre retire.'], 422);
        }

        $transfer->update([
            'status' => Transfer::STATUS_WITHDRAWN,
            'withdrawn_by' => $manager->id,
            'withdrawn_at' => now(),
        ]);
        $auditLogger->log($request, 'transfer.withdrawn', $transfer);

        return response()->json(['data' => $transfer->refresh()->load(['receiver', 'destinationAgency', 'withdrawnBy'])]);
    }

    public function cancel(Request $request, Transfer $transfer, AuditLogger $auditLogger): JsonResponse
    {
        if (! $this->canCancelTransfer($request->user(), $transfer)) {
            return response()->json(['message' => 'Annulation non autorisee.'], 403);
        }

        if ($transfer->status === Transfer::STATUS_WITHDRAWN) {
            return response()->json(['message' => 'Un transfert deja retire ne peut pas etre annule.'], 422);
        }

        $transfer->update(['status' => Transfer::STATUS_CANCELLED]);
        $auditLogger->log($request, 'transfer.cancelled', $transfer);

        return response()->json(['data' => $transfer->refresh()]);
    }

    public function exportCsv(Request $request, TransferExportService $exportService): Response
    {
        $request->validate($this->exportRules());

        $content = $exportService->csv($exportService->rows($request->user(), $request));

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="historique-transferts.csv"',
        ]);
    }

    public function exportPdf(Request $request, TransferExportService $exportService): Response
    {
        $request->validate($this->exportRules());

        $content = $exportService->pdf($exportService->rows($request->user(), $request));

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="historique-transferts.pdf"',
        ]);
    }

    public function exportExcel(Request $request, TransferExportService $exportService): Response
    {
        $request->validate($this->exportRules());

        $content = $exportService->xlsx($exportService->rows($request->user(), $request));

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="historique-transferts.xlsx"',
        ]);
    }

    public function receipt(Request $request, Transfer $transfer, TransferExportService $exportService): Response
    {
        if (! $this->canAccessTransfer($request->user(), $transfer)) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        return response($exportService->receipt($transfer), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="recu-'.$transfer->code.'.pdf"',
        ]);
    }

    private function canAccessTransfer(User $user, Transfer $transfer): bool
    {
        return $user->isDg()
            || ($user->isSupervisor() && (
                $user->supervisesAgency((int) $transfer->source_agency_id)
                || $user->supervisesAgency((int) $transfer->destination_agency_id)
            ))
            || ((int) $transfer->source_agency_id === (int) $user->agency_id)
            || ((int) $transfer->destination_agency_id === (int) $user->agency_id);
    }

    private function canCancelTransfer(User $user, Transfer $transfer): bool
    {
        return $user->isDg()
            || ($user->isSupervisor() && $this->canAccessTransfer($user, $transfer))
            || ($user->isManager() && (int) $transfer->source_agency_id === (int) $user->agency_id);
    }

    private function storeRules(): array
    {
        $customerRules = [
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
        ];

        return [
            'sender' => ['required', 'array'],
            'receiver' => ['required', 'array'],
            ...collect($customerRules)->mapWithKeys(fn ($rules, $field) => ['sender.'.$field => $rules])->all(),
            ...collect($customerRules)->mapWithKeys(fn ($rules, $field) => ['receiver.'.$field => $rules])->all(),
            'source_agency_id' => ['required', Rule::exists('agencies', 'id')->where('is_active', true)],
            'destination_agency_id' => ['required', 'different:source_agency_id', Rule::exists('agencies', 'id')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'in:USD,CDF'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function exportRules(): array
    {
        return [
            // Date de début facultative.
            'from' => [
                'nullable',
                'date',
            ],

            // La date de fin ne peut pas être antérieure
            // à la date de début.
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
            ],

            // L'agence doit réellement exister.
            'agency_id' => [
                'nullable',
                'integer',
                'exists:agencies,id',
            ],

            // Notre application accepte uniquement USD et CDF.
            'currency' => [
                'nullable',
                'in:USD,CDF',
            ],

            // Empêche l'utilisation de statuts inventés.
            'status' => [
                'nullable',
                Rule::in([
                    Transfer::STATUS_VALIDATED,
                    Transfer::STATUS_WITHDRAWN,
                    Transfer::STATUS_CANCELLED,
                ]),
            ],
        ];
    }
}
