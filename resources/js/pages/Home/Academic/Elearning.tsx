// Public academic e-learning page.
import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, CheckCircle2, CloudUpload, FileText, Laptop, MessageCircle, PlayCircle } from 'lucide-react';
import { AcademicBaseProps, AcademicShell, AcademicStat } from '../../../components/Home/Academic/AcademicShell';

type Props = AcademicBaseProps & { stats: { activeOfferings: number; materials: number; assignments: number } };
const features = [
    { icon: BookOpen, title: 'Materi terstruktur', text: 'Modul, slide, dan referensi perkuliahan tersusun mengikuti mata kuliah.' },
    { icon: CloudUpload, title: 'Pengumpulan tugas', text: 'Kumpulkan tugas secara online dengan status dan tenggat yang jelas.' },
    { icon: MessageCircle, title: 'Ruang diskusi', text: 'Bangun percakapan akademik dengan dosen dan rekan satu kelas.' },
    { icon: Laptop, title: 'Ujian daring', text: 'Ikuti evaluasi pembelajaran melalui ruang ujian digital yang terarah.' },
];

export default function Elearning({ campus, links, user, stats }: Props) {
    return <AcademicShell campus={campus} links={links} user={user} activeTab="E-Learning" eyebrow="E-learning" title="Ruang belajar yang ikut bergerak bersama Anda." description="Akses materi, tugas, dan percakapan pembelajaran melalui portal digital kampus." icon={Laptop} action={user ? <a href={user.dashboardUrl} className="adm-btn light">Buka portal saya <ArrowRight size={15} /></a> : <Link href={links.login} className="adm-btn light">Login ke e-learning <ArrowRight size={15} /></Link>}>
        <div className="elearning-hero"><div><span className="hero-kicker">BELAJAR TANPA BATAS RUANG</span><h2>Materi kuliah, tugas, dan diskusi dalam satu ruang.</h2><p>Gunakan portal e-learning untuk melanjutkan proses belajar setelah kelas berakhir—lebih teratur, lebih mudah dilacak.</p><div className="elearning-actions">{user ? <a href={user.dashboardUrl} className="campus-button campus-button-light">Masuk ke portal <ArrowRight size={15} /></a> : <Link href={links.login} className="campus-button campus-button-light">Masuk ke portal <ArrowRight size={15} /></Link>}<Link href="/kontak" className="hero-text-link">Butuh bantuan? <ArrowRight size={15} /></Link></div></div><div className="elearning-visual"><div className="elearning-stat"><strong>{stats.activeOfferings}</strong><span>Kelas aktif</span></div><div className="elearning-stat"><strong>{stats.materials}</strong><span>Materi tersedia</span></div><div className="elearning-stat"><strong>{stats.assignments}</strong><span>Tugas online</span></div><div className="elearning-stat"><PlayCircle size={23} color="#e0b669" /><span>Belajar mandiri</span></div></div></div>
        <div className="academic-overview"><div><h2>Yang tersedia di dalamnya</h2><p>Fitur pembelajaran digital yang mendukung perkuliahan Anda.</p></div><div className="academic-stats"><AcademicStat value={stats.materials} label="Materi" /><AcademicStat value={stats.assignments} label="Tugas" /></div></div>
        <div className="learning-feature-grid">{features.map(({ icon: Icon, title, text }) => <article className="learning-feature" key={title}><span className="learning-feature-icon"><Icon size={19} /></span><h3>{title}</h3><p>{text}</p></article>)}</div>
        <div className="learning-help"><FileText size={20} /><span><strong>Perlu bantuan teknis?</strong><span>Hubungi helpdesk melalui halaman kontak jika mengalami kendala login atau mengakses materi.</span></span><Link href={links.contact} className="academic-link">Buka kontak <ArrowRight size={14} /></Link></div>
    </AcademicShell>;
}
