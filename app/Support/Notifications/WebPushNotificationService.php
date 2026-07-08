<?php

namespace App\Support\Notifications;

use App\Models\Settings\NotificationLog;
use App\Models\Settings\NotificationSetting;
use App\Models\Settings\PushNotificationSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushNotificationService
{
    public function send(User $user, string $eventKey, string $subject, string $body, array $data = [], ?Model $source = null): ?NotificationLog
    {
        if (! $user->exists) {
            return null;
        }

        $log = NotificationLog::query()->create([
            'event_key' => $eventKey,
            'channel' => 'web_push',
            'provider' => 'vapid',
            'status' => 'queued',
            'user_id' => $user->id,
            'recipient_name' => $user->name,
            'recipient_email' => $user->email,
            'subject' => $subject,
            'body' => $body,
            'payload' => $data,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);

        if (! NotificationSetting::current()->web_push_enabled) {
            return $this->skip($log, 'Web push channel is disabled.');
        }

        if (! $this->configured()) {
            return $this->skip($log, 'Web push VAPID keys are not configured.');
        }

        $subscriptions = PushNotificationSubscription::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->skip($log, 'Recipient has no active browser subscription.');
        }

        $payload = json_encode([
            'title' => $subject,
            'body' => $body,
            'url' => $data['url'] ?? route('home.profile-index'),
            'event_key' => $eventKey,
        ], JSON_THROW_ON_ERROR);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.web_push.subject'),
                    'publicKey' => config('services.web_push.public_key'),
                    'privateKey' => config('services.web_push.private_key'),
                ],
            ]);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification($this->subscription($subscription), $payload);
            }

            $sent = 0;
            $failed = 0;
            $lastError = null;

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;

                    continue;
                }

                $failed++;
                $lastError = $report->getReason();

                if ($report->isSubscriptionExpired()) {
                    PushNotificationSubscription::query()
                        ->where('endpoint_hash', hash('sha256', $report->getEndpoint()))
                        ->update(['revoked_at' => now()]);
                }
            }

            $log->forceFill([
                'status' => $sent > 0 ? 'sent' : 'failed',
                'provider_response' => [
                    'sent' => $sent,
                    'failed' => $failed,
                ],
                'error_message' => $sent > 0 ? null : $lastError,
                'attempt_count' => $log->attempt_count + 1,
                'sent_at' => $sent > 0 ? now() : null,
                'failed_at' => $sent > 0 ? null : now(),
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Web push notification failed.', [
                'event_key' => $eventKey,
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            $log->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'attempt_count' => $log->attempt_count + 1,
                'failed_at' => now(),
            ])->save();
        }

        return $log->refresh();
    }

    private function configured(): bool
    {
        return filled(config('services.web_push.public_key'))
            && filled(config('services.web_push.private_key'))
            && filled(config('services.web_push.subject'));
    }

    private function subscription(PushNotificationSubscription $subscription): Subscription
    {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding,
        ]);
    }

    private function skip(NotificationLog $log, string $message): NotificationLog
    {
        $log->forceFill([
            'status' => 'skipped',
            'error_message' => $message,
        ])->save();

        return $log->refresh();
    }
}
