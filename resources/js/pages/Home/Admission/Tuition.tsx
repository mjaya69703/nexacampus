// Public admission tuition (UKT) page.
import { ArrowRight, BadgeCheck, CalendarDays, Clock3, Coins, GraduationCap, Send, Star } from 'lucide-react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type Tuition = { programName: string; programCode: string; facultyName: string; baseFee: number; labFee: number; libraryFee: number; activityFee: number; total: number; deadline: string };
type Props = AdmissionBaseProps & { tuitions: Tuition[]; lowestTotal: number | null };

const rupiah = (value: number) => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
const juta = (value: number | null) => value === null ? '-' : 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(value / 1_000_000) + ' jt';

const schemes = [
    { icon: GraduationCap, tone: 'green', title: 'KIP Kuliah', desc: 'Penerima KIP Kuliah mendapatkan pembebasan biaya pendidikan penuh sesuai ketentuan pemerintah.' },
    { icon: Coins, tone: '', title: 'Cicilan & Keringanan', desc: 'Mahasiswa yang mengalami kesulitan ekonomi dapat mengajukan cicilan pembayaran atau keringanan biaya.' },
    { icon: Star, tone: 'gold', title: 'Beasiswa Prestasi', desc: 'Tersedia beasiswa prestasi akademik dan non-akademik yang dapat mengurangi beban biaya pendidikan.' },
];

const feeParts = (tuition: Tuition) => [
    { key: 'baseFee', label: 'Dasar', short: 'Dasar', value: tuition.baseFee, mix: 100 },
    { key: 'labFee', label: 'Laboratorium', short: 'Lab', value: tuition.labFee, mix: 62 },
    { key: 'libraryFee', label: 'Perpustakaan', short: 'Perpus', value: tuition.libraryFee, mix: 40 },
    { key: 'activityFee', label: 'Kegiatan', short: 'Kegiatan', value: tuition.activityFee, mix: 24 },
];

