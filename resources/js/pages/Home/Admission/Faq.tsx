// Public admission FAQ page.
import { BadgeCheck, ChevronDown, Coins, FileText, HelpCircle, Search, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdmissionBaseProps, AdmissionShell } from '../../../components/Home/Admission/AdmissionShell';
import { EmptyState } from '../../../components/Shared/EmptyState';

type Faq = { id: number; category: string; question: string; answer: string };
type Props = AdmissionBaseProps & { faqs: Faq[]; categories: string[] };

const categoryIcon = (category: string) => {
    const key = category.toLowerCase();
    if (key.includes('biaya')) return Coins;
    if (key.includes('seleksi')) return BadgeCheck;
    if (key.includes('dokumen') || key.includes('berkas')) return FileText;
    return HelpCircle;
};

const fallbackFaqs: Faq[] = [
    { id: -1, category: 'Pendaftaran', question: 'Bagaimana cara melakukan pendaftaran secara online?', answer: 'Akses menu "Daftar Sekarang" di bagian atas halaman ini. Isi formulir biodata awal, lalu sistem akan mengirimkan Nomor Pendaftaran dan password sementara ke email yang Anda daftarkan. Gunakan kredensial tersebut untuk masuk ke Portal Pendaftar dan melengkapi sisa persyaratan secara bertahap.' },
    { id: -2, category: 'Pendaftaran', question: 'Apakah saya bisa mendaftar lebih dari satu program studi?', answer: 'Ya, setiap calon mahasiswa diperbolehkan memilih maksimal 3 (tiga) pilihan program studi sesuai skala prioritas. Proses seleksi dimulai dari pilihan pertama, dan apabila tidak lolos akan dipertimbangkan ke pilihan berikutnya sesuai ketersediaan kuota.' },
    { id: -3, category: 'Biaya', question: 'Berapa biaya pendaftaran PMB NexaCampus?', answer: 'Biaya pendaftaran adalah sebesar Rp 250.000,- untuk semua jalur masuk. Pembayaran dapat dilakukan melalui Virtual Account Bank yang bekerja sama dengan kampus. Biaya pendaftaran tidak dapat dikembalikan setelah berhasil dibayar, namun Anda bisa langsung mengakses portal pendaftaran.' },
    { id: -4, category: 'Seleksi', question: 'Kapan pengumuman hasil seleksi diumumkan?', answer: 'Pengumuman hasil seleksi diumumkan 7 hari kerja setelah masa pendaftaran gelombang tersebut ditutup, atau 3 hari kerja pasca pelaksanaan tes mandiri (CBT) di kampus. Anda dapat memantau status pendaftaran secara real-time melalui menu "Cek Status Pendaftaran".' },
    { id: -5, category: 'Dokumen', question: 'Dokumen apa saja yang wajib disiapkan?', answer: 'Dokumen umum yang wajib disiapkan oleh semua jalur antara lain:\n• Scan Ijazah/SKL yang dilegalisir\n• Scan Nilai Rapor Semester 1–5\n• Pas foto berwarna terbaru (3×4, latar merah/biru)\n• Fotokopi KTP/Kartu Pelajar\n• Scan Kartu Keluarga\n\nPersyaratan tambahan bergantung pada jalur masuk yang Anda pilih.' },
    { id: -6, category: 'Seleksi', question: 'Apakah ada tes tulis / tes masuk?', answer: 'Tergantung jalur masuk yang Anda pilih. Jalur Prestasi umumnya tidak memerlukan tes tulis karena seleksi berbasis nilai rapor dan sertifikat prestasi. Jalur Ujian Tulis mengharuskan Anda mengikuti Computer Based Test (CBT) di kampus pada jadwal yang ditentukan.' },
    { id: -7, category: 'Biaya', question: 'Apakah ada beasiswa atau keringanan biaya?', answer: 'Ya! Tersedia beberapa skema bantuan biaya pendidikan, di antaranya:\n• KIP Kuliah — pembebasan biaya penuh dari pemerintah\n• Beasiswa Prestasi — untuk mahasiswa berprestasi akademik/non-akademik\n• Cicilan — program cicilan pembayaran UKT untuk kesulitan ekonomi' },
    { id: -8, category: 'Pendaftaran', question: 'Berapa lama proses verifikasi berkas berlangsung?', answer: 'Proses verifikasi berkas umumnya berlangsung 3–5 hari kerja setelah semua dokumen diterima secara lengkap. Anda akan mendapatkan notifikasi email jika terdapat kekurangan berkas atau jika verifikasi telah selesai.' },
];

const stripTags = (html: string): string => html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

