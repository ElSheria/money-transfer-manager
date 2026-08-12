<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transfer;

class TransferNotificationService
{
    public function queueFor(Transfer $transfer): void
    {
        $transfer->loadMissing(['sender', 'receiver', 'destinationAgency']);

        $this->queueMessagesForCustomer($transfer, $transfer->sender, 'sender', $this->senderMessage($transfer));
        $this->queueMessagesForCustomer($transfer, $transfer->receiver, 'receiver', $this->receiverMessage($transfer));
    }

    private function queueMessagesForCustomer(Transfer $transfer, Customer $customer, string $type, string $message): void
    {
        $channels = [
            'email' => $customer->email,
            'whatsapp' => $customer->phone,
        ];

        foreach ($channels as $channel => $recipient) {
            if (! $recipient) {
                continue;
            }

            $transfer->notifications()->create([
                'recipient_type' => $type,
                'channel' => $channel,
                'recipient' => $recipient,
                'message' => $message,
            ]);
        }
    }

    private function senderMessage(Transfer $transfer): string
    {
        return "ABT-LACOLOMBE\n\nBonjour {$transfer->sender->full_name},\n\nVotre transfert de {$transfer->amount} {$transfer->currency}, destine a {$transfer->receiver->full_name}, a bien ete valide.\n\nCode de transfert : {$transfer->code}\n\nLe beneficiaire pourra effectuer son retrait a l'agence de destination sur presentation du code.\n\nABT-LACOLOMBE\nVotre partenaire de confiance.";
    }

    private function receiverMessage(Transfer $transfer): string
    {
        return "ABT-LACOLOMBE\n\nBonjour {$transfer->receiver->full_name},\n\nVous avez recu un transfert de {$transfer->amount} {$transfer->currency} envoye par {$transfer->sender->full_name}.\n\nCode de transfert : {$transfer->code}\n\nVeuillez vous presenter a l'agence {$transfer->destinationAgency->name} avec ce code et une piece d'identite.\n\nABT-LACOLOMBE\nVotre partenaire de confiance.";
    }
}
