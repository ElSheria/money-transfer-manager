<?php

use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:send-pending {--limit=50}', function (NotificationDeliveryService $deliveryService) {
    $summary = $deliveryService->sendPending((int) $this->option('limit'));

    $this->info("Sent: {$summary['sent']}");
    $this->info("Failed: {$summary['failed']}");
    $this->info("Skipped: {$summary['skipped']}");
})->purpose('Send queued transfer notifications');
