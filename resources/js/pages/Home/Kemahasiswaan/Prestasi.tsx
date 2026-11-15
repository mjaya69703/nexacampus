// Public student achievements (prestasi mahasiswa) page — development placeholder.
import { Head } from '@inertiajs/react';
import { Clock3, HeartPulse, Hourglass, Medal, Star, Trophy, Users, Wallet } from 'lucide-react';
import { CommunityBaseProps, KemahasiswaanShell } from '../../../components/Home/Community/CommunityShell';
import { StatStrip } from '../../../components/Shared/StatStrip';

const upcoming: Array<{ num: string; title: string; desc: string }> = [
    { num: '01', title: 'Galeri Medali', desc: 'Dokumentasi capaian lomba dan penghargaan mahasiswa di tingkat nasional maupun internasional.' },
    { num: '02', title: 'Profil Peraih Prestasi', desc: 'Kisah inspiratif para mahasiswa yang membanggakan nama kampus.' },
    { num: '03', title: 'Kalender Kompetisi', desc: 'Agenda kompetisi resmi yang dapat diikuti mahasiswa aktif sepanjang tahun.' },
];

export default function Prestasi({ campus, links, user }: CommunityBaseProps) {
    return <KemahasiswaanShell campus={campus} links={links} user={user} activeTab="Prestasi" eyebrow="Prestasi Mahasiswa" title="Prestasi & penghargaan mahasiswa." description="Mahasiswa NexaCampus terus mengukir prestasi gemilang di tingkat nasional maupun internasional. Jadilah bagian dari sejarah kami." icon={Trophy}>
        <Head title={`Prestasi Mahasiswa · ${campus.name}`} />

        <div className="adm-layout">
            <div className="adm-stack">
                <section className="adm-card">
                    <div className="adm-card-head">
                        <span className="adm-kicker"><Hourglass size={12} /> Status Pengembangan</span>
                        <span className="adm-badge amber">Segera Hadir</span>
                    </div>
                    <div className="adm-card-body" style={{ display: 'grid', justifyContent: 'center', textAlign: 'center' }}>
                        <span className="adm-stat-icon gold" style={{ width: 66, height: 66, borderRadius: 20, margin: '4px auto 0' }}><Trophy size={28} /></span>
                        <h2 style={{ margin: '14px 0 0', color: 'var(--adm-heading)', font: "700 19px/1.35 'Source Serif 4', Georgia, serif" }}>Halaman Sedang Dalam Pengembangan</h2>
                        <p className="adm-hint" style={{ margin: '6px auto 0', maxWidth: 430 }}>Galeri prestasi mahasiswa akan segera diluncurkan. Tim kami sedang merangkum capaian terbaik dari seluruh ormawa dan delegasi.</p>
                        <div style={{ width: '100%', maxWidth: 380, margin: '18px auto 0' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 7 }}>
                                <small style={{ color: 'var(--adm-muted)', fontSize: 11, fontWeight: 800, textTransform: 'uppercase', letterSpacing: '.07em' }}>Progres Pengembangan</small>
                            </div>
                            <div className="adm-progress" style={{ background: 'var(--adm-soft)' }}><i style={{ width: '65%' }} /></div>
                        </div>
                    </div>
                </section>

                <section className="adm-card">
                    <div className="adm-card-head"><h2 className="adm-card-title">Yang Akan Kami Hadirkan</h2><span className="adm-badge">{upcoming.length} Fitur</span></div>
                    <div className="adm-card-body"><div className="adm-steps" style={{ gap: 16 }}>
                        {upcoming.map((item) => <div className="adm-step" key={item.num}>
                            <span className="adm-step-num">{item.num}</span>
                            <div><h3>{item.title}</h3><p>{item.desc}</p></div>
                        </div>)}
                    </div></div>
                </section>
            </div>

                <div className="adm-stack">
                <StatStrip
                    items={[
                        { icon: Medal, value: '300+', label: 'Medali Diraih', tone: 'gold' },
                        { icon: Clock3, value: 5, label: 'Tahun Terakhir' },
                    ]}
                />

                <div className="adm-note gold"><Star size={16} /><div><b style={{ display: 'block', marginBottom: 2, color: 'var(--adm-heading)' }}>Kebanggaan bersama.</b>Lebih dari 300 medali telah dikumpulkan dalam lima tahun terakhir — dan terus bertambah setiap semesternya.</div></div>

                <section className="adm-card">
                    <div className="adm-card-head"><h2 className="adm-card-title">Sambil Menunggu</h2></div>
                    <div className="adm-card-body tight" style={{ display: 'grid', gap: 9 }}>
                        <a className="adm-btn ghost sm block" href="/kemahasiswaan/organisasi"><Users size={14} /> Organisasi Mahasiswa</a>
                        <a className="adm-btn ghost sm block" href="/beasiswa"><Wallet size={14} /> Program Beasiswa</a>
                        <a className="adm-btn ghost sm block" href="/kemahasiswaan/layanan"><HeartPulse size={14} /> Layanan Mahasiswa</a>
                    </div>
                </section>
            </div>
        </div>
    </KemahasiswaanShell>;
}