export default function Tuition({ campus, links, user, tuitions, lowestTotal }: Props) {
    return <AdmissionShell campus={campus} links={links} user={user} activeTab="Biaya UKT" eyebrow="Biaya Pendidikan" title="Biaya pendidikan yang terjangkau & transparan." description="Kami berkomitmen pada transparansi penuh. Berikut adalah rincian biaya pendidikan (UKT) lengkap per semester untuk seluruh program studi aktif." icon={Coins}
        action={<div className="adm-hero-cta"><a className="adm-btn light" href="/admission/apply"><Send size={14} /> Daftar Sekarang</a></div>}>
        <div className="adm-stack">
            {tuitions.length > 0 && <section className="adm-tuition-hero">
                <div>
                    <span className="adm-tuition-hero-eyebrow">Transparansi biaya · {tuitions.length} program studi</span>
                    <h2>Kuliah mulai <em>{juta(lowestTotal)}</em> per semester.</h2>
                    <p>Angka pasti per program studi tertera di bawah — memerinci setiap komponen, lengkap dengan batas waktu pembayaran.</p>
                </div>
                <div className="adm-tuition-hero-actions">
                    <a className="adm-btn light" href="/admission/apply"><Send size={14} /> Daftar Sekarang</a>
                    <a className="adm-btn outline" href="/beasiswa"><GraduationCap size={14} /> Lihat Beasiswa</a>
                </div>
            </section>}

            <div className="adm-layout adm-tuition-page">
            <div className="adm-stack">
                {tuitions.length > 0 ? <>
                    <section className="adm-card adm-tuition-card">
                        <div className="adm-card-head adm-tuition-card-head">
                            <div>
                                <span className="adm-kicker"><Coins size={14} /> Struktur biaya</span>
                                <h2 className="adm-card-title">Biaya kuliah per semester</h2>
                                <p className="adm-tuition-subtitle">{tuitions.length === 1 ? 'Rincian komponen biaya semester berjalan, lengkap dengan proporsi tiap komponen.' : 'Rincian komponen tiap program studi, per semester. Panjang bar menunjukkan proporsi tiap komponen.'}</p>
                            </div>
                            <span className="adm-badge green">{tuitions.length} program</span>
                        </div>
                        <div className="adm-card-body">
                            <div className="adm-tuition-list">
                                {tuitions.map((tuition, index) => {
                                    const parts = feeParts(tuition);
                                    return <article className="adm-tuition-item" key={`${tuition.programCode}-${index}`}>
                                    <div className="adm-tuition-item-head">
                                        <div className="adm-tuition-program">
                                            <span className="adm-tuition-index">{String(index + 1).padStart(2, '0')}</span>
                                            <div>
                                                <span className="adm-badge">{tuition.programCode}</span>
                                                <h3>{tuition.programName}</h3>
                                                <p>{tuition.facultyName}</p>
                                            </div>
                                        </div>
                                        <div className="adm-tuition-total">
                                            <span>Total per semester</span>
                                            <strong>{rupiah(tuition.total)}</strong>
                                        </div>
                                    </div>
                                    <div className="adm-feebar" role="img" aria-label={`Proporsi komponen biaya ${tuition.programName}`}>
                                        {parts.map((part) => <i key={part.key} title={`${part.label}: ${rupiah(part.value)}`} style={{ width: `${tuition.total > 0 ? (part.value / tuition.total) * 100 : 0}%`, background: `color-mix(in srgb, var(--adm-brand) ${part.mix}%, transparent)` }} />)}
                                    </div>
                                    <div className="adm-feebar-legend">
                                        {parts.map((part) => <span key={part.key}><i style={{ background: `color-mix(in srgb, var(--adm-brand) ${part.mix}%, transparent)` }} />{part.short}</span>)}
                                    </div>
                                    <div className="adm-tuition-fees">
                                        <div className="adm-tuition-fee"><span>Biaya dasar</span><strong>{rupiah(tuition.baseFee)}</strong></div>
                                        <div className="adm-tuition-fee"><span>Laboratorium</span><strong>{rupiah(tuition.labFee)}</strong></div>
                                        <div className="adm-tuition-fee"><span>Perpustakaan</span><strong>{rupiah(tuition.libraryFee)}</strong></div>
                                        <div className="adm-tuition-fee"><span>Kegiatan</span><strong>{rupiah(tuition.activityFee)}</strong></div>
                                    </div>
                                    <div className="adm-tuition-meta">
                                        <span><CalendarDays size={13} /> Jatuh tempo <b>{tuition.deadline}</b></span>
                                        <span><Clock3 size={13} /> Tagihan terbit per semester berjalan</span>
                                    </div>
                                </article>; })}
                            </div>
                        </div>
                    </section>

                    <div className="adm-note adm-tuition-note"><Clock3 size={16} /><div><b>Catatan pembayaran</b><span>Total di atas adalah estimasi tagihan per semester dan belum termasuk denda keterlambatan. Nominal dapat berubah sesuai keputusan lembaga.</span></div></div>
                </> : <EmptyState
                    icon={Coins}
                    title="Data Biaya Belum Tersedia"
                    description="Rincian biaya pendidikan akan segera dipublikasikan oleh tim keuangan. Hubungi bagian keuangan untuk informasi lebih lanjut."
                    action={<a className="adm-btn primary" href="/admission/faq">Lihat FAQ</a>}
                />}
            </div>

            <div className="adm-stack">
                {tuitions.length > 0 && <section className="adm-card">
                    <div className="adm-card-head"><div><span className="adm-kicker">Dukungan finansial</span><h2 className="adm-card-title">Keringanan & beasiswa</h2></div></div>
                    <div className="adm-card-body tight"><div className="adm-scheme-list">
                        {schemes.map((scheme) => { const SchemeIcon = scheme.icon; return <div className="adm-scheme" key={scheme.title}>
                            <span className={`adm-stat-icon ${scheme.tone}`}><SchemeIcon size={17} /></span>
                            <div><b className="adm-scheme-title">{scheme.title}</b><small className="adm-hint adm-scheme-desc">{scheme.desc}</small></div>
                        </div>; })}
                    </div>
                    <div className="adm-scheme-foot"><a href="/beasiswa">Jelajahi program beasiswa <ArrowRight size={13} /></a></div></div>
                </section>}

                <div className="adm-note gold adm-tuition-help"><BadgeCheck size={16} /><div><b>Butuh keringanan biaya?</b><span>Ajukan KIP Kuliah, cicilan, atau beasiswa prestasi setelah nomor registrasi Anda aktif.</span></div></div>
            </div>
            </div>
        </div>
    </AdmissionShell>;
}
