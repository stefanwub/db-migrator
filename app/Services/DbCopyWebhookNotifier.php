<?php

namespace App\Services;

use App\Models\DbCopy;
use Illuminate\Support\Facades\Http;

class DbCopyWebhookNotifier
{
    /**
     * Send a status update webhook for the given DB copy.
     */
    public function notify(DbCopy $dbCopy): void
    {
        if ($dbCopy->callback_url === null || $dbCopy->callback_url === '') {
            return;
        }

        $payload = [
            'id' => $dbCopy->id,
            'status' => $dbCopy->status,
            'started_at' => $dbCopy->started_at?->toIso8601String(),
            'finished_at' => $dbCopy->finished_at?->toIso8601String(),
            'error' => $dbCopy->last_error,
        ];

        $secret = (string) config('services.db_copy_webhook.secret', '');

        if ($secret === '') {
            return;
        }

        $signature = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES), $secret);

        Http::retry(3, 1000)
            ->withHeaders([
                'X-Signature' => $signature,
            ])
            ->asJson()
            ->post($dbCopy->callback_url, $payload);
    }
}

