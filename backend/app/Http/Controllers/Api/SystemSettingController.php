<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SystemSetting::query()->orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, SystemSetting $systemSetting, AuditLogger $auditLogger): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'array'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $systemSetting->update($data);
        $auditLogger->log($request, 'system_setting.updated', $systemSetting);

        return response()->json(['data' => $systemSetting->refresh()]);
    }
}