export default function Faq({ campus, links, user, faqs, categories }: Props) {
    const [search, setSearch] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
    const [openId, setOpenId] = useState<number | null>(null);

    const visibleFaqs = useMemo(() => {
        const term = search.trim().toLowerCase();
        let items = faqs.length ? faqs : fallbackFaqs;

        if (selectedCategory) items = items.filter((faq) => faq.category === selectedCategory);
        if (term) items = items.filter((faq) => `${faq.question} ${stripTags(faq.answer)} ${faq.category}`.toLowerCase().includes(term));

        return items;
    }, [faqs, search, selectedCategory]);

    const visibleCategories = useMemo(() => Array.from(new Set(visibleFaqs.map((faq) => faq.category))), [visibleFaqs]);
    const groupedFaqs = useMemo(() => visibleCategories.map((category) => ({
        category,
        items: visibleFaqs.filter((faq) => faq.category === category),
    })), [visibleCategories, visibleFaqs]);

    const resetFilters = () => { setSearch(''); setSelectedCategory(null); };

    return <AdmissionShell campus={campus} links={links} user={user} activeTab="FAQ" eyebrow="FAQ Penerimaan" title="Pertanyaan yang sering ditanyakan." description="Temukan jawaban atas semua pertanyaan seputar proses penerimaan mahasiswa baru. Tidak menemukan jawaban? Hubungi tim kami langsung." icon={HelpCircle}
        action={<div className="adm-hero-cta"><a className="adm-btn light" href="/admission/apply"><Send size={14} /> Daftar Sekarang</a></div>}>

        <section className="adm-card">
            <div className="adm-card-head">
                <h3 className="adm-card-title">Cari &amp; Filter Pertanyaan</h3>
                {(selectedCategory || search) && <button type="button" className="adm-btn ghost sm" onClick={resetFilters}>Reset Filter</button>}
            </div>
            <div className="adm-card-body tight" style={{ display: 'grid', gap: 14 }}>
                <div style={{ position: 'relative' }}>
                    <Search size={16} style={{ position: 'absolute', left: 14, top: '50%', transform: 'translateY(-50%)', color: 'var(--adm-muted)' }} />
                    <input className="adm-input" style={{ paddingLeft: 40, borderRadius: 999 }} value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari pertanyaan PMB…" aria-label="Cari pertanyaan" />
                </div>
                <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8 }}>
                    {(faqs.length ? categories : Array.from(new Set(fallbackFaqs.map((faq) => faq.category)))).map((category) => {
                        const count = visibleFaqs.filter((faq) => faq.category === category).length;
                        return <button type="button" key={category} className={`adm-chip${selectedCategory === category ? ' active' : ''}`} onClick={() => setSelectedCategory(selectedCategory === category ? null : category)}>
                            {category}{count > 0 && <b>{count}</b>}
                        </button>;
                    })}
                </div>
            </div>
        </section>

        {visibleFaqs.length === 0
            ? <EmptyState
                icon={Search}
                iconSize={26}
                title="Pertanyaan Tidak Ditemukan"
                description="Tidak ada FAQ PMB yang sesuai dengan kriteria pencarian Anda."
                action={<button type="button" className="adm-btn primary sm" style={{ marginTop: 8 }} onClick={resetFilters}>Reset Pencarian</button>}
            />
            : groupedFaqs.map(({ category, items }) => <section key={category}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                    <span className="adm-kicker"><span className="adm-faq-caticon">{(() => { const Icon = categoryIcon(category); return <Icon size={14} />; })()}</span> {category}</span>
                    <span style={{ flex: 1, height: 1, background: 'var(--adm-line)' }} />
                    <span className="adm-badge gray">{items.length} Pertanyaan</span>
                </div>
                <div className="adm-faq">
                    {items.map((faq, itemIndex) => {
                        const open = openId === faq.id;
                        return <div className={`adm-faq-item${open ? ' open' : ''}`} key={faq.id}>
                            <button type="button" className="adm-faq-q" onClick={() => setOpenId(open ? null : faq.id)} aria-expanded={open}>
                                <span className="adm-faq-num">{String(itemIndex + 1).padStart(2, '0')}</span>
                                {faq.question}<ChevronDown size={16} />
                            </button>
                            <div className="adm-faq-a"><div className="adm-faq-html" dangerouslySetInnerHTML={{ __html: faq.answer }} /></div>
                        </div>;
                    })}
                </div>
            </section>)}

        <section className="adm-card" style={{ border: 0, background: 'linear-gradient(125deg, var(--adm-brand-deep), var(--adm-brand))' }}>
            <div style={{ display: 'grid', justifyItems: 'center', gap: 6, padding: '36px 24px', textAlign: 'center' }}>
                <span style={{ display: 'grid', placeItems: 'center', width: 54, height: 54, marginBottom: 8, borderRadius: 17, color: '#fff', background: 'rgba(255,255,255,.12)', border: '1px solid rgba(255,255,255,.22)' }}><HelpCircle size={24} /></span>
                <h2 style={{ margin: 0, color: '#fff', font: "700 23px/1.3 'Source Serif 4',Georgia,serif" }}>Masih Ada Pertanyaan?</h2>
                <p style={{ maxWidth: 440, margin: '0 0 14px', color: 'rgba(255,255,255,.75)', fontSize: 12.5 }}>Tim penerimaan kami siap membantu Anda melalui berbagai saluran komunikasi yang tersedia.</p>
                <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'center', gap: 10 }}>
                    <a className="adm-btn light" href="/admission/apply"><Send size={14} /> Mulai Pendaftaran</a>
                    <a className="adm-btn outline" href="/admission/status"><Search size={14} /> Cek Status Pendaftaran</a>
                </div>
            </div>
        </section>
    </AdmissionShell>;
}
