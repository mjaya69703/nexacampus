// Wizard instalasi awal NexaCampus — Inertia React (menggantikan setup wizard Livewire).
import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    Check,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    GraduationCap,
    ImagePlus,
    Loader2,
    LockKeyhole,
    Moon,
    Rocket,
    ServerCog,
    Settings2,
    ShieldCheck,
    Sparkles,
    Sun,
    UserRound,
} from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import '../../../css/setup.css';

type Defaults = {
    app_name: string;
    app_version: string;
    app_description: string;
    app_url: string;
    app_email: string;
    maintenance_mode: boolean;
    enable_captcha: boolean;
    max_login_attempts: number | string;
    login_decay_seconds: number | string;
    campus_name: string;
    campus_domain: string;
    campus_phone: string;
    campus_whatsapp: string;
    campus_email_info: string;
    campus_email_humas: string;
    campus_address: string;
    campus_city: string;
    campus_province: string;
    campus_postal_code: string;
    campus_tiktok: string;
    campus_instagram: string;
    campus_facebook: string;
    campus_xtwitter: string;
    campus_linkedin: string;
    admin_first_name: string;
    admin_last_name: string;
    admin_username: string;
    admin_email: string;
    admin_phone: string;
    admin_password: string;
    admin_password_confirmation: string;
    admin_gender: string;
    admin_place_of_birth: string;
    admin_date_of_birth: string;
    admin_religion: string;
};

type WizardData = Defaults & {
    app_favicon: File | null;
    app_logo_vertikal: File | null;
    app_logo_horizontal: File | null;
    confirm_data: boolean;
    accept_terms: boolean;
};

type Props = {
    defaults: Defaults;
    urls: { submit: string };
};

const STEPS = [
    {
        id: 1,
        title: 'Pengaturan System',
        subtitle: 'Konfigurasi dasar aplikasi dan identitas sistem.',
        icon: Settings2,
    },
    {
        id: 2,
        title: 'Pengaturan Kampus',
        subtitle: 'Identitas, kontak, dan lokasi institusi pendidikan.',
        icon: Building2,
    },
    {
        id: 3,
        title: 'Buat Akun',
        subtitle: 'Siapkan akun administrator utama untuk sistem.',
        icon: UserRound,
    },
    {
        id: 4,
        title: 'Konfirmasi',
        subtitle: 'Periksa kembali data sebelum instalasi dijalankan.',
        icon: ClipboardCheck,
    },
] as const;

const STEP_FIELDS: Record<number, (keyof WizardData)[]> = {
    1: [
        'app_name',
        'app_version',
        'app_description',
        'app_url',
        'app_email',
        'maintenance_mode',
        'enable_captcha',
        'max_login_attempts',
        'login_decay_seconds',
        'app_favicon',
        'app_logo_vertikal',
        'app_logo_horizontal',
    ],
    2: [
        'campus_name',
        'campus_domain',
        'campus_phone',
        'campus_whatsapp',
        'campus_email_info',
        'campus_email_humas',
        'campus_address',
        'campus_city',
        'campus_province',
        'campus_postal_code',
        'campus_tiktok',
        'campus_instagram',
        'campus_facebook',
        'campus_xtwitter',
        'campus_linkedin',
    ],
    3: [
        'admin_first_name',
        'admin_last_name',
        'admin_username',
        'admin_email',
        'admin_phone',
        'admin_password',
        'admin_password_confirmation',
        'admin_gender',
        'admin_place_of_birth',
        'admin_date_of_birth',
        'admin_religion',
    ],
    4: ['confirm_data', 'accept_terms'],
};

const FIELD_STEPS: Partial<Record<keyof WizardData, number>> = Object.entries(STEP_FIELDS).reduce(
    (carry, [step, fields]) => {
        fields.forEach((field) => {
            carry[field] = Number(step);
        });

        return carry;
    },
    {} as Partial<Record<keyof WizardData, number>>,
);

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const URL_PATTERN = /^https?:\/\/\S+$/i;

const fullName = (data: WizardData): string =>
    [data.admin_first_name, data.admin_last_name].filter(Boolean).join(' ') || '—';

