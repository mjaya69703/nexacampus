// Halaman pilih peran aktif — Inertia React (menggantikan Livewire auth.select-role).
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Briefcase,
    Check,
    GraduationCap,
    Presentation,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { Campus, PublicLinks, PublicShell } from '../../components/Home/PublicShell';
import '../../../css/auth-public.css';

type AuthUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
type Props = {
    campus: Campus;
    links: PublicLinks;
    user: AuthUser;
    roles: string[];
    roleMeta: Record<string, { desc: string; icon: string }>;
};

const iconFor = (key: string) => {
    switch (key) {
        case 'presentation':
            return Presentation;
        case 'briefcase':
            return Briefcase;
        case 'graduation':
            return GraduationCap;
        case 'users':
            return Users;
        default:
            return ShieldCheck;
    }
};

export default function SelectRole({ campus, links, user, roles, roleMeta }: Props) {
    const form = useForm({ role: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!form.data.role) return;
        form.post('/auth/select-role');
    };

    return (
        <PublicShell campus={campus} links={links} user={user} activeNav="Masuk">
            <Head title={`Pilih Peran · ${campus.name}`} />
            <div className="auth-root">
                <div className="auth-wrap">
                    <div className="auth-grid">
                        <div className="auth-main">
                            <Link href="/" className="auth-brand">
                                <img src={campus.logo} alt={campus.name} />
                                <span>
                                    <strong>{campus.name}</strong>
                                    <small>Sistem Informasi Akademik</small>
                                </span>
                            </Link>

                            <span className="auth-kicker">Halo, {user?.name ?? 'Pengguna'}</span>
                            <h1>Pilih peran Anda.</h1>
                            <p className="auth-sub">
                                Akun Anda memiliki {roles.length} peran. Pilih peran yang ingin digunakan — seluruh menu dan izin akan mengikuti peran aktif ini.
                            </p>

                            <form onSubmit={submit}>
                                <div className="role-list">
                                    {roles.map((role) => {
                                        const key = role.toLowerCase();
                                        const meta = roleMeta[key] ?? { desc: 'Peran tambahan', icon: 'shield' };
                                        const Icon = iconFor(meta.icon);
                                        const selected = form.data.role === key;

                                        return (
                                            <button
                                                key={role}
                                                type="button"
                                                className={`role-card${selected ? ' selected' : ''}`}
                                                onClick={() => form.setData('role', key)}
                                                aria-pressed={selected}
                                            >
                                                <span className="role-icon">
                                                    <Icon size={17} />
                                                </span>
                                                <span>
                                                    <b>{role.replace('-', ' ')}</b>
                                                    <small>{meta.desc}</small>
                                                </span>
                                                {selected && (
                                                    <span className="role-check">
                                                        <Check size={16} />
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>

                                {form.errors.role && <span className="auth-error">{form.errors.role}</span>}

                                <button type="submit" className="auth-btn" disabled={form.processing || !form.data.role}>
                                    {form.processing ? 'Membuka portal…' : 'Pilih Peran & Lanjutkan'}
                                    <ArrowRight size={15} />
                                </button>
                            </form>

                            <p className="auth-meta">
                                Salah akun?{' '}
                                <Link className="auth-link" href="/auth/logout">
                                    Keluar dan masuk ulang
                                </Link>
                                .
                            </p>
                        </div>

                        <aside className="auth-side" aria-hidden="true">
                            <div className="auth-side-top">
                                <span>Akses berbasis peran</span>
                                <span>{roles.length} peran tersedia</span>
                            </div>
                            <p className="auth-quote">Izin, menu, dan dashboard <em>mengikuti peran aktif.</em></p>
                            <p className="auth-side-sub">Beralih peran kapan saja tanpa keluar — seluruh hak akses menyesuaikan otomatis.</p>
                            <div className="auth-side-list">
                                <div>
                                    <span className="auth-side-tile"><ShieldCheck size={15} /></span>
                                    Superuser & admin membuka seluruh modul
                                </div>
                                <div>
                                    <span className="auth-side-tile"><Presentation size={15} /></span>
                                    Dosen & pimpinan fokus ke pengajaran
                                </div>
                                <div>
                                    <span className="auth-side-tile"><GraduationCap size={15} /></span>
                                    Mahasiswa & alumni sesuai kebutuhannya
                                </div>
                            </div>
                            <div className="auth-side-foot">
                                <span className="auth-accred">Terakreditasi <b>Baik Sekali</b></span>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </PublicShell>
    );
}
