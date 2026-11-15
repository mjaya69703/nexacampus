// Pengaturan sistem — singleton 3 model bertab (Inertia React).
import { Head, router, useForm } from '@inertiajs/react';
import {
    BellRing, Building2, Cog, Image as ImageIcon, KeyRound, MapPin,
    MonitorSmartphone, RefreshCw, Send, Share2, ShieldCheck, SquarePlay, SquareSquare,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Health = { status: string; message: string; issues?: string[] };
type Sidecar = { reachable: boolean; message: string; sessions: string[] };

type Props = {
    shell: ShellProps;
    system: {
        app_name: string; app_version: string | null; app_description: string | null;
        app_url: string | null; app_email: string | null; maintenance_mode: boolean;
        enable_captcha: boolean; max_login_attempts: number | null; login_decay_seconds: number | null;
        logo_vertikal_url: string | null; logo_horizontal_url: string | null; favicon_url: string | null;
    };
    campus: Record<string, string | number | null>;
    notification: {
        whatsapp_enabled: boolean; web_push_enabled: boolean;
        whatsapp_provider: string; fallback_channel: string;
        retry_attempts: number; timeout_seconds: number;
        log_retention_days: number; provider_response_retention_days: number;
        official_config: Record<string, string>;
        unofficial_config: Record<string, string>;
    };
    health: Health;
    sidecar: Sidecar;
    suggestedSession: string | null;
    urls: {
        index: string; update: string; checkHealth: string; testWhatsapp: string; testPush: string;
        sidecarStart: string; sidecarStop: string; sidecarRefresh: string; useSession: string;
    };
};

type Tab = 'aplikasi' | 'kampus' | 'logo' | 'alamat' | 'sosmed' | 'keamanan' | 'notifikasi';

const TABS: { key: Tab; label: string; icon: typeof Cog }[] = [
    { key: 'aplikasi', label: 'Aplikasi', icon: Cog },
    { key: 'kampus', label: 'Kampus', icon: Building2 },
    { key: 'logo', label: 'Logo & Favicon', icon: ImageIcon },
    { key: 'alamat', label: 'Alamat', icon: MapPin },
    { key: 'sosmed', label: 'Sosial Media', icon: Share2 },
    { key: 'keamanan', label: 'Keamanan', icon: ShieldCheck },
    { key: 'notifikasi', label: 'Notifikasi', icon: BellRing },
];

export default function SettingIndex({ shell, system, campus, notification, health, sidecar, suggestedSession, urls }: Props) {
    const [tab, setTab] = useState<Tab>('aplikasi');
    const [recipient, setRecipient] = useState('');
    const [busy, setBusy] = useState<string | null>(null);

    const form = useForm({
        system: {
            app_name: system.app_name ?? '',
            app_version: system.app_version ?? '',
            app_description: system.app_description ?? '',
            app_url: system.app_url ?? '',
            app_email: system.app_email ?? '',
            maintenance_mode: system.maintenance_mode,
            enable_captcha: system.enable_captcha,
            max_login_attempts: (system.max_login_attempts ?? '') as number | '',
            login_decay_seconds: (system.login_decay_seconds ?? '') as number | '',
        },
        campus: Object.fromEntries(
            ['name', 'domain', 'phone', 'faximile', 'whatsapp', 'email_info', 'email_humas', 'address', 'city', 'province', 'postal_code', 'latitude', 'longitude', 'instagram', 'facebook', 'linkedin', 'xtwitter', 'tiktok']
                .map((key) => [key, (campus[key] ?? '') as string | number]),
        ) as Record<string, string | number>,
        notification: {
            whatsapp_enabled: notification.whatsapp_enabled,
            web_push_enabled: notification.web_push_enabled,
            whatsapp_provider: notification.whatsapp_provider,
            fallback_channel: notification.fallback_channel,
            retry_attempts: notification.retry_attempts,
            timeout_seconds: notification.timeout_seconds,
            log_retention_days: notification.log_retention_days,
            provider_response_retention_days: notification.provider_response_retention_days,
            official_config: { ...notification.official_config },
            unofficial_config: { ...notification.unofficial_config },
        },
        favicon: null as File | null,
        logo_vertikal: null as File | null,
        logo_horizontal: null as File | null,
    });

    useEffect(() => {
        if (suggestedSession) {
            form.setData('notification', {
                ...form.data.notification,
                unofficial_config: { ...form.data.notification.unofficial_config, session_name: suggestedSession },
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [suggestedSession]);

    const errors = form.errors as Record<string, string>;
    const isOfficial = form.data.notification.whatsapp_provider === 'official_cloud_api';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(urls.update, { forceFormData: true });
    };

    const run = (key: string, url: string, payload: Record<string, string> = {}) => {
        setBusy(key);
        router.post(url, payload, {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        });
    };

    const setNotif = (patch: Partial<typeof form.data.notification>) => {
        form.setData('notification', { ...form.data.notification, ...patch });
    };

    const setOfficial = (key: string, value: string) => {
        form.setData('notification', {
            ...form.data.notification,
            official_config: { ...form.data.notification.official_config, [key]: value },
        });
    };

    const setUnofficial = (key: string, value: string) => {
        form.setData('notification', {
            ...form.data.notification,
            unofficial_config: { ...form.data.notification.unofficial_config, [key]: value },
        });
    };

    const healthTone = health.status === 'ready' ? 'green' : health.status === 'disabled' ? 'gray' : 'amber';

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengaturan Sistem · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Cog}
                        eyebrow="Sistem"
                        title={system.app_name || 'Pengaturan Sistem'}
                        description={system.app_description || 'Kelola identitas aplikasi, kampus, keamanan, dan notifikasi.'}
                        badges={[health.status === 'ready' ? 'WhatsApp siap' : `WhatsApp: ${health.status}`, sidecar.reachable ? 'Sidecar aktif' : 'Sidecar mati']}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Pengaturan</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Bagian pengaturan">
                                {TABS.map(({ key, label, icon: Icon }) => (
                                    <button
                                        key={key}
                                        type="button"
                                        role="tab"
                                        aria-selected={tab === key}
                                        className={`crud-tab${tab === key ? ' active' : ''}`}
                                        onClick={() => setTab(key)}
                                    >
                                        <Icon size={13} /> {label}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                {tab === 'aplikasi' && (
                                    <div className="crud-grid">
                                        <TextField label="Nama aplikasi" required value={form.data.system.app_name} onChange={(e) => form.setData('system', { ...form.data.system, app_name: e.target.value })} error={errors['system.app_name']} />
                                        <TextField label="Versi" value={form.data.system.app_version} onChange={(e) => form.setData('system', { ...form.data.system, app_version: e.target.value })} error={errors['system.app_version']} />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField label="Deskripsi" value={form.data.system.app_description} onChange={(e) => form.setData('system', { ...form.data.system, app_description: e.target.value })} error={errors['system.app_description']} />
                                        </div>
                                        <TextField label="URL aplikasi" value={form.data.system.app_url} onChange={(e) => form.setData('system', { ...form.data.system, app_url: e.target.value })} error={errors['system.app_url']} />
                                        <TextField label="Email aplikasi" type="email" value={form.data.system.app_email} onChange={(e) => form.setData('system', { ...form.data.system, app_email: e.target.value })} error={errors['system.app_email']} />
                                    </div>
                                )}

                                {tab === 'kampus' && (
                                    <div className="crud-grid">
                                        <TextField label="Nama kampus" required value={String(form.data.campus.name ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, name: e.target.value })} error={errors['campus.name']} />
                                        <TextField label="Domain" value={String(form.data.campus.domain ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, domain: e.target.value })} error={errors['campus.domain']} />
                                        <TextField label="Telepon" value={String(form.data.campus.phone ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, phone: e.target.value })} error={errors['campus.phone']} />
                                        <TextField label="Faximile" value={String(form.data.campus.faximile ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, faximile: e.target.value })} error={errors['campus.faximile']} />
                                        <TextField label="WhatsApp" value={String(form.data.campus.whatsapp ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, whatsapp: e.target.value })} error={errors['campus.whatsapp']} />
                                        <TextField label="Email info" type="email" value={String(form.data.campus.email_info ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, email_info: e.target.value })} error={errors['campus.email_info']} />
                                        <TextField label="Email humas" type="email" value={String(form.data.campus.email_humas ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, email_humas: e.target.value })} error={errors['campus.email_humas']} />
                                    </div>
                                )}

                                {tab === 'logo' && (
                                    <div className="crud-grid">
                                        {([
                                            ['favicon', 'Favicon', system.favicon_url, 'PNG/ICO, maks 2 MB'],
                                            ['logo_vertikal', 'Logo vertikal', system.logo_vertikal_url, 'PNG/JPG, maks 4 MB'],
                                            ['logo_horizontal', 'Logo horizontal', system.logo_horizontal_url, 'PNG/JPG, maks 4 MB'],
                                        ] as const).map(([key, label, current, hint]) => (
                                            <div key={key} style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                                                {current && <img src={current} alt={label} width={56} height={56} style={{ objectFit: 'contain', borderRadius: 10, background: 'var(--db-soft)', flex: '0 0 auto' }} />}
                                                <div style={{ flex: 1 }}>
                                                    <span className="crud-label">{label}</span>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        className="db-input"
                                                        onChange={(e) => form.setData(key, e.target.files?.[0] ?? null)}
                                                    />
                                                    {errors[key] && <div className="db-error">{errors[key]}</div>}
                                                    <div className="crud-hint">{hint}{current ? ' Mengunggah baru menghapus file lama.' : ''}</div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {tab === 'alamat' && (
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField label="Alamat" value={String(form.data.campus.address ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, address: e.target.value })} error={errors['campus.address']} />
                                        </div>
                                        <TextField label="Kota" value={String(form.data.campus.city ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, city: e.target.value })} error={errors['campus.city']} />
                                        <TextField label="Provinsi" value={String(form.data.campus.province ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, province: e.target.value })} error={errors['campus.province']} />
                                        <TextField label="Kode pos" value={String(form.data.campus.postal_code ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, postal_code: e.target.value })} error={errors['campus.postal_code']} />
                                        <TextField label="Latitude" value={String(form.data.campus.latitude ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, latitude: e.target.value })} error={errors['campus.latitude']} />
                                        <TextField label="Longitude" value={String(form.data.campus.longitude ?? '')} onChange={(e) => form.setData('campus', { ...form.data.campus, longitude: e.target.value })} error={errors['campus.longitude']} />
                                    </div>
                                )}

                                {tab === 'sosmed' && (
                                    <div className="crud-grid">
                                        {(['instagram', 'facebook', 'linkedin', 'xtwitter', 'tiktok'] as const).map((key) => (
                                            <TextField
                                                key={key}
                                                label={key === 'xtwitter' ? 'X (Twitter)' : key[0].toUpperCase() + key.slice(1)}
                                                value={String(form.data.campus[key] ?? '')}
                                                onChange={(e) => form.setData('campus', { ...form.data.campus, [key]: e.target.value })}
                                                error={errors[`campus.${key}`]}
                                            />
                                        ))}
                                    </div>
                                )}

                                {tab === 'keamanan' && (
                                    <div className="crud-grid">
                                        <SwitchField label="Mode maintenance" checked={form.data.system.maintenance_mode} onChange={(v) => form.setData('system', { ...form.data.system, maintenance_mode: v })} error={errors['system.maintenance_mode']} />
                                        <SwitchField label="Captcha login" checked={form.data.system.enable_captcha} onChange={(v) => form.setData('system', { ...form.data.system, enable_captcha: v })} error={errors['system.enable_captcha']} />
                                        <TextField label="Maks percobaan login" type="number" value={form.data.system.max_login_attempts} onChange={(e) => form.setData('system', { ...form.data.system, max_login_attempts: e.target.value === '' ? '' : Number(e.target.value) })} error={errors['system.max_login_attempts']} hint="1–10" />
                                        <TextField label="Jeda blokir (detik)" type="number" value={form.data.system.login_decay_seconds} onChange={(e) => form.setData('system', { ...form.data.system, login_decay_seconds: e.target.value === '' ? '' : Number(e.target.value) })} error={errors['system.login_decay_seconds']} hint="30–3600" />
                                    </div>
                                )}

                                {tab === 'notifikasi' && (
                                    <div style={{ display: 'grid', gap: 18 }}>
                                        <div className="crud-grid">
                                            <SwitchField label="WhatsApp aktif" checked={form.data.notification.whatsapp_enabled} onChange={(v) => setNotif({ whatsapp_enabled: v })} error={errors['notification.whatsapp_enabled']} />
                                            <SwitchField label="Web push aktif" checked={form.data.notification.web_push_enabled} onChange={(v) => setNotif({ web_push_enabled: v })} error={errors['notification.web_push_enabled']} />
                                            <SelectField label="Provider WhatsApp" required value={form.data.notification.whatsapp_provider} onChange={(e) => setNotif({ whatsapp_provider: e.target.value })} error={errors['notification.whatsapp_provider']}>
                                                <option value="official_cloud_api">Official Cloud API</option>
                                                <option value="unofficial_web_session">Unofficial Web Session</option>
                                            </SelectField>
                                            <SelectField label="Channel cadangan" required value={form.data.notification.fallback_channel} onChange={(e) => setNotif({ fallback_channel: e.target.value })} error={errors['notification.fallback_channel']}>
                                                <option value="in_app">Dalam aplikasi</option>
                                                <option value="email">Email</option>
                                                <option value="none">Tanpa cadangan</option>
                                            </SelectField>
                                            <TextField label="Maks percobaan ulang" type="number" value={form.data.notification.retry_attempts} onChange={(e) => setNotif({ retry_attempts: e.target.value === '' ? 0 : Number(e.target.value) })} error={errors['notification.retry_attempts']} hint="0–5" />
                                            <TextField label="Timeout (detik)" type="number" value={form.data.notification.timeout_seconds} onChange={(e) => setNotif({ timeout_seconds: e.target.value === '' ? 0 : Number(e.target.value) })} error={errors['notification.timeout_seconds']} hint="5–120" />
                                            <TextField label="Retensi log (hari)" type="number" value={form.data.notification.log_retention_days} onChange={(e) => setNotif({ log_retention_days: e.target.value === '' ? 0 : Number(e.target.value) })} error={errors['notification.log_retention_days']} hint="7–3650" />
                                            <TextField label="Retensi respons (hari)" type="number" value={form.data.notification.provider_response_retention_days} onChange={(e) => setNotif({ provider_response_retention_days: e.target.value === '' ? 0 : Number(e.target.value) })} error={errors['notification.provider_response_retention_days']} />
                                        </div>

                                        {isOfficial ? (
                                            <div className="crud-grid">
                                                <div style={{ gridColumn: '1 / -1' }}><span className="crud-label">Konfigurasi Official Cloud API</span></div>
                                                {(['access_token', 'phone_number_id', 'business_account_id', 'app_secret', 'verify_token'] as const).map((key) => (
                                                    <TextField
                                                        key={key}
                                                        label={key.replace(/_/g, ' ')}
                                                        type={key.includes('secret') || key.includes('token') ? 'password' : 'text'}
                                                        value={form.data.notification.official_config[key] ?? ''}
                                                        onChange={(e) => setOfficial(key, e.target.value)}
                                                        error={errors[`notification.official_config.${key}`]}
                                                    />
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="crud-grid">
                                                <div style={{ gridColumn: '1 / -1' }}><span className="crud-label">Konfigurasi Unofficial Web Session</span></div>
                                                <TextField label="Sidecar URL" value={form.data.notification.unofficial_config.sidecar_url ?? ''} onChange={(e) => setUnofficial('sidecar_url', e.target.value)} error={errors['notification.unofficial_config.sidecar_url']} hint="http(s)://…" />
                                                <TextField label="Nama session" value={form.data.notification.unofficial_config.session_name ?? ''} onChange={(e) => setUnofficial('session_name', e.target.value)} error={errors['notification.unofficial_config.session_name']} hint="Huruf, angka, _ dan -" />
                                                <TextField label="Shared token" type="password" value={form.data.notification.unofficial_config.shared_token ?? ''} onChange={(e) => setUnofficial('shared_token', e.target.value)} error={errors['notification.unofficial_config.shared_token']} />
                                            </div>
                                        )}

                                        <div className={`db-note ${health.status === 'ready' ? 'info' : ''}`} style={health.status === 'ready' ? undefined : health.status === 'disabled' ? undefined : { color: 'var(--db-gold)', background: 'var(--db-gold-soft)' }}>
                                            <span>
                                                <b>Status konfigurasi: {health.status.replace(/_/g, ' ')}.</b> {health.message}
                                                {health.issues && health.issues.length > 0 && (
                                                    <span style={{ display: 'block', marginTop: 4 }}>{health.issues.join(' · ')}</span>
                                                )}
                                            </span>
                                        </div>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                            <button className="db-btn ghost sm" type="button" disabled={busy !== null} onClick={() => run('health', urls.checkHealth)}>
                                                <RefreshCw size={13} /> Cek konfigurasi
                                            </button>
                                            <button className="db-btn ghost sm" type="button" disabled={busy !== null} onClick={() => run('push', urls.testPush)}>
                                                <Send size={13} /> {busy === 'push' ? 'Mengirim…' : 'Kirim test push'}
                                            </button>
                                        </div>

                                        <div className="crud-grid">
                                            <div style={{ gridColumn: '1 / -1' }}><span className="crud-label">Test WhatsApp</span></div>
                                            <TextField label="Nomor tujuan" placeholder="628…" value={recipient} onChange={(e) => setRecipient(e.target.value)} />
                                            <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                                <button className="db-btn ghost sm" type="button" disabled={busy !== null || !recipient} onClick={() => run('wa', urls.testWhatsapp, { recipient })}>
                                                    <Send size={13} /> {busy === 'wa' ? 'Mengirim…' : 'Kirim test'}
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <span className="crud-label">Sidecar WhatsApp bawaan</span>
                                            <div className={`db-note ${sidecar.reachable ? 'info' : ''}`} style={sidecar.reachable ? undefined : { color: 'var(--db-muted)', background: 'var(--db-soft)' }}>
                                                <MonitorSmartphone size={15} />
                                                <span><b>Status sidecar:</b> {sidecar.message}</span>
                                            </div>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 10 }}>
                                                <button className="db-btn ghost sm" type="button" disabled={busy !== null} onClick={() => run('sidecar-refresh', urls.sidecarRefresh)}>
                                                    <RefreshCw size={13} /> Refresh status
                                                </button>
                                                {sidecar.reachable ? (
                                                    <button className="db-btn danger sm" type="button" disabled={busy !== null} onClick={() => run('sidecar-stop', urls.sidecarStop)}>
                                                        <SquareSquare size={13} /> Hentikan sidecar
                                                    </button>
                                                ) : (
                                                    <button className="db-btn primary sm" type="button" disabled={busy !== null} onClick={() => run('sidecar-start', urls.sidecarStart)}>
                                                        <SquarePlay size={13} /> Nyalakan sidecar
                                                    </button>
                                                )}
                                            </div>
                                            {sidecar.sessions.length > 0 && (
                                                <div style={{ marginTop: 10 }}>
                                                    <small className="db-hint">Session tersimpan:</small>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 6 }}>
                                                        {sidecar.sessions.map((session) => (
                                                            <button
                                                                key={session}
                                                                className="db-btn ghost sm"
                                                                type="button"
                                                                disabled={busy !== null}
                                                                title="Pakai sebagai nama session"
                                                                onClick={() => run(`session-${session}`, urls.useSession, { session })}
                                                            >
                                                                <KeyRound size={13} /> {session}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel="Simpan Pengaturan"
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