const fileName = (file: File | null): string => (file ? file.name : 'Belum dipilih');

export default function SetupWizard({ defaults, urls }: Props) {
    const form = useForm<WizardData>({
        ...defaults,
        app_favicon: null,
        app_logo_vertikal: null,
        app_logo_horizontal: null,
        confirm_data: false,
        accept_terms: false,
    });

    const [step, setStep] = useState(1);
    const [dark, setDark] = useState(false);

    useEffect(() => {
        const isDark = window.localStorage.getItem('nexa-theme') === 'dark';
        setDark(isDark);
        document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    }, []);

    useEffect(() => {
        document.querySelector('.setup-main')?.scrollTo({ top: 0, behavior: 'smooth' });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, [step]);

    const toggleTheme = (): void => {
        const next = !dark;
        setDark(next);
        document.documentElement.dataset.theme = next ? 'dark' : 'light';
        window.localStorage.setItem('nexa-theme', next ? 'dark' : 'light');
    };

    const validateStep = (target: number): boolean => {
        const errors: Partial<Record<keyof WizardData, string>> = {};
        const data = form.data;

        if (target === 1) {
            if (!data.app_name.trim()) errors.app_name = 'Nama aplikasi wajib diisi.';
            if (!data.app_version.trim()) errors.app_version = 'Versi aplikasi wajib diisi.';

            if (!data.app_url.trim()) {
                errors.app_url = 'URL aplikasi wajib diisi.';
            } else if (!URL_PATTERN.test(data.app_url.trim())) {
                errors.app_url = 'URL harus diawali http:// atau https://.';
            }

            if (!data.app_email.trim()) {
                errors.app_email = 'Email sistem wajib diisi.';
            } else if (!EMAIL_PATTERN.test(data.app_email.trim())) {
                errors.app_email = 'Format email sistem tidak valid.';
            }

            if (String(data.max_login_attempts).trim() === '' || Number(data.max_login_attempts) < 0) {
                errors.max_login_attempts = 'Batas percobaan login minimal 0.';
            }

            if (String(data.login_decay_seconds).trim() === '' || Number(data.login_decay_seconds) < 0) {
                errors.login_decay_seconds = 'Durasi kunci login minimal 0 detik.';
            }
        }

        if (target === 2) {
            if (!data.campus_name.trim()) errors.campus_name = 'Nama kampus wajib diisi.';
            if (!data.campus_domain.trim()) errors.campus_domain = 'Domain kampus wajib diisi.';

            if (data.campus_email_info.trim() && !EMAIL_PATTERN.test(data.campus_email_info.trim())) {
                errors.campus_email_info = 'Format email info tidak valid.';
            }

            if (data.campus_email_humas.trim() && !EMAIL_PATTERN.test(data.campus_email_humas.trim())) {
                errors.campus_email_humas = 'Format email humas tidak valid.';
            }
        }

        if (target === 3) {
            if (!data.admin_first_name.trim()) errors.admin_first_name = 'Nama depan wajib diisi.';
            if (!data.admin_username.trim()) errors.admin_username = 'Username wajib diisi.';

            if (!data.admin_email.trim()) {
                errors.admin_email = 'Email wajib diisi.';
            } else if (!EMAIL_PATTERN.test(data.admin_email.trim())) {
                errors.admin_email = 'Format email tidak valid.';
            }

            if (!data.admin_phone.trim()) errors.admin_phone = 'Nomor HP wajib diisi.';

            if (!data.admin_password) {
                errors.admin_password = 'Password wajib diisi.';
            } else if (data.admin_password.length < 8) {
                errors.admin_password = 'Password minimal 8 karakter.';
            }

            if (!data.admin_password_confirmation) {
                errors.admin_password_confirmation = 'Konfirmasi password wajib diisi.';
            } else if (data.admin_password !== data.admin_password_confirmation) {
                errors.admin_password_confirmation = 'Konfirmasi password tidak cocok.';
            }

            if (!data.admin_gender) errors.admin_gender = 'Jenis kelamin wajib dipilih.';
        }

        if (target === 4) {
            if (!data.confirm_data) errors.confirm_data = 'Konfirmasi kebenaran data wajib dicentang.';
            if (!data.accept_terms) errors.accept_terms = 'Persetujuan syarat & ketentuan wajib dicentang.';
        }

        form.clearErrors(...STEP_FIELDS[target]);

        Object.entries(errors).forEach(([field, message]) => {
            form.setError(field as keyof WizardData, message as string);
        });

        return Object.keys(errors).length === 0;
    };

    const next = (): void => {
        if (!validateStep(step)) return;
        setStep((current) => Math.min(4, current + 1));
    };

    const prev = (): void => {
        setStep((current) => Math.max(1, current - 1));
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (!validateStep(4)) return;

        form.post(urls.submit, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                const keys = Object.keys(errors) as (keyof WizardData)[];
                const firstStep = keys.reduce((lowest, key) => Math.min(lowest, FIELD_STEPS[key] ?? 4), 4);
                setStep(firstStep);
            },
        });
    };

    const activeStep = STEPS[step - 1];

    return (
        <div className="setup-root">
            <Head title={`Instalasi ${defaults.app_name || 'NexaCampus'}`} />

            <div className="setup-shell">
                <aside className="setup-side">
                    <div>
                        <div className="setup-brand">
                            <span className="setup-brand-mark">
                                <GraduationCap size={22} />
                            </span>
                            <span>
                                <strong>NexaCampus</strong>
                                <small>Instalasi sistem</small>
                            </span>
                        </div>

                        <ol className="setup-steps" aria-label="Langkah instalasi">
                            {STEPS.map((item) => {
                                const Icon = item.icon;
                                const isActive = step === item.id;
                                const isDone = step > item.id;

                                return (
                                    <li
                                        key={item.id}
                                        className={`setup-step${isActive ? ' is-active' : ''}${isDone ? ' is-done' : ''}`}
                                        aria-current={isActive ? 'step' : undefined}
                                    >
                                        <span className="setup-step-dot">
                                            {isDone ? <Check size={13} /> : <Icon size={14} />}
                                        </span>
                                        <span className="setup-step-copy">
                                            <b>{item.title}</b>
                                            <small>
                                                Langkah {item.id} dari {STEPS.length}
                                            </small>
                                        </span>
                                    </li>
                                );
                            })}
                        </ol>
                    </div>

                    <div className="setup-side-foot">
                        <ShieldCheck size={14} />
                        <span>Data instalasi hanya disimpan di server Anda.</span>
                    </div>
                </aside>

                <main className="setup-main">
                    <header className="setup-main-head">
                        <div>
                            <span className="setup-kicker">
                                <Sparkles size={13} /> Langkah {step} dari {STEPS.length}
                            </span>
                            <h1>{activeStep.title}</h1>
                            <p>{activeStep.subtitle}</p>
                        </div>
                        <button
                            type="button"
                            className="setup-theme"
                            onClick={toggleTheme}
                            aria-label={dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'}
                        >
                            {dark ? <Sun size={17} /> : <Moon size={17} />}
                        </button>
                    </header>

                    <div className="setup-progress" aria-hidden="true">
                        <span style={{ width: `${(step / STEPS.length) * 100}%` }} />
                    </div>

                    <form onSubmit={submit} noValidate>
                        <div className="setup-body">
                            {step === 1 && (
                                <>
                                    <div className="setup-grid-2">
                                        <TextField
                                            id="app_name"
                                            label="Nama aplikasi"
                                            required
                                            value={form.data.app_name}
                                            onChange={(value) => form.setData('app_name', value)}
                                            error={form.errors.app_name}
                                            placeholder="Contoh: NexaCampus"
                                        />
                                        <TextField
                                            id="app_version"
                                            label="Versi aplikasi"
                                            required
                                            value={form.data.app_version}
                                            onChange={(value) => form.setData('app_version', value)}
                                            error={form.errors.app_version}
                                            placeholder="1.0.0"
                                        />
                                    </div>

                                    <TextareaField
                                        id="app_description"
                                        label="Deskripsi aplikasi"
                                        value={form.data.app_description}
                                        onChange={(value) => form.setData('app_description', value)}
                                        error={form.errors.app_description}
                                        placeholder="Deskripsi singkat aplikasi..."
                                        rows={2}
                                    />

                                    <div className="setup-grid-2">
                                        <TextField
                                            id="app_url"
                                            label="URL aplikasi"
                                            required
                                            type="url"
                                            value={form.data.app_url}
                                            onChange={(value) => form.setData('app_url', value)}
                                            error={form.errors.app_url}
                                            placeholder="https://kampus.ac.id"
                                        />
                                        <TextField
                                            id="app_email"
                                            label="Email sistem"
                                            required
                                            type="email"
                                            value={form.data.app_email}
                                            onChange={(value) => form.setData('app_email', value)}
                                            error={form.errors.app_email}
                                            placeholder="admin@kampus.ac.id"
                                        />
                                    </div>

                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <ImagePlus size={15} />
                                            <span>Aset logo</span>
                                        </div>
                                        <div className="setup-grid-3">
                                            <FileField
                                                id="app_favicon"
                                                label="Favicon"
                                                accept=".png,.ico,image/png,image/x-icon"
                                                file={form.data.app_favicon}
                                                onChange={(file) => form.setData('app_favicon', file)}
                                                error={form.errors.app_favicon}
                                                hint="PNG/ICO, maks. 2 MB"
                                            />
                                            <FileField
                                                id="app_logo_vertikal"
                                                label="Logo vertikal"
                                                accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                                                file={form.data.app_logo_vertikal}
                                                onChange={(file) => form.setData('app_logo_vertikal', file)}
                                                error={form.errors.app_logo_vertikal}
                                                hint="PNG/JPG, maks. 4 MB"
                                            />
                                            <FileField
                                                id="app_logo_horizontal"
                                                label="Logo horizontal"
                                                accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                                                file={form.data.app_logo_horizontal}
                                                onChange={(file) => form.setData('app_logo_horizontal', file)}
                                                error={form.errors.app_logo_horizontal}
                                                hint="PNG/JPG, maks. 4 MB"
                                            />
                                        </div>
                                    </div>

                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <LockKeyhole size={15} />
                                            <span>Keamanan & sistem</span>
                                        </div>
                                        <div className="setup-grid-2">
                                            <ToggleField
                                                id="maintenance_mode"
                                                label="Maintenance mode"
                                                description="Tutup akses publik sementara saat instalasi."
                                                checked={form.data.maintenance_mode}
                                                onChange={(checked) => form.setData('maintenance_mode', checked)}
                                            />
                                            <ToggleField
                                                id="enable_captcha"
                                                label="Captcha login"
                                                description="Minta verifikasi captcha saat login."
                                                checked={form.data.enable_captcha}
                                                onChange={(checked) => form.setData('enable_captcha', checked)}
                                            />
                                            <TextField
                                                id="max_login_attempts"
                                                label="Batas percobaan login"
                                                type="number"
                                                min={0}
                                                max={100}
                                                value={String(form.data.max_login_attempts)}
                                                onChange={(value) => form.setData('max_login_attempts', value)}
                                                error={form.errors.max_login_attempts}
                                            />
                                            <TextField
                                                id="login_decay_seconds"
                                                label="Durasi kunci login (detik)"
                                                type="number"
                                                min={0}
                                                max={86400}
                                                value={String(form.data.login_decay_seconds)}
                                                onChange={(value) => form.setData('login_decay_seconds', value)}
                                                error={form.errors.login_decay_seconds}
                                            />
                                        </div>
                                    </div>
                                </>
                            )}

                            {step === 2 && (
                                <>
                                    <div className="setup-grid-2">
                                        <TextField
                                            id="campus_name"
                                            label="Nama kampus"
                                            required
                                            value={form.data.campus_name}
                                            onChange={(value) => form.setData('campus_name', value)}
                                            error={form.errors.campus_name}
                                            placeholder="Contoh: Nusantara Academy"
                                        />
                                        <TextField
                                            id="campus_domain"
                                            label="Domain"
                                            required
                                            value={form.data.campus_domain}
                                            onChange={(value) => form.setData('campus_domain', value)}
                                            error={form.errors.campus_domain}
                                            placeholder="kampus.ac.id"
                                        />
                                    </div>

                                    <div className="setup-grid-2">
                                        <TextField
                                            id="campus_phone"
                                            label="Telepon"
                                            value={form.data.campus_phone}
                                            onChange={(value) => form.setData('campus_phone', value)}
                                            error={form.errors.campus_phone}
                                            placeholder="021-1234567"
                                        />
                                        <TextField
                                            id="campus_whatsapp"
                                            label="WhatsApp"
                                            value={form.data.campus_whatsapp}
                                            onChange={(value) => form.setData('campus_whatsapp', value)}
                                            error={form.errors.campus_whatsapp}
                                            placeholder="0812xxxxxxx"
                                        />
                                    </div>

                                    <div className="setup-grid-2">
                                        <TextField
                                            id="campus_email_info"
                                            label="Email info"
                                            type="email"
                                            value={form.data.campus_email_info}
                                            onChange={(value) => form.setData('campus_email_info', value)}
                                            error={form.errors.campus_email_info}
                                            placeholder="info@kampus.ac.id"
                                        />
                                        <TextField
                                            id="campus_email_humas"
                                            label="Email humas"
                                            type="email"
                                            value={form.data.campus_email_humas}
                                            onChange={(value) => form.setData('campus_email_humas', value)}
                                            error={form.errors.campus_email_humas}
                                            placeholder="humas@kampus.ac.id"
                                        />
                                    </div>

                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <Building2 size={15} />
                                            <span>Alamat & lokasi</span>
                                        </div>
                                        <TextareaField
                                            id="campus_address"
                                            label="Alamat lengkap"
                                            value={form.data.campus_address}
                                            onChange={(value) => form.setData('campus_address', value)}
                                            error={form.errors.campus_address}
                                            placeholder="Jalan, nomor, kelurahan, kecamatan..."
                                            rows={2}
                                        />
                                        <div className="setup-grid-3">
                                            <TextField
                                                id="campus_city"
                                                label="Kota"
                                                value={form.data.campus_city}
                                                onChange={(value) => form.setData('campus_city', value)}
                                                error={form.errors.campus_city}
                                            />
                                            <TextField
                                                id="campus_province"
                                                label="Provinsi"
                                                value={form.data.campus_province}
                                                onChange={(value) => form.setData('campus_province', value)}
                                                error={form.errors.campus_province}
                                            />
                                            <TextField
                                                id="campus_postal_code"
                                                label="Kode pos"
                                                value={form.data.campus_postal_code}
                                                onChange={(value) => form.setData('campus_postal_code', value)}
                                                error={form.errors.campus_postal_code}
                                            />
                                        </div>
                                    </div>

                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <Sparkles size={15} />
                                            <span>Media sosial</span>
                                        </div>
                                        <div className="setup-grid-3">
                                            <TextField
                                                id="campus_instagram"
                                                label="Instagram"
                                                value={form.data.campus_instagram}
                                                onChange={(value) => form.setData('campus_instagram', value)}
                                                error={form.errors.campus_instagram}
                                                placeholder="@kampus"
                                            />
                                            <TextField
                                                id="campus_facebook"
                                                label="Facebook"
                                                value={form.data.campus_facebook}
                                                onChange={(value) => form.setData('campus_facebook', value)}
                                                error={form.errors.campus_facebook}
                                            />
                                            <TextField
                                                id="campus_tiktok"
                                                label="TikTok"
                                                value={form.data.campus_tiktok}
                                                onChange={(value) => form.setData('campus_tiktok', value)}
                                                error={form.errors.campus_tiktok}
                                            />
                                            <TextField
                                                id="campus_xtwitter"
                                                label="X / Twitter"
                                                value={form.data.campus_xtwitter}
                                                onChange={(value) => form.setData('campus_xtwitter', value)}
                                                error={form.errors.campus_xtwitter}
                                            />
                                            <TextField
                                                id="campus_linkedin"
                                                label="LinkedIn"
                                                value={form.data.campus_linkedin}
                                                onChange={(value) => form.setData('campus_linkedin', value)}
                                                error={form.errors.campus_linkedin}
                                            />
                                        </div>
                                    </div>
                                </>
                            )}

                            {step === 3 && (
                                <>
                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <UserRound size={15} />
                                            <span>Identitas akun</span>
                                        </div>
                                        <div className="setup-grid-2">
                                            <TextField
                                                id="admin_first_name"
                                                label="Nama depan"
                                                required
                                                value={form.data.admin_first_name}
                                                onChange={(value) => form.setData('admin_first_name', value)}
                                                error={form.errors.admin_first_name}
                                            />
                                            <TextField
                                                id="admin_last_name"
                                                label="Nama belakang"
                                                value={form.data.admin_last_name}
                                                onChange={(value) => form.setData('admin_last_name', value)}
                                                error={form.errors.admin_last_name}
                                            />
                                            <TextField
                                                id="admin_username"
                                                label="Username"
                                                required
                                                value={form.data.admin_username}
                                                onChange={(value) => form.setData('admin_username', value)}
                                                error={form.errors.admin_username}
                                                autoComplete="username"
                                            />
                                            <TextField
                                                id="admin_email"
                                                label="Email"
                                                required
                                                type="email"
                                                value={form.data.admin_email}
                                                onChange={(value) => form.setData('admin_email', value)}
                                                error={form.errors.admin_email}
                                                autoComplete="email"
                                            />
                                            <TextField
                                                id="admin_password"
                                                label="Password"
                                                required
                                                type="password"
                                                value={form.data.admin_password}
                                                onChange={(value) => form.setData('admin_password', value)}
                                                error={form.errors.admin_password}
                                                hint="Minimal 8 karakter."
                                                autoComplete="new-password"
                                            />
                                            <TextField
                                                id="admin_password_confirmation"
                                                label="Konfirmasi password"
                                                required
                                                type="password"
                                                value={form.data.admin_password_confirmation}
                                                onChange={(value) => form.setData('admin_password_confirmation', value)}
                                                error={form.errors.admin_password_confirmation}
                                                autoComplete="new-password"
                                            />
                                        </div>
                                    </div>

                                    <div className="setup-section">
                                        <div className="setup-section-head">
                                            <ServerCog size={15} />
                                            <span>Data pribadi</span>
                                        </div>
                                        <div className="setup-grid-2">
                                            <SelectField
                                                id="admin_gender"
                                                label="Jenis kelamin"
                                                required
                                                value={form.data.admin_gender}
                                                onChange={(value) => form.setData('admin_gender', value)}
                                                error={form.errors.admin_gender}
                                                options={['Laki-laki', 'Perempuan']}
                                            />
                                            <SelectField
                                                id="admin_religion"
                                                label="Agama"
                                                value={form.data.admin_religion}
                                                onChange={(value) => form.setData('admin_religion', value)}
                                                error={form.errors.admin_religion}
                                                options={['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu']}
                                            />
                                            <TextField
                                                id="admin_place_of_birth"
                                                label="Tempat lahir"
                                                value={form.data.admin_place_of_birth}
                                                onChange={(value) => form.setData('admin_place_of_birth', value)}
                                                error={form.errors.admin_place_of_birth}
                                            />
                                            <TextField
                                                id="admin_date_of_birth"
                                                label="Tanggal lahir"
                                                type="date"
                                                value={form.data.admin_date_of_birth}
                                                onChange={(value) => form.setData('admin_date_of_birth', value)}
                                                error={form.errors.admin_date_of_birth}
                                            />
                                            <TextField
                                                id="admin_phone"
                                                label="No. HP / WhatsApp"
                                                required
                                                value={form.data.admin_phone}
                                                onChange={(value) => form.setData('admin_phone', value)}
                                                error={form.errors.admin_phone}
                                            />
                                        </div>
                                    </div>

                                    <div className="setup-note">
                                        <ShieldCheck size={16} />
                                        <span>
                                            Akun ini akan mendapat role <b>superuser</b> dengan akses penuh ke seluruh modul.
                                        </span>
                                    </div>
                                </>
                            )}

                            {step === 4 && (
                                <>
                                    <div className="setup-summary">
                                        <section>
                                            <h3>1. Pengaturan System</h3>
                                            <dl>
                                                <dt>Nama aplikasi</dt>
                                                <dd>{form.data.app_name || '—'}</dd>
                                                <dt>Versi</dt>
                                                <dd>{form.data.app_version || '—'}</dd>
                                                <dt>URL</dt>
                                                <dd>{form.data.app_url || '—'}</dd>
                                                <dt>Email sistem</dt>
                                                <dd>{form.data.app_email || '—'}</dd>
                                                <dt>Maintenance</dt>
                                                <dd>{form.data.maintenance_mode ? 'Aktif' : 'Tidak aktif'}</dd>
                                                <dt>Captcha login</dt>
                                                <dd>{form.data.enable_captcha ? 'Aktif' : 'Tidak aktif'}</dd>
                                                <dt>Aset logo</dt>
                                                <dd>
                                                    {[
                                                        form.data.app_favicon ? 'Favicon' : null,
                                                        form.data.app_logo_vertikal ? 'Logo vertikal' : null,
                                                        form.data.app_logo_horizontal ? 'Logo horizontal' : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(', ') || 'Menggunakan aset default'}
                                                </dd>
                                            </dl>
                                        </section>

                                        <section>
                                            <h3>2. Pengaturan Kampus</h3>
                                            <dl>
                                                <dt>Nama kampus</dt>
                                                <dd>{form.data.campus_name || '—'}</dd>
                                                <dt>Domain</dt>
                                                <dd>{form.data.campus_domain || '—'}</dd>
                                                <dt>Telepon</dt>
                                                <dd>{form.data.campus_phone || '—'}</dd>
                                                <dt>Email info</dt>
                                                <dd>{form.data.campus_email_info || '—'}</dd>
                                                <dt>Alamat</dt>
                                                <dd>{form.data.campus_address || '—'}</dd>
                                            </dl>
                                        </section>

                                        <section>
                                            <h3>3. Akun Super Admin</h3>
                                            <dl>
                                                <dt>Nama lengkap</dt>
                                                <dd>{fullName(form.data)}</dd>
                                                <dt>Username</dt>
                                                <dd>{form.data.admin_username || '—'}</dd>
                                                <dt>Email</dt>
                                                <dd>{form.data.admin_email || '—'}</dd>
                                                <dt>No. HP</dt>
                                                <dd>{form.data.admin_phone || '—'}</dd>
                                            </dl>
                                        </section>
                                    </div>

                                    <div className="setup-confirm">
                                        <label className={`setup-check${form.errors.confirm_data ? ' has-error' : ''}`}>
                                            <input
                                                type="checkbox"
                                                checked={form.data.confirm_data}
                                                onChange={(event) => {
                                                    form.setData('confirm_data', event.target.checked);
                                                    form.clearErrors('confirm_data');
                                                }}
                                            />
                                            <span>
                                                Saya telah memeriksa dan memastikan seluruh data instalasi sudah benar.
                                                {form.errors.confirm_data && (
                                                    <small className="setup-error" role="alert">
                                                        {form.errors.confirm_data}
                                                    </small>
                                                )}
                                            </span>
                                        </label>
                                        <label className={`setup-check${form.errors.accept_terms ? ' has-error' : ''}`}>
                                            <input
                                                type="checkbox"
                                                checked={form.data.accept_terms}
                                                onChange={(event) => {
                                                    form.setData('accept_terms', event.target.checked);
                                                    form.clearErrors('accept_terms');
                                                }}
                                            />
                                            <span>
                                                Saya menyetujui syarat & ketentuan serta kebijakan penggunaan NexaCampus.
                                                {form.errors.accept_terms && (
                                                    <small className="setup-error" role="alert">
                                                        {form.errors.accept_terms}
                                                    </small>
                                                )}
                                            </span>
                                        </label>
                                    </div>
                                </>
                            )}
                        </div>

                        <footer className="setup-actions">
                            <button type="button" className="setup-btn ghost" onClick={prev} disabled={step === 1}>
                                <ChevronLeft size={15} /> Kembali
                            </button>

                            {step < 4 ? (
                                <button type="button" className="setup-btn primary" onClick={next}>
                                    Lanjut <ChevronRight size={15} />
                                </button>
                            ) : (
                                <button type="submit" className="setup-btn primary" disabled={form.processing}>
                                    {form.processing ? <Loader2 size={15} className="setup-spin" /> : <Rocket size={15} />}
                                    {form.processing ? 'Memproses instalasi...' : 'Selesaikan Instalasi'}
                                </button>
                            )}
                        </footer>
                    </form>
                </main>
            </div>
        </div>
    );
}

function TextField({
    id,
    label,
    value,
    onChange,
    error,
    type = 'text',
    placeholder,
    hint,
    required = false,
    autoComplete,
    min,
    max,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: string;
    placeholder?: string;
    hint?: string;
    required?: boolean;
    autoComplete?: string;
    min?: number;
    max?: number;
}) {
    return (
        <div className="setup-field">
            <label htmlFor={id}>
                {label}
                {required && <span className="setup-required"> *</span>}
            </label>
            <input
                id={id}
                type={type}
                className={`setup-input${error ? ' has-error' : ''}`}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                autoComplete={autoComplete}
                min={min}
                max={max}
                aria-invalid={error ? true : undefined}
            />
            {hint && !error && <small className="setup-hint">{hint}</small>}
            {error && (
                <span className="setup-error" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}

function TextareaField({
    id,
    label,
    value,
    onChange,
    error,
    placeholder,
    rows = 3,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    rows?: number;
}) {
    return (
        <div className="setup-field">
            <label htmlFor={id}>{label}</label>
            <textarea
                id={id}
                className={`setup-input setup-textarea${error ? ' has-error' : ''}`}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                rows={rows}
                aria-invalid={error ? true : undefined}
            />
            {error && (
                <span className="setup-error" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}

function SelectField({
    id,
    label,
    value,
    onChange,
    error,
    options,
    required = false,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    options: string[];
    required?: boolean;
}) {
    return (
        <div className="setup-field">
            <label htmlFor={id}>
                {label}
                {required && <span className="setup-required"> *</span>}
            </label>
            <select
                id={id}
                className={`setup-input setup-select${error ? ' has-error' : ''}`}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={error ? true : undefined}
            >
                <option value="">Pilih...</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
            </select>
            {error && (
                <span className="setup-error" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}

function ToggleField({
    id,
    label,
    description,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className="setup-toggle" htmlFor={id}>
            <span>
                <b>{label}</b>
                <small>{description}</small>
            </span>
            <span className="setup-switch">
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                />
                <span className="setup-switch-track" aria-hidden="true" />
            </span>
        </label>
    );
}

function FileField({
    id,
    label,
    accept,
    file,
    onChange,
    error,
    hint,
}: {
    id: string;
    label: string;
    accept: string;
    file: File | null;
    onChange: (file: File | null) => void;
    error?: string;
    hint: string;
}) {
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    return (
        <div className="setup-field">
            <label htmlFor={id}>{label}</label>
            <div className={`setup-preview${preview ? ' has-image' : ''}`}>
                {preview ? (
                    <img src={preview} alt={`Pratinjau ${label}`} />
                ) : (
                    <span className="setup-preview-empty">
                        <ImagePlus size={18} />
                        <small>Pratinjau</small>
                    </span>
                )}
            </div>
            <label className="setup-file-btn" htmlFor={id}>
                {file ? 'Ganti file' : 'Pilih file'}
            </label>
            <input
                id={id}
                type="file"
                className="setup-file-input"
                accept={accept}
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
            <small className="setup-hint">{file ? fileName(file) : hint}</small>
            {error && (
                <span className="setup-error" role="alert">
                    {error}
                </span>
            )}
        </div>
    );
}
