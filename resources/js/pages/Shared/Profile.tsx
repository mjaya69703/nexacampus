// Profil pengguna — dashboard React Inertia di dalam layout admin (AdminShell).
import { Head, useForm } from '@inertiajs/react';
import {
    Award,
    BadgeCheck,
    Building2,
    CalendarDays,
    Camera,
    CircleCheck,
    Clock3,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileText,
    Fingerprint,
    GraduationCap,
    IdCard,
    Info,
    KeyRound,
    LayoutDashboard,
    Mail,
    MapPin,
    Phone,
    Plus,
    ShieldCheck,
    Upload,
    UserRound,
    UsersRound,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../components/Shared/AdminShell';
import { EmptyState } from '../../components/Shared/EmptyState';
import '../../../css/profile.css';

type Profile = {
    firstName: string; lastName: string; name: string; username: string; email: string; phone: string;
    instagram: string | null; facebook: string | null; linkedin: string | null; identityNumber: string | null;
    religion: string | null; bloodType: string | null; citizenship: string | null; gender: string | null;
    height: number | null; weight: number | null; placeOfBirth: string | null; dateOfBirth: string | null;
    dateOfBirthLabel: string | null; photoUrl: string; roles: string[]; activeRole: string | null;
    activeRoleLabel: string; memberSince: string | null; lastLogin: string | null;
};

type StudentInfo = {
    nim: string | null; programName: string | null; facultyName: string | null; entryYear: number | null;
    entryAcademicYear: string | null; currentSemester: number | null; academicStatus: string | null;
    classType: string | null; entryDate: string | null; entryDateLabel: string | null;
    graduationDate: string | null; isActive: boolean;
} | null;

type LecturerInfo = {
    nidn: string | null; nidk: string | null; nip: string | null; facultyName: string | null;
    programName: string | null; employmentStatus: string | null; joinDate: string | null;
    joinDateLabel: string | null; isActive: boolean;
} | null;

type Development = {
    id: number; type: string; typeLabel: string; title: string; organizer: string | null;
    credentialNumber: string | null; startDate: string | null; startDateLabel: string | null;
    endDateLabel: string | null; expiresLabel: string; isVerified: boolean;
    attachments: Array<{ id: number; fileName: string; previewUrl: string }>;
};

type Props = {
    shell: ShellProps;
    profile: Profile; student: StudentInfo; lecturer: LecturerInfo;
    developments: Development[];
    completeness: { percent: number; missing: string[]; filled: number; total: number };
    stats: { certifications: number; verified: number; pending: number };
    options: { religions: string[]; bloodTypes: string[]; citizenships: string[]; genders: string[] };
};

type TabKey = 'ringkasan' | 'biodata' | 'keamanan' | 'akademik' | 'sertifikasi';

const initialsFor = (name: string) =>
    name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="pf-error">{message}</span>;
}

