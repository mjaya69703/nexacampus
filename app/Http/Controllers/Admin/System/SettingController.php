<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\Settings\Campus;
use App\Models\Settings\NotificationSetting;
use App\Models\Settings\System;
use App\Models\User;
use App\Support\Inertia\ShellProps;
use App\Support\Notifications\NotificationDispatchService;
use App\Support\Notifications\WebPushNotificationService;
use App\Support\Notifications\WhatsAppProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Kstmostofa\LaravelWhatsApp\Exceptions\SidecarException;
use Kstmostofa\LaravelWhatsApp\Web\SidecarManager;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;

/**
 * Pengaturan sistem — singleton 3 model (paritas settings Blade).
 * Satu-satunya resource yang memakai setting.viewAny untuk semua aksi
 * tulis (registry hanya mendaftarkan viewAny untuk settings).
 */
class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $system = System::first() ?? new System();
        $campus = Campus::first() ?? new Campus();
        $notification = NotificationSetting::current();

        return Inertia::render('Admin/System/Setting/Index', [
            'shell' => ShellProps::make($request->user(), 'Sistem', 'Pengaturan Sistem'),
            'system' => [
                'app_name' => $system->app_name, 'app_version' => $system->app_version,
                'app_description' => $system->app_description, 'app_url' => $system->app_url,
                'app_email' => $system->app_email, 'maintenance_mode' => (bool) $system->maintenance_mode,
                'enable_captcha' => (bool) $system->enable_captcha,
                'max_login_attempts' => $system->max_login_attempts,
                'login_decay_seconds' => $system->login_decay_seconds,
                'logo_vertikal_url' => $system->app_logo_vertikal,
                'logo_horizontal_url' => $system->app_logo_horizontal,
                'favicon_url' => $system->app_favicon,
            ],
            'campus' => [
                'name' => $campus->name, 'domain' => $campus->domain, 'phone' => $campus->phone,
                'faximile' => $campus->faximile, 'whatsapp' => $campus->whatsapp,
                'email_info' => $campus->email_info, 'email_humas' => $campus->email_humas,
                'address' => $campus->address, 'city' => $campus->city, 'province' => $campus->province,
                'postal_code' => $campus->postal_code, 'latitude' => $campus->latitude,
                'longitude' => $campus->longitude, 'instagram' => $campus->instagram,
                'facebook' => $campus->facebook, 'linkedin' => $campus->linkedin,
                'xtwitter' => $campus->xtwitter, 'tiktok' => $campus->tiktok,
            ],
            'notification' => $this->settingToForm($notification),
            'health' => app(WhatsAppProviderManager::class)->health($notification),
            'sidecar' => $this->sidecarStatus(),
            'suggestedSession' => $request->session()->get('suggested_session'),
            'urls' => [
                'index' => route('admin.system.settings.index'),
                'update' => route('admin.system.settings.update'),
                'checkHealth' => route('admin.system.settings.check-health'),
                'testWhatsapp' => route('admin.system.settings.test-whatsapp'),
                'testPush' => route('admin.system.settings.test-push'),
                'sidecarStart' => route('admin.system.settings.sidecar-start'),
                'sidecarStop' => route('admin.system.settings.sidecar-stop'),
                'sidecarRefresh' => route('admin.system.settings.sidecar-refresh'),
                'useSession' => route('admin.system.settings.use-session'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'system.app_name' => 'required|string|max:255',
            'system.app_version' => 'nullable|string|max:50',
            'system.app_description' => 'nullable|string|max:1000',
            'system.app_url' => 'nullable|url|max:255',
            'system.app_email' => 'nullable|email|max:255',
            'system.maintenance_mode' => 'boolean',
            'system.enable_captcha' => 'boolean',
            'system.max_login_attempts' => 'nullable|integer|min:1|max:10',
            'system.login_decay_seconds' => 'nullable|integer|min:30|max:3600',
            'favicon' => 'nullable|image|mimes:png,ico|max:2048',
            'logo_vertikal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'logo_horizontal' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'campus.name' => 'required|string|max:255',
            'campus.domain' => 'nullable|string|max:255',
            'campus.phone' => 'nullable|string|max:20',
            'campus.faximile' => 'nullable|string|max:20',
            'campus.whatsapp' => 'nullable|string|max:20',
            'campus.email_info' => 'nullable|email|max:255',
            'campus.email_humas' => 'nullable|email|max:255',
            'campus.address' => 'nullable|string|max:1000',
            'campus.city' => 'nullable|string|max:255',
            'campus.province' => 'nullable|string|max:255',
            'campus.postal_code' => 'nullable|string|max:20',
            'campus.latitude' => 'nullable|numeric|between:-90,90',
            'campus.longitude' => 'nullable|numeric|between:-180,180',
            'campus.instagram' => 'nullable|string|max:255',
            'campus.facebook' => 'nullable|string|max:255',
            'campus.linkedin' => 'nullable|string|max:255',
            'campus.xtwitter' => 'nullable|string|max:255',
            'campus.tiktok' => 'nullable|string|max:255',
            'notification.whatsapp_enabled' => 'boolean',
            'notification.web_push_enabled' => 'boolean',
            'notification.whatsapp_provider' => 'required|in:official_cloud_api,unofficial_web_session',
            'notification.fallback_channel' => 'required|in:in_app,email,none',
            'notification.retry_attempts' => 'required|integer|min:0|max:5',
            'notification.timeout_seconds' => 'required|integer|min:5|max:120',
            'notification.log_retention_days' => 'required|integer|min:7|max:3650',
            'notification.provider_response_retention_days' => 'required|integer|min:1|max:3650|lte:notification.log_retention_days',
            'notification.official_config.access_token' => 'nullable|string|max:2000',
            'notification.official_config.phone_number_id' => 'nullable|string|max:255',
            'notification.official_config.business_account_id' => 'nullable|string|max:255',
            'notification.official_config.app_secret' => 'nullable|string|max:2000',
            'notification.official_config.verify_token' => 'nullable|string|max:255',
            'notification.unofficial_config.sidecar_url' => 'nullable|url|starts_with:http://,https://|max:255',
            'notification.unofficial_config.session_name' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
            'notification.unofficial_config.shared_token' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $system = System::first() ?? new System();
            $system->app_name = $validated['system']['app_name'];
            // Kolom string NOT NULL tanpa default: null berarti "tidak diubah".
            foreach (['app_version', 'app_description', 'app_url', 'app_email'] as $column) {
                $system->$column = $validated['system'][$column] ?? $system->getAttribute($column) ?? '';
            }
            $system->maintenance_mode = $validated['system']['maintenance_mode'] ?? false;
            $system->enable_captcha = $validated['system']['enable_captcha'] ?? false;
            $system->max_login_attempts = $validated['system']['max_login_attempts'] ?? 5;
            $system->login_decay_seconds = $validated['system']['login_decay_seconds'] ?? 300;

            foreach ([
                'favicon' => 'app_favicon',
                'logo_vertikal' => 'app_logo_vertikal',
                'logo_horizontal' => 'app_logo_horizontal',
            ] as $input => $column) {
                if ($request->hasFile($input)) {
                    if ($system->$column) {
                        Storage::disk('public')->delete('images/logo/'.$system->$column);
                    }

                    $file = $request->file($input);
                    $filename = $file->hashName();
                    $file->store('images/logo', 'public');
                    $system->$column = $filename;
                }
            }

            $system->save();

            $campus = Campus::first() ?? new Campus();
            $campusData = $validated['campus'];

            // Kolom faximile tidak ada di migrasi (baris dikomentari):
            // hanya teruskan bila kolomnya benar-benar ada.
            if (! Schema::hasColumn('campuses', 'faximile')) {
                unset($campusData['faximile']);
            }

            foreach (['domain', 'phone', 'whatsapp', 'email_info', 'email_humas'] as $column) {
                if (($campusData[$column] ?? null) === null) {
                    $campusData[$column] = $campus->getAttribute($column) ?? '';
                }
            }

            $campus->fill($campusData);
            $campus->save();

            $notification = NotificationSetting::current();
            $notification->fill([
                'whatsapp_enabled' => $validated['notification']['whatsapp_enabled'] ?? false,
                'web_push_enabled' => $validated['notification']['web_push_enabled'] ?? false,
                'whatsapp_provider' => $validated['notification']['whatsapp_provider'],
                'fallback_channel' => $validated['notification']['fallback_channel'],
                'retry_attempts' => $validated['notification']['retry_attempts'],
                'timeout_seconds' => $validated['notification']['timeout_seconds'],
                'log_retention_days' => $validated['notification']['log_retention_days'],
                'provider_response_retention_days' => $validated['notification']['provider_response_retention_days'],
                'official_config' => $validated['notification']['official_config'] ?? [],
                'unofficial_config' => $validated['notification']['unofficial_config'] ?? [],
            ]);
            $notification->save();
        });

        Cache::forget('global_campus');
        Cache::forget('global_system');

        return redirect()->route('admin.system.settings.index')
            ->with('success', 'Pengaturan kampus berhasil disimpan.');
    }

    public function checkHealth()
    {
        $health = app(WhatsAppProviderManager::class)
            ->persistHealth(NotificationSetting::current()->fresh());

        return back()->with(
            $health['status'] === 'ready' ? 'success' : 'warning',
            $health['message']
        );
    }

    public function sendTestWhatsapp(Request $request)
    {
        $validated = $request->validate(['recipient' => 'required|string|max:30']);
        $auth = $request->user();

        $recipient = new User([
            'first_name' => $auth?->first_name ?: 'Admin',
            'last_name' => $auth?->last_name ?: '',
            'email' => $auth?->email ?: 'admin@example.test',
            'phone' => $validated['recipient'],
        ]);

        $setting = NotificationSetting::current()->fresh();
        $log = app(NotificationDispatchService::class)->whatsapp($recipient, 'system.whatsapp_test', [
            'recipient_name' => $recipient->name,
            'provider' => str_replace('_', ' ', $setting->whatsapp_provider),
        ]);

        return back()->with(
            $log->status === 'sent' ? 'success' : 'warning',
            'Test WhatsApp status: '.$log->status.($log->error_message ? ' - '.$log->error_message : '')
        );
    }

    public function sendTestPush(Request $request)
    {
        $user = $request->user();

        $log = app(WebPushNotificationService::class)->send(
            user: $user,
            eventKey: 'system.web_push_test',
            subject: 'Test Web Push',
            body: 'Web push notifications are working! 🎉',
            data: ['url' => route('home.profile-index')]
        );

        $message = match ($log->status) {
            'sent' => 'Test push berhasil dikirim ke browser!',
            'skipped' => $log->error_message ?: 'Web push belum dikonfigurasi.',
            'failed' => 'Gagal mengirim: '.$log->error_message,
            default => 'Status tidak diketahui.',
        };

        $type = match ($log->status) {
            'sent' => 'success',
            'skipped' => 'warning',
            'failed' => 'error',
            default => 'warning',
        };

        return back()->with($type, $message);
    }

    public function sidecarStart()
    {
        $manager = app(SidecarManager::class);
        $message = 'WhatsApp sidecar siap digunakan.';
        $status = 'success';

        try {
            if (! app(WebClient::class)->ping()) {
                $pid = $manager->start();
                $message = 'WhatsApp sidecar sedang dinyalakan (pid '.$pid.').';
            }

            if (method_exists($manager, 'restorePersistedSessions')) {
                $restored = $manager->restorePersistedSessions();

                if ($restored !== []) {
                    $message .= ' Session tersimpan dipulihkan: '.implode(', ', $restored).'.';
                }
            }

            $this->waitForSidecar();
        } catch (SidecarException $exception) {
            $status = 'warning';
            $message = $exception->getMessage();
        } catch (\Throwable $exception) {
            $status = 'warning';
            $message = 'WhatsApp sidecar belum bisa dinyalakan: '.$exception->getMessage();
        }

        return back()->with($status, $message.' '.$this->sidecarStatus()['message']);
    }

    public function sidecarStop()
    {
        $manager = app(SidecarManager::class);
        $status = 'success';
        $message = 'WhatsApp sidecar sudah dihentikan.';

        try {
            if (! $manager->stop()) {
                $status = 'warning';
                $message = 'Tidak ada proses sidecar yang tercatat aktif.';
            }

            $this->waitForSidecarStop();
        } catch (\Throwable $exception) {
            $status = 'warning';
            $message = 'WhatsApp sidecar belum bisa dihentikan: '.$exception->getMessage();
        }

        return back()->with($status, $message.' '.$this->sidecarStatus()['message']);
    }

    public function sidecarRefresh()
    {
        $status = $this->sidecarStatus();

        return back()->with(
            $status['reachable'] ? 'success' : 'warning',
            'Status sidecar diperbarui: '.$status['message']
        );
    }

    public function useSession(Request $request)
    {
        $validated = $request->validate(['session' => 'required|string|max:64|regex:/^[A-Za-z0-9_\-]+$/']);

        return back()
            ->with('suggested_session', $validated['session'])
            ->with('success', 'Session Name diganti ke '.$validated['session'].'. Simpan pengaturan agar dipakai untuk pengiriman.');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function settingToForm(NotificationSetting $setting): array
    {
        return [
            'whatsapp_enabled' => (bool) $setting->whatsapp_enabled,
            'web_push_enabled' => (bool) $setting->web_push_enabled,
            'whatsapp_provider' => $setting->whatsapp_provider ?: NotificationSetting::PROVIDER_OFFICIAL,
            'fallback_channel' => $setting->fallback_channel ?: 'in_app',
            'retry_attempts' => $setting->retry_attempts ?? 3,
            'timeout_seconds' => $setting->timeout_seconds ?? 15,
            'log_retention_days' => $setting->log_retention_days ?? 90,
            'provider_response_retention_days' => $setting->provider_response_retention_days ?? 30,
            'official_config' => array_merge([
                'access_token' => '', 'phone_number_id' => '', 'business_account_id' => '',
                'app_secret' => '', 'verify_token' => '',
            ], $setting->official_config ?? []),
            'unofficial_config' => array_merge([
                'sidecar_url' => '', 'session_name' => 'main', 'shared_token' => '',
            ], $setting->unofficial_config ?? []),
        ];
    }

    /**
     * @return array{reachable: bool, message: string, sessions: array<int, string>}
     */
    private function sidecarStatus(): array
    {
        $manager = app(SidecarManager::class);
        $sessions = method_exists($manager, 'persistedSessionIds') ? $manager->persistedSessionIds() : [];

        try {
            $reachable = app(WebClient::class)->ping();

            return [
                'reachable' => $reachable,
                'message' => $reachable
                    ? 'Sidecar bawaan aktif di http://'.config('laravel-whatsapp.web.host').':'.config('laravel-whatsapp.web.port')
                    : 'Sidecar bawaan belum aktif.',
                'sessions' => $sessions,
            ];
        } catch (\Throwable) {
            return ['reachable' => false, 'message' => 'Sidecar bawaan belum aktif.', 'sessions' => $sessions];
        }
    }

    private function waitForSidecar(): void
    {
        for ($i = 0; $i < 30; $i++) {
            usleep(250_000);

            try {
                if (app(WebClient::class)->ping()) {
                    return;
                }
            } catch (\Throwable) {
                //
            }
        }
    }

    private function waitForSidecarStop(): void
    {
        for ($i = 0; $i < 20; $i++) {
            usleep(150_000);

            try {
                if (! app(WebClient::class)->ping()) {
                    return;
                }
            } catch (\Throwable) {
                return;
            }
        }
    }
}
