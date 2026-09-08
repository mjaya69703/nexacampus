// Form tambah/ubah pengguna — berbahasa visual halaman profile (pf-*),
// dikhususkan untuk admin: tab peran + sub-form mahasiswa/dosen.
// Profile.tsx tidak disentuh; pola disalin ke konteks admin di sini.
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Briefcase, Camera, GraduationCap, ShieldCheck, UserRound, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { Checklist } from '../../../../components/Shared/Crud/Checklist';
import '../../../../../css/profile.css';
import '../../../../../css/crud.css';

type IdName = { id: number; name: string };

type StudentData = {
    study_program_id: number | ''; entry_academic_year_id: number | ''; nim: string;
    entry_year: number | ''; academic_status: string; entry_date: string;
    graduation_date: string; current_semester: number | ''; is_active: boolean; desc: string;
};

type LecturerData = {
    faculty_id: number | ''; study_program_id: number | ''; nidn: string; nidk: string;
    nip: string; employment_status: string; join_date: string; is_active: boolean; desc: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    user: {
        id?: number; first_name: string; last_name: string; username: string; email: string;
        phone: string; instagram: string | null; facebook: string | null; linkedin: string | null;
        identity_number: string | null; religion: string | null; blood_type: string | null;
        citizenship: string | null; gender: string | null; height: number | null; weight: number | null;
        place_of_birth: string | null; date_of_birth: string | null; photo_url?: string;
        is_active: boolean; fst_setup: boolean; tfa_setup: boolean;
        is_self?: boolean; role_ids: (number | string)[];
    };
    student: {
        study_program_id: number | null; entry_academic_year_id: number | null; nim: string | null;
        entry_year: number | null; academic_status: string | null; entry_date: string | null;
        graduation_date: string | null; current_semester: number | null; is_active: boolean; desc: string | null;
    } | null;
    lecturer: {
        faculty_id: number | null; study_program_id: number | null; nidn: string | null; nidk: string | null;
        nip: string | null; employment_status: string | null; join_date: string | null;
        is_active: boolean; desc: string | null;
    } | null;
    roles: IdName[];
    studentRoleId: number | null;
    lecturerRoleId: number | null;
    faculties: IdName[];
    studyPrograms: IdName[];
    academicYears: IdName[];
    urls: { index: string; submit: string };
};

const BLANK_STUDENT: StudentData = {
    study_program_id: '', entry_academic_year_id: '', nim: '', entry_year: '',
    academic_status: '', entry_date: '', graduation_date: '', current_semester: '',
    is_active: true, desc: '',
};

const BLANK_LECTURER: LecturerData = {
    faculty_id: '', study_program_id: '', nidn: '', nidk: '', nip: '',
    employment_status: '', join_date: '', is_active: true, desc: '',
};

type Tab = 'biodata' | 'keamanan' | 'peran' | 'student' | 'lecturer';

const initialsFor = (name: string) =>
    name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();

function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <span className="pf-error">{message}</span>;
}

