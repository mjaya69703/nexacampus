// Public admission requirements page.
import { ArrowRight, CalendarDays, CheckCircle2, ClipboardList, FileText, FolderOpen, GraduationCap, HelpCircle, IdCard, Mail, Send, UserCheck } from 'lucide-react';
import { LucideIcon } from 'lucide-react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';

type PeriodRequirement = { label: string; isRequired: boolean };
type Period = { id: number; name: string; code: string; description: string | null; opensAt: string | null; closesAt: string | null; isActive: boolean; requirements: PeriodRequirement[] };
type Props = AdmissionBaseProps & { stats: { openPeriods: number; totalPrograms: number }; periods: Period[] };

type Document = { icon: LucideIcon; label: string; desc: string };
const documents: Document[] = [
    { icon: IdCard, label: 'Kartu identitas', desc: 'KTP, kartu pelajar, atau akta kelahiran.' },
    { icon: GraduationCap, label: 'Ijazah / SKL', desc: 'Ijazah asli atau Surat Keterangan Lulus.' },
    { icon: FileText, label: 'Nilai rapor', desc: 'Semester 1–5, sudah dilegalisir.' },
    { icon: UserCheck, label: 'Pas foto terbaru', desc: 'Berwarna, latar merah/biru, ukuran 3×4.' },
    { icon: FolderOpen, label: 'Kartu keluarga', desc: 'KK yang masih berlaku dan terbaca jelas.' },
    { icon: Mail, label: 'Email & nomor HP', desc: 'Kontak aktif untuk verifikasi pendaftaran.' },
];

const journey = [
    { num: '01', title: 'Isi formulir', desc: 'Masukkan data diri dan riwayat pendidikan.' },
    { num: '02', title: 'Pilih program studi', desc: 'Tentukan pilihan program dan jalur masuk.' },
    { num: '03', title: 'Unggah berkas', desc: 'Kirim dokumen dalam format yang diminta.' },
    { num: '04', title: 'Pantau hasil', desc: 'Simpan nomor registrasi untuk cek status.' },
];

const dateRange = (period: Period) => period.opensAt && period.closesAt
    ? `${period.opensAt} – ${period.closesAt}`
    : period.opensAt ?? period.closesAt ?? 'Jadwal belum ditentukan';

