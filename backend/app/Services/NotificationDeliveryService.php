<?php

namespace App\Services;

use App\Models\TransferNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationDeliveryService
{
    public function sendPending(int $limit = 50): array
    {
        $summary = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        TransferNotification::query()
            ->where('status', 'queued')
            ->oldest()
            ->limit($limit)
            ->get()
            ->each(function (TransferNotification $notification) use (&$summary) {
                $status = $this->send($notification);
                $summary[$status]++;
            });

        return $summary;
    }

    public function send(TransferNotification $notification): string
    {
        try {
            if ($notification->channel === 'email') {
                return $this->sendEmail($notification);
            }

            if ($notification->channel === 'whatsapp') {
                return $this->sendWhatsapp($notification);
            }
        } catch (Throwable $exception) {
            $notification->update([
                'status' => 'failed',
            ]);

            return 'failed';
        }

        $notification->update(['status' => 'skipped']);

        return 'skipped';
    }

    private function sendEmail(TransferNotification $notification): string
    {
        if (! $notification->recipient) {
            $notification->update(['status' => 'skipped']);

            return 'skipped';
        }

        Mail::raw($notification->message, function ($mail) use ($notification) {
            $mail->to($notification->recipient)
                ->subject('ABT-LACOLOMBE - Notification de transfert');
        });

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return 'sent';
    }

    private function sendWhatsapp(TransferNotification $notification): string
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        if (! $token || ! $phoneNumberId || ! $notification->recipient) {
            $notification->update(['status' => 'skipped']);

            return 'skipped';
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $notification->recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $notification->message,
                ],
            ]);

        if ($response->failed()) {
            $notification->update(['status' => 'failed']);

            return 'failed';
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return 'sent';
    }
}
