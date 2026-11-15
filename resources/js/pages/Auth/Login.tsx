// Halaman login publik — Inertia React (menggantikan Livewire auth.signin-index).
import { Head, Link, useForm } from '@inertiajs/react';
import { CircleCheck, Eye, EyeOff, GraduationCap, LogIn, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Campus, PublicLinks, PublicShell } from '../../components/Home/PublicShell';
import '../../../css/auth-public.css';

type AuthUser = { name: string; role: string; photo: string | null; dashboardUrl: string } | null;
type Props = { campus: Campus; links: PublicLinks; user: AuthUser };

export default function Login({ campus, links, user }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({ login: '', password: '', remember: false as boolean });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/auth/login');
    };

    return (
        <PublicShell campus={campus} links={links} user={user} activeNav="Masuk">
            <Head title={`Masuk · ${campus.name}`} />
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

                            <span className="auth-kicker">Autentikasi</span>
                            <h1>Masuk ke portal Anda.</h1>
                            <p className="auth-sub">
                                Gunakan username atau email terdaftar. Akun dengan lebih dari satu peran akan diminta memilih peran setelah masuk.
                            </p>

                            <form onSubmit={submit} noValidate>
                                <div className="auth-field">
                                    <label htmlFor="auth-login">Username / Email</label>
                                    <input
                                        id="auth-login"
                                        className="auth-input"
                                        value={form.data.login}
                                        onChange={(event) => form.setData('login', event.target.value)}
                                        placeholder="Masukkan username atau email"
                                        autoComplete="username"
                                    />
                                    {form.errors.login && <span className="auth-error">{form.errors.login}</span>}
                                </div>

                                <div className="auth-field">
                                    <label htmlFor="auth-password">Password</label>
                                    <div className="auth-input-wrap">
                                        <input
                                            id="auth-password"
                                            type={showPassword ? 'text' : 'password'}
                                            className="auth-input"
                                            value={form.data.password}
                                            onChange={(event) => form.setData('password', event.target.value)}
                                            placeholder="Masukkan password"
                                            autoComplete="current-password"
                                        />
                                        <button
                                            type="button"
                                            className="auth-eye"
                                            onClick={() => setShowPassword(!showPassword)}
                                            aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                        >
                                            {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                                        </button>
                                    </div>
                                    {form.errors.password && <span className="auth-error">{form.errors.password}</span>}
                                </div>

                                <div className="auth-row">
                                    <label className="auth-check">
                                        <input
                                            type="checkbox"
                                            checked={form.data.remember}
                                            onChange={(event) => form.setData('remember', event.target.checked)}
                                        />
                                        Ingat saya di perangkat ini
                                    </label>
                                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                        <ShieldCheck size={13} /> Akses terenkripsi
                                    </span>
                                </div>

                                <button type="submit" className="auth-btn" disabled={form.processing}>
                                    <LogIn size={15} />
                                    {form.processing ? 'Memeriksa…' : 'Masuk Sekarang'}
                                </button>
                            </form>

                            <p className="auth-meta">
                                Belum punya akun? Ikuti pendaftaran melalui{' '}
                                <Link className="auth-link" href={links.admission}>
                                    penerimaan mahasiswa baru
                                </Link>
                                .
                            </p>
                        </div>

                        <aside className="auth-side" aria-hidden="true">
                            <div className="auth-side-top">
                                <span>Portal resmi {campus.name}</span>
                                <span>Aman · Cepat · Terpadu</span>
                            </div>
                            <p className="auth-quote">Satu akun untuk <em>seluruh perjalanan</em> akademik Anda.</p>
                            <p className="auth-side-sub">Masuk sekali untuk mengakses layanan akademik, pengajaran, dan administrasi dalam satu tempat.</p>
                            <div className="auth-side-list">
                                <div>
                                    <span className="auth-side-tile"><GraduationCap size={15} /></span>
                                    Mahasiswa, dosen, dan tendik dalam satu portal
                                </div>
                                <div>
                                    <span className="auth-side-tile"><ShieldCheck size={15} /></span>
                                    Proteksi rate-limit & pencatatan aktivitas masuk
                                </div>
                                <div>
                                    <span className="auth-side-tile"><CircleCheck size={15} /></span>
                                    Pilih peran aktif sesuai hak akses Anda
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
