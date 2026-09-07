// Public admission status check.
import { useForm } from '@inertiajs/react';
import { CalendarCheck, ClipboardList, GraduationCap, Hourglass, Inbox, Search, ShieldCheck, UserCheck } from 'lucide-react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';
import { StatStrip } from '../../../components/Shared/StatStrip';

type Props = AdmissionBaseProps & { stats: { openIntakes: number; studyPrograms: number; submitted: number; underReview: number } };

const preparations = [
    { title: 'Siapkan Nomor Pendaftaran', desc: 'Tercantum pada tanda terima atau email' },
    { title: 'Gunakan Email yang Sama', desc: 'Penulisan huruf kecil/besar harus sesuai' },
    { title: 'Re-upload Dokumen', desc: 'Di dalam portal Anda dapat mengunggah ulang berkas' },
];

export default function Status({ campus, links, user, stats }: Props) {
    const form = useForm({ applicationNumber: '', email: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/admission/status');
    };

    return <AdmissionShell campus={campus} links={links} user={user} activeTab="Cek Status" eyebrow="Cek Status" title="Pantau progres pendaftaran & jadwal Anda." description="Masukkan nomor pendaftaran dan email untuk membuka portal pendaftar dan melihat progres seleksi secara real-time." icon={Search}>
        <StatStrip
            items={[
                { icon: CalendarCheck, value: stats.openIntakes, label: 'Gelombang Aktif' },
                { icon: GraduationCap, value: stats.studyPrograms, label: 'Program Studi', tone: 'green' },
                { icon: Inbox, value: stats.submitted, label: 'Total Pendaftar' },
                { icon: Hourglass, value: stats.underReview, label: 'Sedang Diperiksa', tone: 'gold' },
            ]}
        />
        <div className="adm-layout">
            <section className="adm-card">
                <div className="adm-card-head">
                    <h3 className="adm-card-title">Lacak Status & Masuk Portal</h3>
                    <span className="adm-badge solid"><ShieldCheck size={12} /> Aman &amp; Terenkripsi</span>
                </div>
                <div className="adm-card-body">
                    <form style={{ display: 'grid', gap: 18 }} onSubmit={submit}>
                        <p className="adm-note"><ClipboardList size={17} /> <span>Nomor pendaftaran (misal: <b>ADM2026W1-0001</b>) dikirimkan melalui email saat pendaftaran berhasil. Kombinasi nomor + email membuka portal pendaftar Anda.</span></p>
                        <div className="adm-form-grid">
                            <div className="adm-field">
                                <label>Nomor Pendaftaran <em>*</em></label>
                                <input className="adm-input" style={{ padding: '14px 16px', fontSize: 15 }} value={form.data.applicationNumber} onChange={(event) => form.setData('applicationNumber', event.target.value)} placeholder="Contoh: ADM2026W1-0001" />
                                {form.errors.applicationNumber && <span className="adm-error">{form.errors.applicationNumber}</span>}
                            </div>
                            <div className="adm-field">
                                <label>Alamat Email Terdaftar <em>*</em></label>
                                <input type="email" className="adm-input" style={{ padding: '14px 16px', fontSize: 15 }} value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} placeholder="nama@email.com" />
                                {form.errors.email && <span className="adm-error">{form.errors.email}</span>}
                            </div>
                        </div>
                        <button type="submit" className="adm-btn primary block" disabled={form.processing}><UserCheck size={16} />{form.processing ? 'Mencari data…' : 'Periksa Status & Buka Portal'}</button>
                    </form>
                </div>
            </section>
            <aside className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head"><h3 className="adm-card-title">Persiapan Sebelum Masuk</h3></div>
                    <div className="adm-card-body tight">
                        <div className="adm-steps">
                            {preparations.map((item, index) => <div className="adm-step" key={item.title}>
                                <span className="adm-step-num">{index + 1}</span>
                                <div><h3>{item.title}</h3><p>{item.desc}</p></div>
                            </div>)}
                        </div>
                    </div>
                </section>
                <div className="adm-note gold"><ShieldCheck size={17} /> <span><b style={{ color: 'var(--adm-heading)' }}>Data Anda terlindungi.</b> Portal pendaftar hanya dapat diakses dengan kombinasi nomor pendaftaran + email yang benar.</span></div>
            </aside>
        </div>
    </AdmissionShell>;
}