export default function Profile({ shell, profile, student, lecturer, developments, completeness, stats, options }: Props) {
    const hasAcademic = Boolean(student || lecturer);
    const [tab, setTab] = useState<TabKey>('ringkasan');
    const [certOpen, setCertOpen] = useState(false);
    const [photoFailed, setPhotoFailed] = useState(false);
    const [draggingTabs, setDraggingTabs] = useState(false);
    const [canTabLeft, setCanTabLeft] = useState(false);
    const [canTabRight, setCanTabRight] = useState(false);
    const tabsRef = useRef<HTMLDivElement>(null);
    const tabDrag = useRef<{ x: number; sl: number } | null>(null);
    const tabDragMoved = useRef(false);

    const biodata = useForm({
        first_name: profile.firstName ?? '',
        last_name: profile.lastName ?? '',
        username: profile.username ?? '',
        phone: profile.phone ?? '',
        email: profile.email ?? '',
        instagram: profile.instagram ?? '',
        facebook: profile.facebook ?? '',
        linkedin: profile.linkedin ?? '',
        identity_number: profile.identityNumber ?? '',
        religion: profile.religion ?? '',
        blood_type: profile.bloodType ?? '',
        citizenship: profile.citizenship ?? '',
        gender: profile.gender ?? '',
        height: profile.height ? String(profile.height) : '',
        weight: profile.weight ? String(profile.weight) : '',
        place_of_birth: profile.placeOfBirth ?? '',
        date_of_birth: profile.dateOfBirth ?? '',
        photo: null as File | null,
    });

    const password = useForm({ current_password: '', new_password: '', new_password_confirmation: '' });

    const cert = useForm({
        type: 'certification',
        title: '',
        organizer: '',
        credential_number: '',
        start_date: '',
        end_date: '',
        expires_at: '',
        description: '',
        document: null as File | null,
    });

    const photoPreview = useMemo(
        () => (biodata.data.photo ? URL.createObjectURL(biodata.data.photo) : profile.photoUrl),
        [biodata.data.photo, profile.photoUrl],
    );

    useEffect(() => {
        tabsRef.current?.querySelector('.pf-tab.active')?.scrollIntoView({ inline: 'nearest', block: 'nearest' });
        updateTabArrows();
    }, [tab]);

    useEffect(() => {
        updateTabArrows();
        window.addEventListener('resize', updateTabArrows);
        return () => window.removeEventListener('resize', updateTabArrows);
    }, []);

    const updateTabArrows = () => {
        const el = tabsRef.current;
        if (!el) return;
        setCanTabLeft(el.scrollLeft > 4);
        setCanTabRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
    };

    const scrollTabsBy = (dx: number) => {
        tabsRef.current?.scrollBy({ left: dx, behavior: 'smooth' });
    };

    const onTabsPointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
        if (event.pointerType !== 'mouse' || event.button !== 0) return;
        const el = tabsRef.current;
        if (!el) return;
        tabDrag.current = { x: event.clientX, sl: el.scrollLeft };
        tabDragMoved.current = false;
    };

    const onTabsPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
        const drag = tabDrag.current;
        const el = tabsRef.current;
        if (!drag || !el) return;
        const dx = event.clientX - drag.x;
        if (!tabDragMoved.current && Math.abs(dx) > 6) {
            tabDragMoved.current = true;
            setDraggingTabs(true);
        }
        if (tabDragMoved.current) el.scrollLeft = drag.sl - dx;
    };

    const endTabsDrag = () => {
        tabDrag.current = null;
        setDraggingTabs(false);
    };

    const onTabsClickCapture = (event: React.MouseEvent<HTMLDivElement>) => {
        if (tabDragMoved.current) {
            event.stopPropagation();
            event.preventDefault();
            tabDragMoved.current = false;
        }
    };

    const submitBiodata = (event: React.FormEvent) => {
        event.preventDefault();
        biodata.post('/profile', { forceFormData: true, preserveScroll: true });
    };

    const submitPassword = (event: React.FormEvent) => {
        event.preventDefault();
        password.post('/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });
    };

    const submitCert = (event: React.FormEvent) => {
        event.preventDefault();
        cert.post('/profile/certificates', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { cert.reset(); setCertOpen(false); },
        });
    };

    const tabs: Array<{ key: TabKey; label: string; icon: typeof UserRound; count?: number; hidden?: boolean }> = [
        { key: 'ringkasan', label: 'Ringkasan', icon: LayoutDashboard },
        { key: 'biodata', label: 'Biodata', icon: UserRound },
        { key: 'keamanan', label: 'Keamanan', icon: ShieldCheck },
        { key: 'akademik', label: 'Akademik', icon: GraduationCap, hidden: !hasAcademic },
        { key: 'sertifikasi', label: 'Sertifikasi', icon: Award, count: stats.certifications },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Profil Saya · ${shell.appName}`} />
            <div className="pf-root">
                <div className="pf-content">
                    {/* ── Head card: cover + identitas + tab menyatu ── */}
                    <section className="pf-head">
                        <div className="pf-cover" aria-hidden="true" />
                        <div className="pf-head-body">
                            <div className="pf-identity">
                                <div className="pf-avatar">
                                    {photoPreview && !photoFailed
                                        ? <img src={photoPreview} alt={profile.name} onError={() => setPhotoFailed(true)} />
                                        : <span className="pf-avatar-fallback">{initialsFor(profile.name) || '?'}</span>}
                                    <span className="pf-presence" aria-hidden="true" />
                                </div>
                                <div className="pf-head-copy">
                                    <h1>{profile.name}</h1>
                                    <p>{profile.activeRoleLabel} · Terdaftar sejak {profile.memberSince ?? '-'} · Login terakhir {profile.lastLogin ?? '-'}</p>
                                    <div className="pf-badges">
                                        <span className="pf-badge gray"><BadgeCheck size={12} /> {profile.activeRoleLabel}</span>
                                        {student?.nim && <span className="pf-badge gray"><IdCard size={12} /> {student.nim}</span>}
                                        {lecturer?.nidn && <span className="pf-badge gray"><Fingerprint size={12} /> NIDN {lecturer.nidn}</span>}
                                    </div>
                                </div>
                            </div>
                            <aside className="pf-head-side" aria-label="Kelengkapan profil">
                                <div className="pf-complete-top">
                                    <small>Kelengkapan profil</small>
                                    <strong>{completeness.percent}%</strong>
                                </div>
                                <div className="pf-progress" role="progressbar" aria-valuenow={completeness.percent} aria-valuemin={0} aria-valuemax={100}>
                                    <i style={{ width: `${completeness.percent}%` }} />
                                </div>
                                <p>
                                    {completeness.percent === 100
                                        ? 'Luar biasa — seluruh data utama sudah terisi.'
                                        : `Masih kurang ${completeness.missing.length} bagian: ${completeness.missing.slice(0, 2).join(', ')}${completeness.missing.length > 2 ? '…' : '.'}`}
                                </p>
                                {completeness.percent < 100 && (
                                    <button type="button" className="pf-btn primary sm" onClick={() => setTab('biodata')}>
                                        Lengkapi biodata
                                    </button>
                                )}
                            </aside>
                        </div>
                        <div className="pf-tabs-wrap">
                            {canTabLeft && (
                                <button type="button" className="pf-tab-arrow left" aria-label="Geser tab ke kiri" onClick={() => scrollTabsBy(-220)}>
                                    <ChevronLeft size={16} />
                                </button>
                            )}
                            <div
                                className={`pf-tabs${draggingTabs ? ' dragging' : ''}`}
                                role="tablist"
                                aria-label="Navigasi profil"
                                ref={tabsRef}
                                onScroll={updateTabArrows}
                                onPointerDown={onTabsPointerDown}
                                onPointerMove={onTabsPointerMove}
                                onPointerUp={endTabsDrag}
                                onPointerLeave={endTabsDrag}
                                onPointerCancel={endTabsDrag}
                                onClickCapture={onTabsClickCapture}
                            >
                            {tabs.filter((item) => !item.hidden).map(({ key, label, icon: Icon, count }) => (
                                <button
                                    key={key}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === key}
                                    className={`pf-tab${tab === key ? ' active' : ''}`}
                                    onClick={() => setTab(key)}
                                >
                                    <Icon size={15} /> {label}
                                    {typeof count === 'number' && <span className="pf-tab-count">{count}</span>}
                                </button>
                            ))}
                            </div>
                            {canTabRight && (
                                <button type="button" className="pf-tab-arrow right" aria-label="Geser tab ke kanan" onClick={() => scrollTabsBy(220)}>
                                    <ChevronRight size={16} />
                                </button>
                            )}
                        </div>
                    </section>

                    <div className="pf-panels">
                        {tab === 'ringkasan' && (
                            <>
                                <section className="pf-stats" aria-label="Statistik profil">
                                    <div className="pf-stat">
                                        <span className="pf-stat-icon"><UsersRound size={20} /></span>
                                        <div><span className="pf-stat-num">{profile.roles.length}</span><span className="pf-stat-label">Peran Dimiliki</span></div>
                                    </div>
                                    <div className="pf-stat">
                                        <span className="pf-stat-icon gold"><Award size={20} /></span>
                                        <div><span className="pf-stat-num">{stats.certifications}</span><span className="pf-stat-label">Sertifikasi Diajukan</span></div>
                                    </div>
                                    <div className="pf-stat">
                                        <span className="pf-stat-icon green"><BadgeCheck size={20} /></span>
                                        <div><span className="pf-stat-num">{stats.verified}</span><span className="pf-stat-label">Terverifikasi</span></div>
                                    </div>
                                    <div className="pf-stat">
                                        <span className="pf-stat-icon"><Clock3 size={20} /></span>
                                        <div><span className="pf-stat-num">{stats.pending}</span><span className="pf-stat-label">Menunggu Verifikasi</span></div>
                                    </div>
                                </section>

                                <div className="pf-layout">
                                    <div className="pf-stack">
                                        <section className="pf-card">
                                            <div className="pf-card-head">
                                                <h2 className="pf-card-title"><UserRound size={16} /> Tentang saya</h2>
                                                <button type="button" className="pf-btn ghost sm" onClick={() => setTab('biodata')}>Kelola biodata</button>
                                            </div>
                                            <div className="pf-card-body">
                                                <div className="pf-list">
                                                    {[
                                                        ['Nama lengkap', profile.name],
                                                        ['Username', `@${profile.username}`],
                                                        ['Email', profile.email],
                                                        ['Telepon', profile.phone],
                                                        ['Tempat, tanggal lahir', [profile.placeOfBirth, profile.dateOfBirthLabel].filter(Boolean).join(', ') || '-'],
                                                        ['Jenis kelamin', profile.gender ?? '-'],
                                                        ['Agama', profile.religion ?? '-'],
                                                        ['Kewarganegaraan', profile.citizenship ?? '-'],
                                                    ].map(([label, value]) => (
                                                        <div className="pf-list-row" key={label as string}>
                                                            <small>{label}</small><b>{value}</b>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </section>

                                        {hasAcademic && (
                                            <section className="pf-card">
                                                <div className="pf-card-head">
                                                    <h2 className="pf-card-title"><GraduationCap size={16} /> Status akademik</h2>
                                                    <button type="button" className="pf-btn ghost sm" onClick={() => setTab('akademik')}>Lihat detail</button>
                                                </div>
                                                <div className="pf-card-body">
                                                    <div className="pf-list">
                                                        {student && (
                                                            <>
                                                                <div className="pf-list-row"><small>Program studi</small><b>{student.programName ?? '-'}</b></div>
                                                                <div className="pf-list-row"><small>NIM / Semester</small><b>{student.nim ?? '-'} · Semester {student.currentSemester ?? '-'}</b></div>
                                                                <div className="pf-list-row">
                                                                    <small>Status</small>
                                                                    <span><span className={`pf-badge ${student.academicStatus === 'Aktif' ? 'green' : 'amber'}`}>{student.academicStatus ?? '-'}</span></span>
                                                                </div>
                                                            </>
                                                        )}
                                                        {lecturer && (
                                                            <>
                                                                <div className="pf-list-row"><small>Fakultas</small><b>{lecturer.facultyName ?? '-'}</b></div>
                                                                <div className="pf-list-row"><small>Status kepegawaian</small><b>{lecturer.employmentStatus ?? '-'}</b></div>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            </section>
                                        )}
                                    </div>

                                    <aside className="pf-stack">
                                        <section className="pf-card">
                                            <div className="pf-card-head"><h3 className="pf-card-title"><ShieldCheck size={16} /> Keamanan akun</h3></div>
                                            <div className="pf-card-body tight">
                                                <div className="pf-metric">
                                                    <span className="pf-metric-icon"><KeyRound size={17} /></span>
                                                    <div><b>Kata sandi</b><small>Perbarui secara berkala minimal 8 karakter.</small></div>
                                                </div>
                                                <div className="pf-metric">
                                                    <span className="pf-metric-icon gold"><Fingerprint size={17} /></span>
                                                    <div><b>Peran aktif</b><small>{profile.activeRoleLabel} · {profile.roles.length} peran dimiliki.</small></div>
                                                </div>
                                                <button type="button" className="pf-btn ghost sm" style={{ marginTop: 12, width: '100%' }} onClick={() => setTab('keamanan')}>
                                                    Buka pengaturan keamanan
                                                </button>
                                            </div>
                                        </section>

                                        <section className="pf-card">
                                            <div className="pf-card-head">
                                                <h3 className="pf-card-title"><Award size={16} /> Sertifikasi terbaru</h3>
                                                <span className="pf-badge gray">{stats.certifications}</span>
                                            </div>
                                            <div className="pf-card-body tight">
                                                {developments.length === 0 && (
                                                    <EmptyState icon={Award} iconSize={24} title="Belum ada sertifikasi" description="Ajukan sertifikasi pertama Anda dari tab Sertifikasi." style={{ minHeight: 0, padding: '20px 14px' }} />
                                                )}
                                                {developments.slice(0, 3).map((item) => (
                                                    <div className="pf-metric" key={item.id}>
                                                        <span className="pf-metric-icon gold"><Award size={17} /></span>
                                                        <div>
                                                            <b>{item.title}</b>
                                                            <small>{item.organizer ?? '-'} · {item.startDateLabel ?? '-'}</small>
                                                        </div>
                                                        <span className={`pf-badge ${item.isVerified ? 'green' : 'amber'}`}>{item.isVerified ? 'Sah' : 'Antre'}</span>
                                                    </div>
                                                ))}
                                                {developments.length > 0 && (
                                                    <button type="button" className="pf-btn ghost sm" style={{ marginTop: 12, width: '100%' }} onClick={() => setTab('sertifikasi')}>
                                                        Kelola sertifikasi
                                                    </button>
                                                )}
                                            </div>
                                        </section>

                                        <section className="pf-card">
                                            <div className="pf-card-body" style={{ display: 'grid', gap: 10 }}>
                                                <span className="pf-kicker">Akses cepat</span>
                                                <a className="pf-btn primary" href={shell.dashboardUrl}><LayoutDashboard size={15} /> Buka dashboard</a>
                                                <a className="pf-btn ghost" href={shell.switchRoleUrl}>Ganti peran aktif</a>
                                            </div>
                                        </section>
                                    </aside>
                                </div>
                            </>
                        )}

                        {tab === 'biodata' && (
                            <form onSubmit={submitBiodata}>
                                <div className="pf-layout">
                                    <div className="pf-stack">
                                        <section className="pf-card">
                                            <div className="pf-card-head"><h2 className="pf-card-title"><UserRound size={16} /> Data pribadi</h2><span className="pf-badge gray">Tersimpan otomatis ke akun</span></div>
                                            <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                                <div className="pf-photo-row">
                                                    {photoPreview
                                                        ? <img className="pf-photo-thumb" src={photoPreview} alt="Foto profil" />
                                                        : <span className="pf-photo-thumb fallback">{initialsFor(profile.name)}</span>}
                                                    <div style={{ display: 'grid', gap: 8, flex: 1 }}>
                                                        <label className="pf-upload" htmlFor="pf-photo-input">
                                                            <Camera size={15} />
                                                            <span style={{ fontSize: 12.5, fontWeight: 700 }}>{biodata.data.photo ? biodata.data.photo.name : 'Ganti foto profil…'}</span>
                                                            <input
                                                                id="pf-photo-input"
                                                                type="file"
                                                                accept="image/jpeg,image/png,image/jpg,image/webp"
                                                                onChange={(event) => biodata.setData('photo', event.target.files?.[0] ?? null)}
                                                            />
                                                        </label>
                                                        <small className="pf-hint">JPG, PNG, atau WEBP · maksimal 2 MB. Foto lama dihapus otomatis.</small>
                                                        <FieldError message={biodata.errors.photo} />
                                                    </div>
                                                </div>
                                                <div className="pf-grid-2">
                                                    <div className="pf-field">
                                                        <label>Nama depan <i>*</i></label>
                                                        <input className="pf-input" value={biodata.data.first_name} onChange={(e) => biodata.setData('first_name', e.target.value)} placeholder="Nama depan" />
                                                        <FieldError message={biodata.errors.first_name} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Nama belakang <i>*</i></label>
                                                        <input className="pf-input" value={biodata.data.last_name} onChange={(e) => biodata.setData('last_name', e.target.value)} placeholder="Nama belakang" />
                                                        <FieldError message={biodata.errors.last_name} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Username <i>*</i></label>
                                                        <input className="pf-input" value={biodata.data.username} onChange={(e) => biodata.setData('username', e.target.value)} placeholder="username_unik" />
                                                        <FieldError message={biodata.errors.username} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Nomor identitas</label>
                                                        <input className="pf-input" value={biodata.data.identity_number} onChange={(e) => biodata.setData('identity_number', e.target.value)} placeholder="NIK / nomor identitas" />
                                                        <FieldError message={biodata.errors.identity_number} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Jenis kelamin</label>
                                                        <select className="pf-select" value={biodata.data.gender} onChange={(e) => biodata.setData('gender', e.target.value)}>
                                                            <option value="">Pilih jenis kelamin</option>
                                                            {options.genders.map((item) => <option key={item} value={item}>{item}</option>)}
                                                        </select>
                                                        <FieldError message={biodata.errors.gender} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Agama</label>
                                                        <select className="pf-select" value={biodata.data.religion} onChange={(e) => biodata.setData('religion', e.target.value)}>
                                                            <option value="">Pilih agama</option>
                                                            {options.religions.map((item) => <option key={item} value={item}>{item}</option>)}
                                                        </select>
                                                        <FieldError message={biodata.errors.religion} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Tempat lahir</label>
                                                        <input className="pf-input" value={biodata.data.place_of_birth} onChange={(e) => biodata.setData('place_of_birth', e.target.value)} placeholder="Contoh: Jakarta" />
                                                        <FieldError message={biodata.errors.place_of_birth} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Tanggal lahir</label>
                                                        <input className="pf-input" type="date" value={biodata.data.date_of_birth} onChange={(e) => biodata.setData('date_of_birth', e.target.value)} />
                                                        <FieldError message={biodata.errors.date_of_birth} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Golongan darah</label>
                                                        <select className="pf-select" value={biodata.data.blood_type} onChange={(e) => biodata.setData('blood_type', e.target.value)}>
                                                            <option value="">Pilih golongan darah</option>
                                                            {options.bloodTypes.map((item) => <option key={item} value={item}>{item}</option>)}
                                                        </select>
                                                        <FieldError message={biodata.errors.blood_type} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Kewarganegaraan</label>
                                                        <select className="pf-select" value={biodata.data.citizenship} onChange={(e) => biodata.setData('citizenship', e.target.value)}>
                                                            <option value="">Pilih kewarganegaraan</option>
                                                            {options.citizenships.map((item) => <option key={item} value={item}>{item === 'WNI' ? 'Warga Negara Indonesia (WNI)' : 'Warga Negara Asing (WNA)'}</option>)}
                                                        </select>
                                                        <FieldError message={biodata.errors.citizenship} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Tinggi badan (cm)</label>
                                                        <input className="pf-input" type="number" min={1} value={biodata.data.height} onChange={(e) => biodata.setData('height', e.target.value)} placeholder="Contoh: 170" />
                                                        <FieldError message={biodata.errors.height} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Berat badan (kg)</label>
                                                        <input className="pf-input" type="number" min={1} value={biodata.data.weight} onChange={(e) => biodata.setData('weight', e.target.value)} placeholder="Contoh: 65" />
                                                        <FieldError message={biodata.errors.weight} />
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                    </div>

                                    <aside className="pf-stack">
                                        <section className="pf-card">
                                            <div className="pf-card-head"><h3 className="pf-card-title"><Mail size={16} /> Kontak & sosial</h3></div>
                                            <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                                <div className="pf-field">
                                                    <label>Email <i>*</i></label>
                                                    <input className="pf-input" type="email" value={biodata.data.email} onChange={(e) => biodata.setData('email', e.target.value)} placeholder="nama@email.com" />
                                                    <FieldError message={biodata.errors.email} />
                                                </div>
                                                <div className="pf-field">
                                                    <label>Nomor telepon <i>*</i></label>
                                                    <input className="pf-input" value={biodata.data.phone} onChange={(e) => biodata.setData('phone', e.target.value)} placeholder="Contoh: 081234567890" />
                                                    <FieldError message={biodata.errors.phone} />
                                                </div>
                                                <div className="pf-field">
                                                    <label>Instagram</label>
                                                    <input className="pf-input" value={biodata.data.instagram} onChange={(e) => biodata.setData('instagram', e.target.value)} placeholder="@username" />
                                                    <FieldError message={biodata.errors.instagram} />
                                                </div>
                                                <div className="pf-field">
                                                    <label>Facebook</label>
                                                    <input className="pf-input" value={biodata.data.facebook} onChange={(e) => biodata.setData('facebook', e.target.value)} placeholder="facebook.com/username" />
                                                    <FieldError message={biodata.errors.facebook} />
                                                </div>
                                                <div className="pf-field">
                                                    <label>LinkedIn</label>
                                                    <input className="pf-input" value={biodata.data.linkedin} onChange={(e) => biodata.setData('linkedin', e.target.value)} placeholder="linkedin.com/in/username" />
                                                    <FieldError message={biodata.errors.linkedin} />
                                                </div>
                                                <div className="pf-form-actions">
                                                    <button type="submit" className="pf-btn primary" disabled={biodata.processing}>
                                                        <CircleCheck size={15} /> {biodata.processing ? 'Menyimpan…' : 'Simpan perubahan'}
                                                    </button>
                                                </div>
                                            </div>
                                        </section>
                                    </aside>
                                </div>
                            </form>
                        )}

                        {tab === 'keamanan' && (
                            <div className="pf-layout">
                                <section className="pf-card">
                                    <div className="pf-card-head"><h2 className="pf-card-title"><KeyRound size={16} /> Ubah kata sandi</h2></div>
                                    <form onSubmit={submitPassword}>
                                        <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                            <div className="pf-field">
                                                <label>Kata sandi saat ini</label>
                                                <input className="pf-input" type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} placeholder="Masukkan kata sandi lama" />
                                                <FieldError message={password.errors.current_password} />
                                            </div>
                                            <div className="pf-grid-2">
                                                <div className="pf-field">
                                                    <label>Kata sandi baru</label>
                                                    <input className="pf-input" type="password" autoComplete="new-password" value={password.data.new_password} onChange={(e) => password.setData('new_password', e.target.value)} placeholder="Minimal 8 karakter" />
                                                    <FieldError message={password.errors.new_password} />
                                                </div>
                                                <div className="pf-field">
                                                    <label>Konfirmasi kata sandi baru</label>
                                                    <input className="pf-input" type="password" autoComplete="new-password" value={password.data.new_password_confirmation} onChange={(e) => password.setData('new_password_confirmation', e.target.value)} placeholder="Ulangi kata sandi baru" />
                                                </div>
                                            </div>
                                            <div className="pf-form-actions">
                                                <button type="submit" className="pf-btn primary" disabled={password.processing}>
                                                    <ShieldCheck size={15} /> {password.processing ? 'Memperbarui…' : 'Perbarui kata sandi'}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </section>

                                <aside className="pf-stack">
                                    <section className="pf-card">
                                        <div className="pf-card-head"><h3 className="pf-card-title"><ShieldCheck size={16} /> Sesi & peran</h3></div>
                                        <div className="pf-card-body tight">
                                            <div className="pf-metric">
                                                <span className="pf-metric-icon"><Phone size={17} /></span>
                                                <div><b>Login terakhir</b><small>{profile.lastLogin ?? 'Belum tercatat'}</small></div>
                                            </div>
                                            <div className="pf-metric">
                                                <span className="pf-metric-icon gold"><Fingerprint size={17} /></span>
                                                <div><b>Peran aktif</b><small>{profile.activeRoleLabel} dari {profile.roles.length} peran.</small></div>
                                            </div>
                                            <div className="pf-metric">
                                                <span className="pf-metric-icon"><CalendarDays size={17} /></span>
                                                <div><b>Anggota sejak</b><small>{profile.memberSince ?? '-'}</small></div>
                                            </div>
                                        </div>
                                    </section>
                                    <div className="pf-note info"><Info size={15} /><span>Gunakan kombinasi huruf besar, angka, dan simbol. Jangan bagikan kata sandi kepada siapa pun termasuk pihak kampus.</span></div>
                                </aside>
                            </div>
                        )}

                        {tab === 'akademik' && hasAcademic && (
                            <div className="pf-grid-2" style={{ alignItems: 'start' }}>
                                {student && (
                                    <section className="pf-card">
                                        <div className="pf-card-head">
                                            <h2 className="pf-card-title"><GraduationCap size={16} /> Profil mahasiswa</h2>
                                            <span className={`pf-badge ${student.academicStatus === 'Aktif' ? 'green' : 'amber'}`}>{student.academicStatus ?? '-'}</span>
                                        </div>
                                        <div className="pf-card-body">
                                            <div className="pf-list">
                                                <div className="pf-list-row"><small>NIM</small><b>{student.nim ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Program studi</small><b>{student.programName ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Fakultas</small><b>{student.facultyName ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Tahun masuk</small><b>{student.entryYear ?? '-'} {student.entryAcademicYear ? `· ${student.entryAcademicYear}` : ''}</b></div>
                                                <div className="pf-list-row"><small>Semester / Kelas</small><b>Semester {student.currentSemester ?? '-'} · {student.classType ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Tanggal masuk</small><b>{student.entryDateLabel ?? '-'}</b></div>
                                            </div>
                                            <div className="pf-note gold" style={{ marginTop: 14 }}><Building2 size={15} /><span>Data akademik bersifat read-only. Perubahan dilakukan melalui bagian akademik.</span></div>
                                        </div>
                                    </section>
                                )}
                                {lecturer && (
                                    <section className="pf-card">
                                        <div className="pf-card-head">
                                            <h2 className="pf-card-title"><IdCard size={16} /> Profil dosen</h2>
                                            <span className="pf-badge gray">{lecturer.employmentStatus ?? '-'}</span>
                                        </div>
                                        <div className="pf-card-body">
                                            <div className="pf-list">
                                                <div className="pf-list-row"><small>NIDN</small><b>{lecturer.nidn ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>NIDK</small><b>{lecturer.nidk ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>NIP</small><b>{lecturer.nip ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Fakultas</small><b>{lecturer.facultyName ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Program studi</small><b>{lecturer.programName ?? '-'}</b></div>
                                                <div className="pf-list-row"><small>Bergabung sejak</small><b>{lecturer.joinDateLabel ?? '-'}</b></div>
                                            </div>
                                            <div className="pf-note gold" style={{ marginTop: 14 }}><Building2 size={15} /><span>Data kepegawaian bersifat read-only. Perubahan dilakukan melalui bagian kepegawaian.</span></div>
                                        </div>
                                    </section>
                                )}
                            </div>
                        )}

                        {tab === 'sertifikasi' && (
                            <div className="pf-stack">
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><Award size={16} /> Riwayat pengembangan diri</h2>
                                        <button type="button" className="pf-btn primary sm" onClick={() => setCertOpen(!certOpen)}>
                                            {certOpen ? <><X size={14} /> Batal</> : <><Plus size={14} /> Ajukan baru</>}
                                        </button>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 16 }}>
                                        <div className="pf-note info"><Info size={15} /><span>Pengajuan baru diverifikasi bagian kepegawaian sebelum berstatus sah. Dokumen tersimpan privat dan hanya dapat dilihat pemilik serta verifikator.</span></div>

                                        {certOpen && (
                                            <form onSubmit={submitCert} style={{ display: 'grid', gap: 14, padding: 16, border: '1px solid var(--pf-line)', borderRadius: 12, background: 'var(--pf-soft)' }}>
                                                <div className="pf-grid-2">
                                                    <div className="pf-field">
                                                        <label>Jenis kegiatan <i>*</i></label>
                                                        <select className="pf-select" value={cert.data.type} onChange={(e) => cert.setData('type', e.target.value)}>
                                                            <option value="certification">Sertifikasi</option>
                                                            <option value="training">Pelatihan</option>
                                                            <option value="workshop">Workshop</option>
                                                            <option value="seminar">Seminar</option>
                                                            <option value="award">Penghargaan</option>
                                                            <option value="license">Lisensi</option>
                                                        </select>
                                                        <FieldError message={cert.errors.type} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Judul <i>*</i></label>
                                                        <input className="pf-input" value={cert.data.title} onChange={(e) => cert.setData('title', e.target.value)} placeholder="Contoh: AWS Certified Developer" />
                                                        <FieldError message={cert.errors.title} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Penyelenggara <i>*</i></label>
                                                        <input className="pf-input" value={cert.data.organizer} onChange={(e) => cert.setData('organizer', e.target.value)} placeholder="Contoh: Amazon Web Services" />
                                                        <FieldError message={cert.errors.organizer} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Nomor kredensial</label>
                                                        <input className="pf-input" value={cert.data.credential_number} onChange={(e) => cert.setData('credential_number', e.target.value)} placeholder="Nomor sertifikat / lisensi" />
                                                        <FieldError message={cert.errors.credential_number} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Tanggal pelaksanaan <i>*</i></label>
                                                        <input className="pf-input" type="date" value={cert.data.start_date} onChange={(e) => cert.setData('start_date', e.target.value)} />
                                                        <FieldError message={cert.errors.start_date} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Tanggal selesai</label>
                                                        <input className="pf-input" type="date" value={cert.data.end_date} onChange={(e) => cert.setData('end_date', e.target.value)} />
                                                        <FieldError message={cert.errors.end_date} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Berlaku hingga</label>
                                                        <input className="pf-input" type="date" value={cert.data.expires_at} onChange={(e) => cert.setData('expires_at', e.target.value)} />
                                                        <FieldError message={cert.errors.expires_at} />
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Dokumen <i>*</i></label>
                                                        <label className="pf-upload" htmlFor="pf-cert-file">
                                                            <Upload size={15} />
                                                            <span style={{ fontSize: 12.5, fontWeight: 700 }}>{cert.data.document ? cert.data.document.name : 'Pilih berkas…'}</span>
                                                            <input id="pf-cert-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" onChange={(e) => cert.setData('document', e.target.files?.[0] ?? null)} />
                                                        </label>
                                                        <small className="pf-hint">PDF / JPG / PNG / WEBP · maksimal 5 MB.</small>
                                                        <FieldError message={cert.errors.document} />
                                                    </div>
                                                </div>
                                                <div className="pf-field">
                                                    <label>Deskripsi</label>
                                                    <textarea className="pf-textarea" value={cert.data.description} onChange={(e) => cert.setData('description', e.target.value)} placeholder="Kompetensi atau keterangan tambahan" />
                                                    <FieldError message={cert.errors.description} />
                                                </div>
                                                <div className="pf-form-actions" style={{ border: 0, padding: 0, margin: 0 }}>
                                                    <button type="submit" className="pf-btn primary" disabled={cert.processing}>
                                                        <Upload size={15} /> {cert.processing ? 'Mengunggah…' : 'Kirim pengajuan'}
                                                    </button>
                                                </div>
                                            </form>
                                        )}

                                        {developments.length === 0 && (
                                            <EmptyState icon={Award} iconSize={26} title="Belum ada riwayat" description="Riwayat sertifikasi, pelatihan, dan penghargaan Anda akan tampil di sini." />
                                        )}

                                        {developments.length > 0 && (
                                            <div style={{ display: 'grid', gap: 14 }}>
                                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                                    <span className="pf-badge green"><BadgeCheck size={12} /> {stats.verified} terverifikasi</span>
                                                    <span className="pf-badge amber"><Clock3 size={12} /> {stats.pending} menunggu</span>
                                                    <span className="pf-badge gray"><MapPin size={12} /> Berlaku: cek tiap item</span>
                                                </div>
                                                <div className="pf-timeline">
                                                    {developments.map((item) => (
                                                        <div className="pf-timeline-item" key={item.id}>
                                                            <span className={`pf-timeline-dot${item.isVerified ? ' verified' : ''}`} />
                                                            <div className="pf-cert">
                                                                <div className="pf-cert-top">
                                                                    <div>
                                                                        <h4>{item.title}</h4>
                                                                        <small>{item.organizer ?? '-'} · {item.startDateLabel ?? '-'}{item.endDateLabel ? ` – ${item.endDateLabel}` : ''}</small>
                                                                    </div>
                                                                    <span className={`pf-badge ${item.isVerified ? 'green' : 'amber'}`}>
                                                                        {item.isVerified ? <><BadgeCheck size={12} /> Terverifikasi</> : <><Clock3 size={12} /> Menunggu</>}
                                                                    </span>
                                                                </div>
                                                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 10 }}>
                                                                    <span className="pf-badge gray">{item.typeLabel}</span>
                                                                    {item.credentialNumber && <span className="pf-badge gray">{item.credentialNumber}</span>}
                                                                    <span className="pf-badge gray">Berlaku: {item.expiresLabel}</span>
                                                                </div>
                                                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 12 }}>
                                                                    {item.attachments.length === 0 && <small className="pf-hint">Tidak ada dokumen.</small>}
                                                                    {item.attachments.map((file) => (
                                                                        <a key={file.id} className="pf-btn ghost sm" href={file.previewUrl} target="_blank" rel="noopener noreferrer">
                                                                            <FileText size={13} /> {file.fileName.length > 26 ? `${file.fileName.slice(0, 24)}…` : file.fileName} <Eye size={13} />
                                                                        </a>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </section>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
