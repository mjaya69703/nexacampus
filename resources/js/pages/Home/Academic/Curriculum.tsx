// Public academic curriculum page.
import { BookOpen, CheckCircle2, Layers3 } from 'lucide-react';
import { AcademicBaseProps, AcademicShell, AcademicStat } from '../../../components/Home/Academic/AcademicShell';

type CurriculumItem = { id: number; program: string; programCode: string | null; name: string; code: string | null; startYear: number | null; endYear: number | null; description: string | null; courseCount: number; credits: number };
type Props = AcademicBaseProps & { curriculums: CurriculumItem[] };

export default function Curriculum({ campus, links, user, curriculums }: Props) {
    return <AcademicShell campus={campus} links={links} user={user} activeTab="Kurikulum" eyebrow="Kurikulum" title="Pahami peta belajar sebelum melangkah." description="Kurikulum aktif membantu Anda melihat struktur pembelajaran, beban studi, dan arah kompetensi setiap program studi." icon={Layers3}>
        <div className="academic-overview"><div><h2>Kurikulum yang sedang berlaku</h2><p>Dokumen pembelajaran yang telah diterbitkan untuk program studi aktif di {campus.name}.</p></div><div className="academic-stats"><AcademicStat value={curriculums.length} label="Kurikulum aktif" /><AcademicStat value={curriculums.reduce((total, item) => total + item.courseCount, 0)} label="Mata kuliah" /></div></div>
        {curriculums.length ? <div className="curriculum-grid">{curriculums.map((item) => <article className="curriculum-card" key={item.id}><div className="curriculum-card-top"><span className="curriculum-active"><CheckCircle2 size={11} /> Aktif</span><span className="curriculum-year">{item.startYear ?? '—'}{item.endYear ? `–${item.endYear}` : ''}</span></div><h3>{item.name}</h3><p>{item.description ?? `Peta mata kuliah dan kompetensi untuk program ${item.program}.`}</p><div className="curriculum-metrics"><span><small>Program</small><strong>{item.programCode ?? item.program}</strong></span><span><small>Mata kuliah</small><strong>{item.courseCount}</strong></span><span><small>Beban studi</small><strong>{item.credits} SKS</strong></span></div></article>)}</div> : <div className="academic-panel academic-empty"><span className="academic-empty-icon"><BookOpen size={25} /></span><h3>Kurikulum belum tersedia</h3><p>Dokumen kurikulum aktif belum dipublikasikan oleh bagian akademik.</p></div>}
        <div className="schedule-note"><BookOpen size={15} /> <span>Informasi kurikulum dapat diperbarui mengikuti evaluasi akademik dan kebutuhan kompetensi program studi.</span></div>
    </AcademicShell>;
}