export default function UserForm({ shell, mode, user, student, lecturer, roles, studentRoleId, lecturerRoleId, faculties, studyPrograms, academicYears, urls }: Props) {
    const isCreate = mode === 'create';
    const [tab, setTab] = useState<Tab>('biodata');

    const form = useForm({
        first_name: user.first_name,
        last_name: user.last_name,
        username: user.username,
        email: user.email,
        phone: user.phone,
        photo: null as File | null,
        password: '',
        password_confirmation: '',
        new_password: '',
        new_password_confirmation: '',
        instagram: user.instagram ?? '',
        facebook: user.facebook ?? '',
        linkedin: user.linkedin ?? '',
        identity_number: user.identity_number ?? '',
        religion: user.religion ?? '',
        blood_type: user.blood_type ?? '',
        citizenship: user.citizenship ?? '',
        gender: user.gender ?? '',
        height: (user.height ?? '') as number | '',
        weight: (user.weight ?? '') as number | '',
        place_of_birth: user.place_of_birth ?? '',
        date_of_birth: user.date_of_birth ?? '',
        is_active: user.is_active,
        fst_setup: user.fst_setup,
        tfa_setup: user.tfa_setup,
        role_ids: user.role_ids as number[],
        student: student ? {
            study_program_id: (student.study_program_id ?? '') as number | '',
            entry_academic_year_id: (student.entry_academic_year_id ?? '') as number | '',
            nim: student.nim ?? '',
            entry_year: (student.entry_year ?? '') as number | '',
            academic_status: student.academic_status ?? '',
            entry_date: student.entry_date ?? '',
            graduation_date: student.graduation_date ?? '',
            current_semester: (student.current_semester ?? '') as number | '',
            is_active: student.is_active,
            desc: student.desc ?? '',
        } : { ...BLANK_STUDENT },
        lecturer: lecturer ? {
            faculty_id: (lecturer.faculty_id ?? '') as number | '',
            study_program_id: (lecturer.study_program_id ?? '') as number | '',
            nidn: lecturer.nidn ?? '',
            nidk: lecturer.nidk ?? '',
            nip: lecturer.nip ?? '',
            employment_status: lecturer.employment_status ?? '',
            join_date: lecturer.join_date ?? '',
            is_active: lecturer.is_active,
            desc: lecturer.desc ?? '',
        } : { ...BLANK_LECTURER },
    });

    const roleIds = useMemo(() => form.data.role_ids.map((v) => Number(v)), [form.data.role_ids]);
    const showStudent = (studentRoleId !== null && roleIds.includes(studentRoleId)) || student !== null;
    const showLecturer = (lecturerRoleId !== null && roleIds.includes(lecturerRoleId)) || lecturer !== null;

    const errors = form.errors as Record<string, string>;
    const displayName = `${form.data.first_name} ${form.data.last_name}`.trim() || (isCreate ? 'Pengguna Baru' : 'Pengguna');
    const photoPreview = useMemo(
        () => (form.data.photo ? URL.createObjectURL(form.data.photo) : (user.photo_url ?? null)),
        [form.data.photo, user.photo_url],
    );
    const roleNames = useMemo(
        () => roles.filter((r) => roleIds.includes(r.id)).map((r) => r.name),
        [roles, roleIds],
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const tabs: { key: Tab; label: string; icon: typeof UserRound; count?: number; hidden?: boolean }[] = [
        { key: 'biodata', label: 'Biodata', icon: UserRound },
        { key: 'keamanan', label: 'Keamanan', icon: ShieldCheck },
        { key: 'peran', label: 'Peran', icon: Users, count: roleIds.length },
        { key: 'student', label: 'Mahasiswa', icon: GraduationCap, hidden: !showStudent },
        { key: 'lecturer', label: 'Dosen', icon: Briefcase, hidden: !showLecturer },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Pengguna · ${shell.appName}`} />
            <div className="pf-root">
                <div className="pf-content">
                    <section className="pf-head">
                        <div className="pf-cover" aria-hidden="true" />
                        <div className="pf-head-body">
                            <div className="pf-identity">
                                <div className="pf-avatar">
                                    {photoPreview
                                        ? <img src={photoPreview} alt={displayName} />
                                        : <span className="pf-avatar-fallback">{initialsFor(displayName) || '?'}</span>}
                                    <span className="pf-presence" aria-hidden="true" />
                                </div>
                                <div className="pf-head-copy">
                                    <h1>{displayName}</h1>
                                    <p>@{form.data.username || '-'} · {form.data.email || '-'}</p>
                                    <div className="pf-badges">
                                        <span className={`pf-badge ${form.data.is_active ? 'green' : 'amber'}`}>
                                            {form.data.is_active ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                        {roleNames.map((name) => (
                                            <span className="pf-badge gray" key={name}>{name}</span>
                                        ))}
                                    </div>
                                </div>
                            </div>
                            <aside className="pf-head-side" aria-label="Tindakan">
                                <p>{isCreate ? 'Akun baru dengan password wajib diisi.' : 'Perubahan di semua tab tersimpan sekaligus.'}</p>
                                <a className="pf-btn ghost sm" href={urls.index}><ArrowLeft size={14} /> Kembali ke daftar</a>
                            </aside>
                        </div>
                        <div className="pf-tabs-wrap">
                            <div className="pf-tabs" role="tablist" aria-label="Navigasi form pengguna">
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
                        </div>
                    </section>

                    <div className="pf-panels">
                        <form onSubmit={submit}>
                            {tab === 'biodata' && (
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><UserRound size={16} /> Data pribadi</h2>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                        <div className="pf-photo-row">
                                            {photoPreview
                                                ? <img className="pf-photo-thumb" src={photoPreview} alt="Foto profil" />
                                                : <span className="pf-photo-thumb fallback">{initialsFor(displayName) || '?'}</span>}
                                            <div style={{ display: 'grid', gap: 8, flex: 1 }}>
                                                <label className="pf-upload" htmlFor="user-photo-input">
                                                    <Camera size={15} />
                                                    <span style={{ fontSize: 12.5, fontWeight: 700 }}>{form.data.photo ? form.data.photo.name : 'Ganti foto profil…'}</span>
                                                    <input
                                                        id="user-photo-input"
                                                        type="file"
                                                        accept="image/jpeg,image/png,image/jpg,image/webp"
                                                        onChange={(e) => form.setData('photo', e.target.files?.[0] ?? null)}
                                                    />
                                                </label>
                                                <small className="pf-hint">JPG, PNG, atau WEBP · maksimal 2 MB.</small>
                                                <FieldError message={errors.photo} />
                                            </div>
                                        </div>
                                        <div className="pf-grid-2">
                                            <div className="pf-field">
                                                <label>Nama depan <i>*</i></label>
                                                <input className="pf-input" value={form.data.first_name} onChange={(e) => form.setData('first_name', e.target.value)} placeholder="Nama depan" />
                                                <FieldError message={errors.first_name} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Nama belakang <i>*</i></label>
                                                <input className="pf-input" value={form.data.last_name} onChange={(e) => form.setData('last_name', e.target.value)} placeholder="Nama belakang" />
                                                <FieldError message={errors.last_name} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Username <i>*</i></label>
                                                <input className="pf-input" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} placeholder="username_unik" disabled={!isCreate && !!user.username} />
                                                <FieldError message={errors.username} />
                                                {!isCreate && <small className="pf-hint">Username tidak bisa diubah setelah diisi.</small>}
                                            </div>
                                            <div className="pf-field">
                                                <label>Email <i>*</i></label>
                                                <input className="pf-input" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} placeholder="nama@kampus.ac.id" />
                                                <FieldError message={errors.email} />
                                            </div>
                                            <div className="pf-field">
                                                <label>No. HP <i>*</i></label>
                                                <input className="pf-input" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} placeholder="08…" />
                                                <FieldError message={errors.phone} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Nomor identitas</label>
                                                <input className="pf-input" value={form.data.identity_number} onChange={(e) => form.setData('identity_number', e.target.value)} placeholder="NIK / nomor identitas" />
                                                <FieldError message={errors.identity_number} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Jenis kelamin</label>
                                                <select className="pf-select" value={form.data.gender} onChange={(e) => form.setData('gender', e.target.value)}>
                                                    <option value="">Pilih jenis kelamin</option>
                                                    <option value="Laki-laki">Laki-laki</option>
                                                    <option value="Perempuan">Perempuan</option>
                                                </select>
                                                <FieldError message={errors.gender} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Agama</label>
                                                <select className="pf-select" value={form.data.religion} onChange={(e) => form.setData('religion', e.target.value)}>
                                                    <option value="">Pilih agama</option>
                                                    {['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'].map((v) => <option key={v} value={v}>{v}</option>)}
                                                </select>
                                                <FieldError message={errors.religion} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Golongan darah</label>
                                                <select className="pf-select" value={form.data.blood_type} onChange={(e) => form.setData('blood_type', e.target.value)}>
                                                    <option value="">Pilih golongan darah</option>
                                                    {['A', 'B', 'AB', 'O'].map((v) => <option key={v} value={v}>{v}</option>)}
                                                </select>
                                                <FieldError message={errors.blood_type} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Kewarganegaraan</label>
                                                <select className="pf-select" value={form.data.citizenship} onChange={(e) => form.setData('citizenship', e.target.value)}>
                                                    <option value="">Pilih kewarganegaraan</option>
                                                    <option value="WNI">WNI</option>
                                                    <option value="WNA">WNA</option>
                                                </select>
                                                <FieldError message={errors.citizenship} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tempat lahir</label>
                                                <input className="pf-input" value={form.data.place_of_birth} onChange={(e) => form.setData('place_of_birth', e.target.value)} />
                                                <FieldError message={errors.place_of_birth} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tanggal lahir</label>
                                                <input className="pf-input" type="date" value={form.data.date_of_birth} onChange={(e) => form.setData('date_of_birth', e.target.value)} />
                                                <FieldError message={errors.date_of_birth} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tinggi (cm)</label>
                                                <input className="pf-input" type="number" value={form.data.height} onChange={(e) => form.setData('height', e.target.value === '' ? '' : Number(e.target.value))} />
                                                <FieldError message={errors.height} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Berat (kg)</label>
                                                <input className="pf-input" type="number" value={form.data.weight} onChange={(e) => form.setData('weight', e.target.value === '' ? '' : Number(e.target.value))} />
                                                <FieldError message={errors.weight} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Instagram</label>
                                                <input className="pf-input" value={form.data.instagram} onChange={(e) => form.setData('instagram', e.target.value)} />
                                                <FieldError message={errors.instagram} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Facebook</label>
                                                <input className="pf-input" value={form.data.facebook} onChange={(e) => form.setData('facebook', e.target.value)} />
                                                <FieldError message={errors.facebook} />
                                            </div>
                                            <div className="pf-field">
                                                <label>LinkedIn</label>
                                                <input className="pf-input" value={form.data.linkedin} onChange={(e) => form.setData('linkedin', e.target.value)} />
                                                <FieldError message={errors.linkedin} />
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {tab === 'keamanan' && (
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><ShieldCheck size={16} /> Keamanan & status akun</h2>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                        <div className="pf-grid-2">
                                            {isCreate ? (
                                                <>
                                                    <div className="pf-field">
                                                        <label>Password <i>*</i></label>
                                                        <input className="pf-input" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                                                        <FieldError message={errors.password} />
                                                        <small className="pf-hint">Minimal 8 karakter.</small>
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Konfirmasi password <i>*</i></label>
                                                        <input className="pf-input" type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                                                        <FieldError message={errors.password_confirmation} />
                                                    </div>
                                                </>
                                            ) : (
                                                <>
                                                    <div className="pf-field">
                                                        <label>Password baru</label>
                                                        <input className="pf-input" type="password" value={form.data.new_password} onChange={(e) => form.setData('new_password', e.target.value)} />
                                                        <FieldError message={errors.new_password} />
                                                        <small className="pf-hint">Kosongkan bila tidak ingin mengganti. Minimal 8 karakter.</small>
                                                    </div>
                                                    <div className="pf-field">
                                                        <label>Konfirmasi password baru</label>
                                                        <input className="pf-input" type="password" value={form.data.new_password_confirmation} onChange={(e) => form.setData('new_password_confirmation', e.target.value)} />
                                                        <FieldError message={errors.new_password_confirmation} />
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                        <div className="pf-grid-2">
                                            <label className="pf-check">
                                                <input type="checkbox" checked={form.data.is_active} disabled={!isCreate && !!user.is_self} onChange={(e) => form.setData('is_active', e.target.checked)} />
                                                <span>Akun aktif {!isCreate && user.is_self ? '(akun sendiri tidak bisa dinonaktifkan)' : ''}</span>
                                            </label>
                                            <label className="pf-check">
                                                <input type="checkbox" checked={form.data.fst_setup} onChange={(e) => form.setData('fst_setup', e.target.checked)} />
                                                <span>Setup awal selesai</span>
                                            </label>
                                            <label className="pf-check">
                                                <input type="checkbox" checked={form.data.tfa_setup} onChange={(e) => form.setData('tfa_setup', e.target.checked)} />
                                                <span>2FA aktif</span>
                                            </label>
                                        </div>
                                        <FieldError message={errors.is_active} />
                                    </div>
                                </section>
                            )}

                            {tab === 'peran' && (
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><Users size={16} /> Peran yang dimiliki</h2>
                                        <span className="pf-badge gray">{roleIds.length} dipilih</span>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 10 }}>
                                        <Checklist
                                            options={roles.map((role) => ({ id: role.id, label: role.name }))}
                                            selected={form.data.role_ids}
                                            onChange={(ids) => form.setData('role_ids', ids as number[])}
                                            placeholder="Cari peran…"
                                            hint="Tab Mahasiswa/Dosen muncul otomatis saat peran terkait dipilih."
                                            error={errors.role_ids}
                                        />
                                    </div>
                                </section>
                            )}

                            {tab === 'student' && showStudent && (
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><GraduationCap size={16} /> Profil mahasiswa</h2>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                        <div className="pf-grid-2">
                                            <div className="pf-field">
                                                <label>Program studi</label>
                                                <select className="pf-select" value={form.data.student.study_program_id} onChange={(e) => form.setData('student', { ...form.data.student, study_program_id: e.target.value === '' ? '' : Number(e.target.value) })}>
                                                    <option value="">— Pilih —</option>
                                                    {studyPrograms.map((sp) => <option key={sp.id} value={sp.id}>{sp.name}</option>)}
                                                </select>
                                                <FieldError message={errors['student.study_program_id']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tahun masuk</label>
                                                <select className="pf-select" value={form.data.student.entry_academic_year_id} onChange={(e) => form.setData('student', { ...form.data.student, entry_academic_year_id: e.target.value === '' ? '' : Number(e.target.value) })}>
                                                    <option value="">— Pilih —</option>
                                                    {academicYears.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                                </select>
                                                <FieldError message={errors['student.entry_academic_year_id']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>NIM</label>
                                                <input className="pf-input" value={form.data.student.nim} onChange={(e) => form.setData('student', { ...form.data.student, nim: e.target.value })} />
                                                <FieldError message={errors['student.nim']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Angkatan</label>
                                                <input className="pf-input" type="number" value={form.data.student.entry_year} onChange={(e) => form.setData('student', { ...form.data.student, entry_year: e.target.value === '' ? '' : Number(e.target.value) })} />
                                                <FieldError message={errors['student.entry_year']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Status akademik</label>
                                                <select className="pf-select" value={form.data.student.academic_status} onChange={(e) => form.setData('student', { ...form.data.student, academic_status: e.target.value })}>
                                                    <option value="">— Pilih —</option>
                                                    {['Aktif', 'Cuti', 'Lulus', 'Drop Out', 'Nonaktif', 'Keluar'].map((v) => <option key={v} value={v}>{v}</option>)}
                                                </select>
                                                <FieldError message={errors['student.academic_status']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Semester berjalan</label>
                                                <input className="pf-input" type="number" value={form.data.student.current_semester} onChange={(e) => form.setData('student', { ...form.data.student, current_semester: e.target.value === '' ? '' : Number(e.target.value) })} />
                                                <FieldError message={errors['student.current_semester']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tanggal masuk</label>
                                                <input className="pf-input" type="date" value={form.data.student.entry_date} onChange={(e) => form.setData('student', { ...form.data.student, entry_date: e.target.value })} />
                                                <FieldError message={errors['student.entry_date']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tanggal lulus</label>
                                                <input className="pf-input" type="date" value={form.data.student.graduation_date} onChange={(e) => form.setData('student', { ...form.data.student, graduation_date: e.target.value })} />
                                                <FieldError message={errors['student.graduation_date']} />
                                            </div>
                                            <label className="pf-check">
                                                <input type="checkbox" checked={form.data.student.is_active} onChange={(e) => form.setData('student', { ...form.data.student, is_active: e.target.checked })} />
                                                <span>Profil aktif</span>
                                            </label>
                                            <div className="pf-field" style={{ gridColumn: '1 / -1' }}>
                                                <label>Keterangan</label>
                                                <textarea className="pf-input" rows={3} value={form.data.student.desc} onChange={(e) => form.setData('student', { ...form.data.student, desc: e.target.value })} />
                                                <FieldError message={errors['student.desc']} />
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {tab === 'lecturer' && showLecturer && (
                                <section className="pf-card">
                                    <div className="pf-card-head">
                                        <h2 className="pf-card-title"><Briefcase size={16} /> Profil dosen</h2>
                                    </div>
                                    <div className="pf-card-body" style={{ display: 'grid', gap: 14 }}>
                                        <div className="pf-grid-2">
                                            <div className="pf-field">
                                                <label>Fakultas</label>
                                                <select className="pf-select" value={form.data.lecturer.faculty_id} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, faculty_id: e.target.value === '' ? '' : Number(e.target.value) })}>
                                                    <option value="">— Pilih —</option>
                                                    {faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                                </select>
                                                <FieldError message={errors['lecturer.faculty_id']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Program studi</label>
                                                <select className="pf-select" value={form.data.lecturer.study_program_id} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, study_program_id: e.target.value === '' ? '' : Number(e.target.value) })}>
                                                    <option value="">— Pilih —</option>
                                                    {studyPrograms.map((sp) => <option key={sp.id} value={sp.id}>{sp.name}</option>)}
                                                </select>
                                                <FieldError message={errors['lecturer.study_program_id']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>NIDN</label>
                                                <input className="pf-input" value={form.data.lecturer.nidn} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, nidn: e.target.value })} />
                                                <FieldError message={errors['lecturer.nidn']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>NIDK</label>
                                                <input className="pf-input" value={form.data.lecturer.nidk} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, nidk: e.target.value })} />
                                                <FieldError message={errors['lecturer.nidk']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>NIP</label>
                                                <input className="pf-input" value={form.data.lecturer.nip} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, nip: e.target.value })} />
                                                <FieldError message={errors['lecturer.nip']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Status kepegawaian</label>
                                                <select className="pf-select" value={form.data.lecturer.employment_status} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, employment_status: e.target.value })}>
                                                    <option value="">— Pilih —</option>
                                                    {['Tetap', 'Kontrak', 'Tidak Tetap', 'Tamu'].map((v) => <option key={v} value={v}>{v}</option>)}
                                                </select>
                                                <FieldError message={errors['lecturer.employment_status']} />
                                            </div>
                                            <div className="pf-field">
                                                <label>Tanggal bergabung</label>
                                                <input className="pf-input" type="date" value={form.data.lecturer.join_date} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, join_date: e.target.value })} />
                                                <FieldError message={errors['lecturer.join_date']} />
                                            </div>
                                            <label className="pf-check">
                                                <input type="checkbox" checked={form.data.lecturer.is_active} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, is_active: e.target.checked })} />
                                                <span>Profil aktif</span>
                                            </label>
                                            <div className="pf-field" style={{ gridColumn: '1 / -1' }}>
                                                <label>Keterangan</label>
                                                <textarea className="pf-input" rows={3} value={form.data.lecturer.desc} onChange={(e) => form.setData('lecturer', { ...form.data.lecturer, desc: e.target.value })} />
                                                <FieldError message={errors['lecturer.desc']} />
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="pf-card">
                                <div className="pf-card-body" style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
                                    <a className="pf-btn ghost" href={urls.index}>Batal</a>
                                    <button className="pf-btn primary" type="submit" disabled={form.processing}>
                                        {isCreate ? 'Simpan Pengguna' : 'Simpan Perubahan'}
                                    </button>
                                </div>
                            </section>
                        </form>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
