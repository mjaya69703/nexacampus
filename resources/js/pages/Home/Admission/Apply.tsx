// Pendaftaran mahasiswa baru — wizard 6 langkah (rail kiri + konten fokus).
import { useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    Check,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FolderOpen,
    GraduationCap,
    Hourglass,
    IdCard,
    Inbox,
    Pencil,
    Phone,
    Send,
    ShieldCheck,
    Sunrise,
    MoonStar,
    CalendarDays,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type Period = { id: number; name: string; code: string; opensAt: string | null; closesAt: string | null } | null;
type Option = { id: number; name: string };
type StudyProgramOption = Option & { facultyId: number | null };
type Requirement = { id: number; documentType: string; label: string; isRequired: boolean; allowedExtensions: string; maxSizeKb: number };
type Props = AdmissionBaseProps & { period: Period; faculties: Option[]; studyPrograms: StudyProgramOption[]; requirements: Requirement[]; stats: { activePeriods: number; studyPrograms: number; requirements: number; daysLeft: number } };
type ApplyFormData = {
    fullName: string; email: string; phone: string; birthDate: string; gender: string; address: string;
    emergencyContactName: string; emergencyContactPhone: string;
    highSchoolName: string; highSchoolMajor: string; highSchoolGraduationYear: string;
    facultyId: string; studyProgramId: string; classType: string;
    documents: Record<string, File | null>;
};

const classTypes = [
    { value: 'regular', label: 'Reguler Pagi', desc: 'Kuliah pagi–siang untuk lulusan baru.', icon: Sunrise },
    { value: 'evening', label: 'Karyawan / Malam', desc: 'Kuliah malam untuk yang bekerja.', icon: MoonStar },
    { value: 'weekend', label: 'Akhir Pekan', desc: 'Kelas Sabtu–Minggu / ekstensi.', icon: CalendarDays },
];

const steps = [
    { label: 'Data Diri', title: 'Ceritakan siapa Anda.', desc: 'Nama sesuai ijazah, email aktif, dan nomor yang bisa dihubungi.', icon: IdCard },
    { label: 'Kontak', title: 'Agar kami bisa menghubungi Anda.', desc: 'Alamat domisili dan kontak darurat orang tua atau wali.', icon: Phone },
    { label: 'Sekolah', title: 'Dari mana Anda berasal.', desc: 'Asal sekolah, jurusan, dan tahun kelulusan terakhir.', icon: GraduationCap },
    { label: 'Program Studi', title: 'Pilih masa depan Anda.', desc: 'Fakultas, program studi, dan kelas yang paling cocok.', icon: BookOpen },
    { label: 'Berkas', title: 'Lengkapi berkas Anda.', desc: 'Unggah setiap dokumen wajib dengan jelas dan terbaca.', icon: Upload },
    { label: 'Kirim', title: 'Periksa, lalu kirim.', desc: 'Pastikan semua benar — setelah terkirim, data dikunci untuk verifikasi.', icon: ClipboardCheck },
];

const isEmail = (value: string): boolean => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
const maxYear = new Date().getFullYear() + 1;

const err = (message?: string) => (message ? <span className="adm-error">{message}</span> : null);
const hint = (show: boolean, text = 'Bidang ini wajib diisi.') => (show ? <span className="adm-error">{text}</span> : null);

export default function Apply({ campus, links, user, period, faculties, studyPrograms, requirements, stats }: Props) {
    const [step, setStep] = useState(0);
    const [maxVisited, setMaxVisited] = useState(0);
    const [tried, setTried] = useState(false);
    const topRef = useRef<HTMLDivElement>(null);
    const form = useForm<ApplyFormData>({
        fullName: '', email: '', phone: '', birthDate: '', gender: '', address: '',
        emergencyContactName: '', emergencyContactPhone: '',
        highSchoolName: '', highSchoolMajor: '', highSchoolGraduationYear: '',
        facultyId: '', studyProgramId: '', classType: 'regular',
        documents: Object.fromEntries(requirements.map((requirement) => [requirement.documentType, null as File | null])),
    });
    const backendErrors = form.errors as Record<string, string | undefined>;
    const data = form.data;
    const availablePrograms = data.facultyId ? studyPrograms.filter((program) => String(program.facultyId ?? '') === data.facultyId) : studyPrograms;
    const chosenProgram = studyPrograms.find((program) => String(program.id) === data.studyProgramId) ?? null;
    const chosenFaculty = faculties.find((faculty) => String(faculty.id) === data.facultyId) ?? null;
    const requiredDocs = requirements.filter((requirement) => requirement.isRequired);
    const uploadedRequired = requiredDocs.filter((requirement) => data.documents[requirement.documentType]).length;

    useEffect(() => {
        setTried(false);
        topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [step]);

    const invalid = (key: string): boolean => {
        const raw = data[key as keyof ApplyFormData];
        const value = typeof raw === 'string' ? raw.trim() : '';
        switch (key) {
            case 'fullName': return value.length < 3;
            case 'email': return !isEmail(value);
            case 'phone': return value.replace(/\D/g, '').length < 9;
            case 'birthDate': return value === '';
            case 'gender': return data.gender !== 'male' && data.gender !== 'female';
            case 'address': return value.length < 5;
            case 'emergencyContactName': return value.length < 3;
            case 'emergencyContactPhone': return value.replace(/\D/g, '').length < 9;
            case 'highSchoolName': return value.length < 3;
            case 'highSchoolMajor': return value.length < 2;
            case 'highSchoolGraduationYear': {
                const year = Number(value);
                return !/^\d{4}$/.test(value) || year < 1980 || year > maxYear;
            }
            default: return false;
        }
    };

    const isDirty = (key: string): boolean => {
        if (key.startsWith('documents.')) return !!data.documents[key.slice('documents.'.length)];
        const value = data[key as keyof ApplyFormData];
        return typeof value === 'string' && value !== '';
    };

    const stepKeys = (index: number): string[] => {
        switch (index) {
            case 0: return ['fullName', 'email', 'phone', 'birthDate', 'gender'];
            case 1: return ['address', 'emergencyContactName', 'emergencyContactPhone'];
            case 2: return ['highSchoolName', 'highSchoolMajor', 'highSchoolGraduationYear'];
            case 4: return requiredDocs.map((requirement) => `documents.${requirement.documentType}`);
            default: return [];
        }
    };

    const stepMissing = (index: number): string[] => stepKeys(index).filter((key) =>
        key.startsWith('documents.') ? !data.documents[key.slice('documents.'.length)] : invalid(key),
    );

    const isStepDone = (index: number): boolean => {
        if (index === 3) return data.studyProgramId !== '';
        if (index >= steps.length - 1) return false;
        return index < step || (maxVisited > index && stepMissing(index).length === 0);
    };
    const doneCount = steps.slice(0, -1).filter((_, index) => isStepDone(index)).length;

    const goTo = (index: number) => {
        if (index < 0 || index >= steps.length || !period) return;
        if (index <= maxVisited) setStep(index);
    };

    const next = () => {
        if (stepMissing(step).length > 0) {
            setTried(true);
            topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        setMaxVisited((current) => Math.max(current, step + 1));
        setStep(step + 1);
    };

    const pickDocument = (requirement: Requirement, file: File | null) => {
        form.setData('documents', { ...data.documents, [requirement.documentType]: file });
    };

    const fileName = (documentType: string): string => (data.documents[documentType] as File | null)?.name ?? '';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admission/apply', { forceFormData: true });
    };

    const showHint = (key: string, text?: string) => hint(invalid(key) && (tried || isDirty(key)), text);
    const docsAttempted = requiredDocs.some((requirement) => tried || isDirty(`documents.${requirement.documentType}`));
    const programLabel = studyPrograms.find((program) => String(program.id) === data.studyProgramId)?.name ?? '—';
    const facultyLabel = faculties.find((faculty) => String(faculty.id) === data.facultyId)?.name ?? '—';
    const classLabel = classTypes.find((item) => item.value === data.classType)?.label ?? data.classType;
    const genderLabel = data.gender === 'male' ? 'Laki-laki' : data.gender === 'female' ? 'Perempuan' : '—';

    return <AdmissionShell campus={campus} links={links} user={user} activeTab="Pendaftaran" eyebrow="Pendaftaran" title="Daftar kuliah dalam 6 langkah singkat." description="Isi data diri, pilih program studi favorit, unggah berkas, lalu periksa kembali — selesai dalam beberapa menit, tanpa antre." icon={Send}>
        {!period && <EmptyState
            icon={Inbox}
            title="Pendaftaran Belum Dibuka"
            description="Saat ini belum ada gelombang penerimaan mahasiswa baru yang aktif. Silakan kembali lagi nanti atau lacak pendaftaran Anda yang sudah ada."
            action={<a className="adm-btn primary" style={{ marginTop: 12 }} href="/admission/status">Cek Aplikasi Saya</a>}
        />}
        {period && <div className="adm-stack" ref={topRef} style={{ scrollMarginTop: 18 }}>
            <div className="adm-wz-intake">
                <span className="adm-wz-intake-icon"><Hourglass size={22} /></span>
                <div className="grow">
                    <span className="adm-wz-intake-eyebrow">Gelombang aktif · {period.code}</span>
                    <b className="t">{period.name}</b>
                    <small>{period.opensAt ?? '—'} – {period.closesAt ?? '—'} · {stats.studyPrograms} program studi · {stats.requirements} berkas</small>
                </div>
                <div className="adm-wz-count"><b>{stats.daysLeft}</b><small>hari tersisa</small></div>
            </div>

            <div className="adm-wz">
                <aside className="adm-wz-rail" aria-label="Alur pendaftaran">
                    <span className="adm-wz-railcap">Alur pendaftaran</span>
                    <div className="adm-wz-railsteps">
                    {steps.map((item, index) => {
                        const done = isStepDone(index);
                        const state = index === step ? 'current' : done ? 'done' : '';
                        return <button key={item.label} type="button" disabled={index > maxVisited} onClick={() => goTo(index)} className={`adm-wz-step ${state}`} aria-current={index === step ? 'step' : undefined}>
                            <span className="adm-wz-dot">{done && index !== step ? <Check size={14} /> : index + 1}</span>
                            <span><b>{item.label}</b><small>{done && index !== step ? 'Selesai' : index === step ? 'Sedang diisi' : `Langkah ${index + 1}`}</small></span>
                        </button>;
                    })}
                    </div>
                    <div className="adm-wz-railfoot">
                        <small><b>{doneCount}</b> dari {steps.length - 1} bagian selesai</small>
                        <div className="adm-wprogress"><i style={{ width: `${Math.round((doneCount / (steps.length - 1)) * 100)}%` }} /></div>
                    </div>
                </aside>

                <div className="adm-wz-body">
                    <div className="adm-wz-topbar"><span>Langkah {step + 1} dari {steps.length} · {steps[step].label}</span><div className="adm-wprogress"><i style={{ width: `${Math.round(((step + 1) / steps.length) * 100)}%` }} /></div></div>
                    <span className="adm-wz-eyebrow">Langkah {step + 1} dari {steps.length}</span>
                    <h2>{steps[step].title}</h2>
                    <p className="adm-wz-desc">{steps[step].desc}</p>

                    <form onSubmit={submit} encType="multipart/form-data">
                        {step === 0 && <div className="adm-form-grid">
                            <div className="adm-field span-2"><label>Nama Lengkap (Sesuai Ijazah) <em>*</em></label><input className="adm-input" value={data.fullName} onChange={(event) => form.setData('fullName', event.target.value)} placeholder="Contoh: Ahmad Rizki Pratama" autoComplete="name" />{showHint('fullName', 'Isi nama lengkap minimal 3 huruf.')}{err(backendErrors.fullName)}</div>
                            <div className="adm-field"><label>Email Aktif <em>*</em></label><input type="email" className="adm-input" value={data.email} onChange={(event) => form.setData('email', event.target.value)} placeholder="email@domain.com" autoComplete="email" /><small className="adm-hint">Nomor pendaftaran dikirim ke email ini.</small>{showHint('email', 'Isi alamat email yang valid.')}{err(backendErrors.email)}</div>
                            <div className="adm-field"><label>Nomor WhatsApp / HP <em>*</em></label><input className="adm-input" inputMode="tel" value={data.phone} onChange={(event) => form.setData('phone', event.target.value)} placeholder="081234567890" autoComplete="tel" />{showHint('phone', 'Isi nomor HP yang valid (min. 9 digit).')}{err(backendErrors.phone)}</div>
                            <div className="adm-field"><label>Tanggal Lahir <em>*</em></label><input type="date" className="adm-input" value={data.birthDate} onChange={(event) => form.setData('birthDate', event.target.value)} />{showHint('birthDate')}{err(backendErrors.birthDate)}</div>
                            <div className="adm-field"><label>Jenis Kelamin <em>*</em></label><div className="adm-chip-row" role="radiogroup" aria-label="Jenis kelamin">
                                {[{ value: 'male', label: 'Laki-laki' }, { value: 'female', label: 'Perempuan' }].map((option) => <button key={option.value} type="button" role="radio" aria-checked={data.gender === option.value} className={`adm-chip-pick${data.gender === option.value ? ' selected' : ''}`} onClick={() => form.setData('gender', option.value)}>{data.gender === option.value && <Check size={14} />}{option.label}</button>)}
                            </div>{showHint('gender', 'Pilih salah satu.')}{err(backendErrors.gender)}</div>
                        </div>}

                        {step === 1 && <div className="adm-form-grid">
                            <div className="adm-field span-2"><label>Alamat Lengkap Domisili <em>*</em></label><textarea className="adm-input" rows={3} value={data.address} onChange={(event) => form.setData('address', event.target.value)} placeholder="Jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota/kabupaten" />{showHint('address', 'Isi alamat domisili Anda.')}{err(backendErrors.address)}</div>
                            <div className="adm-field"><label>Nama Kontak Darurat (Orang Tua/Wali) <em>*</em></label><input className="adm-input" value={data.emergencyContactName} onChange={(event) => form.setData('emergencyContactName', event.target.value)} placeholder="Nama Orang Tua / Wali" />{showHint('emergencyContactName')}{err(backendErrors.emergencyContactName)}</div>
                            <div className="adm-field"><label>No. HP Kontak Darurat <em>*</em></label><input className="adm-input" inputMode="tel" value={data.emergencyContactPhone} onChange={(event) => form.setData('emergencyContactPhone', event.target.value)} placeholder="0812xxxxxx" />{showHint('emergencyContactPhone', 'Isi nomor HP yang valid (min. 9 digit).')}{err(backendErrors.emergencyContactPhone)}</div>
                            <div className="adm-note span-2" style={{ gridColumn: '1 / -1' }}><ShieldCheck size={15} /> <span>Kontak darurat hanya dihubungi untuk keperluan penting terkait seleksi dan administrasi.</span></div>
                        </div>}

                        {step === 2 && <div className="adm-form-grid">
                            <div className="adm-field span-2"><label>Asal Sekolah (SMA/SMK/MA) <em>*</em></label><input className="adm-input" value={data.highSchoolName} onChange={(event) => form.setData('highSchoolName', event.target.value)} placeholder="Contoh: SMAN 1 Jakarta" />{showHint('highSchoolName')}{err(backendErrors.highSchoolName)}</div>
                            <div className="adm-field"><label>Jurusan Asal <em>*</em></label><input className="adm-input" value={data.highSchoolMajor} onChange={(event) => form.setData('highSchoolMajor', event.target.value)} placeholder="Contoh: IPA / Rekayasa Perangkat Lunak" />{showHint('highSchoolMajor')}{err(backendErrors.highSchoolMajor)}</div>
                            <div className="adm-field"><label>Tahun Lulus <em>*</em></label><input type="number" min={1980} max={maxYear} className="adm-input" value={data.highSchoolGraduationYear} onChange={(event) => form.setData('highSchoolGraduationYear', event.target.value)} placeholder={String(maxYear - 1)} />{showHint('highSchoolGraduationYear', `Isi tahun lulus 1980–${maxYear}.`)}{err(backendErrors.highSchoolGraduationYear)}</div>
                        </div>}

                        {step === 3 && <div className="adm-form-grid">
                            <div className="adm-field"><label>Fakultas Pilihan</label><select className="adm-input" value={data.facultyId} onChange={(event) => form.setData({ ...data, facultyId: event.target.value, studyProgramId: '' })}><option value="">Semua fakultas</option>{faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name}</option>)}</select>{err(backendErrors.facultyId)}</div>
                            <div className="adm-field"><label>Program Studi / Jurusan</label><select className="adm-input" value={data.studyProgramId} onChange={(event) => form.setData('studyProgramId', event.target.value)}><option value="">— Pilih Program Studi —</option>{availablePrograms.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}</select>{err(backendErrors.studyProgramId)}</div>
                            <div className="adm-field span-2"><label>Pilih Kelas Tujuan</label><div className="adm-pick-grid" role="radiogroup" aria-label="Kelas tujuan">
                                {classTypes.map(({ value, label, desc, icon: Icon }) => <button key={value} type="button" role="radio" aria-checked={data.classType === value} className={`adm-pick${data.classType === value ? ' selected' : ''}`} onClick={() => form.setData('classType', value)}><Icon size={20} /><b>{label}</b><small>{desc}</small></button>)}
                            </div>{err(backendErrors.classType)}</div>
                            {chosenProgram && <div className="adm-note green span-2" style={{ gridColumn: '1 / -1' }}><CheckCircle2 size={15} /> <span>Pilihan Anda: <b>{chosenProgram.name}</b>{chosenFaculty ? ` — ${chosenFaculty.name}` : ''} · Kelas {classLabel}.</span></div>}
                        </div>}

                        {step === 4 && <div className="adm-stack">
                            <div className="adm-note"><ClipboardList size={15} /> <span><b>{uploadedRequired} dari {requiredDocs.length}</b> berkas wajib sudah terunggah. Format dan ukuran maksimum tertulis di tiap kartu.</span></div>
                            <div className="adm-grid adm-cols-2">
                                {requirements.map((requirement) => {
                                    const picked = fileName(requirement.documentType);
                                    const showDocHint = docsAttempted && requirement.isRequired && !picked;
                                    return <div className={`adm-doc${picked ? ' ok' : requirement.isRequired ? ' bad' : ''}`} key={requirement.id}>
                                        <div className="adm-doc-top"><h4>{requirement.label}</h4><span className={`adm-badge ${requirement.isRequired ? 'red' : 'gray'}`}>{requirement.isRequired ? 'Wajib' : 'Opsional'}</span></div>
                                        <span className="adm-hint">Maks. {requirement.maxSizeKb} KB · Format: {requirement.allowedExtensions}</span>
                                        <div className="adm-upload"><label className="adm-file-label"><FolderOpen size={14} />{picked || 'Pilih berkas untuk diunggah…'}<input type="file" accept={requirement.allowedExtensions.split(',').map((extension) => '.' + extension.trim().toLowerCase()).join(',')} onChange={(event) => pickDocument(requirement, event.target.files?.[0] ?? null)} /></label></div>
                                        {picked && <button type="button" className="adm-review-edit" onClick={() => pickDocument(requirement, null)}>Hapus berkas</button>}
                                        {showDocHint && <span className="adm-error">Berkas ini wajib diunggah.</span>}
                                        {err(backendErrors[`documents.${requirement.documentType}`])}
                                    </div>;
                                })}
                            </div>
                        </div>}

                        {step === 5 && <div className="adm-stack">
                            {!!backendErrors && Object.keys(backendErrors).length > 0 && <div className="adm-note" style={{ borderColor: 'var(--adm-red)', color: 'var(--adm-red)' }}><ClipboardList size={15} /> <span>Ada isian yang perlu diperbaiki — periksa pesan merah di bawah lalu kirim ulang.</span></div>}
                            <div className="adm-review">
                                <div className="adm-review-group"><h4>Data Diri <button type="button" className="adm-review-edit" onClick={() => goTo(0)}><Pencil size={12} /> Ubah</button></h4>
                                    <div className="adm-review-row"><small>Nama lengkap</small><b>{data.fullName || '—'}</b></div>
                                    <div className="adm-review-row"><small>Email</small><b>{data.email || '—'}</b></div>
                                    <div className="adm-review-row"><small>No. HP</small><b>{data.phone || '—'}</b></div>
                                    <div className="adm-review-row"><small>Tanggal lahir</small><b>{data.birthDate || '—'}</b></div>
                                    <div className="adm-review-row"><small>Jenis kelamin</small><b>{genderLabel}</b></div>
                                </div>
                                <div className="adm-review-group"><h4>Kontak & Sekolah <button type="button" className="adm-review-edit" onClick={() => goTo(1)}><Pencil size={12} /> Ubah</button></h4>
                                    <div className="adm-review-row"><small>Alamat</small><b>{data.address || '—'}</b></div>
                                    <div className="adm-review-row"><small>Kontak darurat</small><b>{data.emergencyContactName ? `${data.emergencyContactName} (${data.emergencyContactPhone || '—'})` : '—'}</b></div>
                                    <div className="adm-review-row"><small>Sekolah</small><b>{data.highSchoolName ? `${data.highSchoolName} · ${data.highSchoolMajor} · Lulus ${data.highSchoolGraduationYear}` : '—'}</b></div>
                                </div>
                                <div className="adm-review-group"><h4>Program Studi <button type="button" className="adm-review-edit" onClick={() => goTo(3)}><Pencil size={12} /> Ubah</button></h4>
                                    <div className="adm-review-row"><small>Fakultas</small><b>{facultyLabel}</b></div>
                                    <div className="adm-review-row"><small>Program studi</small><b>{programLabel}</b></div>
                                    <div className="adm-review-row"><small>Kelas</small><b>{classLabel}</b></div>
                                </div>
                                <div className="adm-review-group"><h4>Berkas ({uploadedRequired}/{requirements.length}) <button type="button" className="adm-review-edit" onClick={() => goTo(4)}><Pencil size={12} /> Ubah</button></h4>
                                    {requirements.map((requirement) => <div className="adm-review-row" key={requirement.id}><small>{requirement.label}{requirement.isRequired ? ' *' : ''}</small><b>{fileName(requirement.documentType) || '—'}</b></div>)}
                                </div>
                            </div>
                            <div className="adm-note green"><ShieldCheck size={15} /> <span>Dengan menekan tombol kirim, Anda menyatakan seluruh data di atas benar. Nomor pendaftaran & tautan portal dikirim ke email Anda.</span></div>
                        </div>}

                        <div className="adm-wz-nav">
                            <button type="button" className="adm-btn ghost" disabled={step === 0} onClick={() => goTo(step - 1)}><ArrowLeft size={15} /> Kembali</button>
                            {step < steps.length - 1
                                ? <button type="button" className="adm-btn primary" onClick={next}>Lanjut: {steps[step + 1].label} <ArrowRight size={15} /></button>
                                : <button type="submit" className="adm-btn primary" disabled={form.processing}><Send size={15} />{form.processing ? 'Mengunggah & Memproses…' : 'Kirim Pendaftaran'}</button>}
                        </div>
                    </form>
                </div>
            </div>

            <p className="adm-wz-trust"><ShieldCheck size={13} /> Data terenkripsi & hanya dipakai untuk seleksi · Sudah mendaftar? <a href="/admission/status">Cek status di sini</a></p>
        </div>}
    </AdmissionShell>;
}
