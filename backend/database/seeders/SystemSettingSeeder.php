<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'transfer_fees'],
            [
                'value' => [
                    'USD' => ['type' => 'percentage', 'rate' => 2.0, 'minimum' => 1.0],
                    'CDF' => ['type' => 'percentage', 'rate' => 2.0, 'minimum' => 1000.0],
                ],
                'description' => 'Regles de frais appliquees automatiquement aux transferts.',
            ],
        );
    }
}