export default function Requirements({ campus, links, user, stats, periods }: Props) {
    const activePeriod = periods.find((period) => period.isActive) ?? periods[0] ?? null;

    return <AdmissionShell campus={campus} links={links} user={user} activeTab="Syarat" eyebrow="Jalur & Syarat" title="Semua yang perlu disiapkan sebelum mendaftar." description="Panduan ringkas untuk menyiapkan berkas, memilih jalur, dan menyelesaikan pendaftaran mahasiswa baru tanpa langkah yang membingungkan." icon={ClipboardList}
        action={<div className="adm-hero-cta"><a className="adm-btn light" href="/admission/apply"><Send size={14} /> Mulai Pendaftaran</a></div>}>
        <div className="adm-requirements-redesign">
            {activePeriod && <section className="adm-intake-feature">
                <div className="adm-intake-feature-copy"><span className="adm-feature-eyebrow"><span /> Gelombang pendaftaran {activePeriod.isActive ? 'sedang dibuka' : 'terbaru'}</span><h2>{activePeriod.name}</h2><p>{activePeriod.description ?? 'Lengkapi persyaratan dan mulai proses pendaftaran sesuai jadwal yang tersedia.'}</p><div className="adm-feature-meta"><span><CalendarDays size={14} /> {dateRange(activePeriod)}</span><span><ClipboardList size={14} /> {activePeriod.requirements.length} berkas tambahan</span><span><GraduationCap size={14} /> {stats.totalPrograms} program studi</span></div></div>
                <div className="adm-intake-feature-action"><span className="adm-feature-code">{activePeriod.code}</span><a className="adm-btn light" href="/admission/apply">Daftar sekarang <ArrowRight size={15} /></a><small>Online, bisa diakses 24 jam</small></div>
            </section>}

            <section className="adm-guide-grid">
                <article className="adm-guide-panel adm-documents-panel">
                    <div className="adm-guide-heading"><div><span className="adm-feature-eyebrow dark"><span /> Checklist awal</span><h2>Siapkan 6 dokumen ini.</h2><p>Semua berkas dasar di bawah berlaku untuk seluruh jalur masuk.</p></div><strong>01—06</strong></div>
                    <div className="adm-document-list">{documents.map((document, index) => { const DocumentIcon = document.icon; return <div className="adm-document-row" key={document.label}><span className="adm-document-number">{String(index + 1).padStart(2, '0')}</span><span className="adm-document-icon"><DocumentIcon size={18} /></span><div><h3>{document.label}</h3><p>{document.desc}</p></div><CheckCircle2 className="adm-document-check" size={17} /></div>; })}</div>
                    <div className="adm-guide-foot"><span><CheckCircle2 size={15} /> Format PDF atau JPG</span><span><CheckCircle2 size={15} /> Pastikan file terbaca</span></div>
                </article>

                <article className="adm-guide-panel adm-journey-panel">
                    <div className="adm-guide-heading"><div><span className="adm-feature-eyebrow dark"><span /> Alur pendaftaran</span><h2>Dari mulai sampai selesai.</h2><p>Empat langkah sederhana untuk mengirim pendaftaranmu.</p></div><strong>04</strong></div>
                    <div className="adm-journey-list">{journey.map((step) => <div className="adm-journey-row" key={step.num}><span className="adm-journey-number">{step.num}</span><div><h3>{step.title}</h3><p>{step.desc}</p></div></div>)}</div>
                    <div className="adm-journey-callout"><span><strong>{stats.openPeriods || '—'}</strong> gelombang aktif</span><a href="/admission/status">Sudah mendaftar? Cek status <ArrowRight size={14} /></a></div>
                </article>
            </section>

            <section className="adm-prep-strip"><div className="adm-prep-lead"><span className="adm-feature-eyebrow dark"><span /> Sebelum klik kirim</span><h2>Hal kecil yang sering terlupa.</h2></div><div className="adm-prep-points"><span><CheckCircle2 size={16} /> Nama dan tanggal lahir harus konsisten.</span><span><CheckCircle2 size={16} /> Email aktif untuk menerima nomor registrasi.</span><span><CheckCircle2 size={16} /> Scan dokumen tidak terpotong atau buram.</span></div></section>

            {periods.length > 0 && <section className="adm-special-section"><div className="adm-section-heading-new"><div><span className="adm-feature-eyebrow dark"><span /> Pilihan jalur</span><h2>Persyaratan tambahan per gelombang.</h2><p>Cek apakah jalur yang kamu pilih membutuhkan dokumen tambahan.</p></div><span className="adm-section-count">{periods.length} gelombang</span></div><div className="adm-special-list">{periods.map((period) => <article className={`adm-special-row ${period.isActive ? 'active' : ''}`} key={period.id}><div className="adm-special-name"><span className="adm-special-code">{period.code}</span><h3>{period.name}</h3>{period.isActive && <span className="adm-special-status">Sedang dibuka</span>}</div><div className="adm-special-date"><CalendarDays size={14} /> {dateRange(period)}</div><div className="adm-special-docs">{period.requirements.length > 0 ? period.requirements.map((requirement, index) => <span key={`${period.id}-${index}`}><CheckCircle2 size={13} /> {requirement.label}{!requirement.isRequired && <em>opsional</em>}</span>) : <span className="adm-special-empty">Tidak ada dokumen tambahan</span>}</div></article>)}</div></section>}

            <section className="adm-final-guide"><div><span className="adm-feature-eyebrow"><span /> Langkah berikutnya</span><h2>Sudah siap mulai?</h2><p>Siapkan dokumenmu, pilih jalur yang sesuai, dan selesaikan pendaftaran hari ini.</p></div><div><a className="adm-btn light" href="/admission/apply">Mulai Pendaftaran <ArrowRight size={15} /></a><a className="adm-btn outline" href="/admission/faq"><HelpCircle size={15} /> Baca FAQ</a></div></section>
        </div>
    </AdmissionShell>;
}
