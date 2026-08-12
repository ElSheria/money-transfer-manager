<?php

namespace App\Services;

use App\Models\SystemSetting;

class TransferFeeService
{
    public function calculate(float $amount, string $currency): float
    {
        $fees = SystemSetting::query()
            ->where('key', 'transfer_fees')
            ->value('value') ?? $this->defaultRules();

        $rule = $fees[$currency] ?? null;

        if (! $rule) {
            return 0.0;
        }

        $fee = match ($rule['type'] ?? 'percentage') {
            'fixed' => (float) ($rule['amount'] ?? 0),
            default => $amount * ((float) ($rule['rate'] ?? 0) / 100),
        };

        return round(max($fee, (float) ($rule['minimum'] ?? 0)), 2);
    }

    private function defaultRules(): array
    {
        return [
            'USD' => ['type' => 'percentage', 'rate' => 2.0, 'minimum' => 1.0],
            'CDF' => ['type' => 'percentage', 'rate' => 2.0, 'minimum' => 1000.0],
        ];
    }
}
