// Detail karya pada repositori publik skripsi/tugas akhir.
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft, BookMarked, Building2, CalendarDays, Download, FileText,
    GraduationCap, ShieldCheck, UserRound, Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AcademicBaseProps, AcademicShell } from '../../../components/Home/Academic/AcademicShell';
import '../../../../css/admission-public.css';

type Props = AcademicBaseProps & {
    thesis: {
        id: number; title: string; topic: string | null; abstract: string | null; keywords: string | null;
        typeLabel: string; year: string | null; completedAt: string | null;
        finalScore: number | null; finalGrade: string | null;
        plagiarismStatusLabel: string; plagiarismStatusTone: string; plagiarismScore: number | null;
        plagiarismTool: string | null; plagiarismCheckedAt: string | null;
        student: { name: string | null; nim: string | null; program: string | null; faculty: string | null };
        supervisors: { name: string | null; nidn: string | null; roleLabel: string }[];
        finalDocument: { fileName: string | null; fileSize: string; downloadUrl: string } | null;
    };
    backUrl: string;
};

function Info({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
    return (
        <div>
            <span style={{ display: 'block', color: 'var(--adm-muted)', fontSize: 10.5, fontWeight: 800, letterSpacing: '.08em', textTransform: 'uppercase' }}>{label}</span>
            <div style={{ marginTop: 3, color: 'var(--adm-heading)', fontWeight: 700 }}>{value || '-'}</div>
            {hint && <div style={{ marginTop: 3, color: 'var(--adm-muted)', fontSize: 12 }}>{hint}</div>}
        </div>
    );
}

export default function RepositoryShow({ campus, links, user, thesis, backUrl }: Props) {
    const citation = `${thesis.student.name ?? '-'} (${thesis.completedAt ?? '-'}). ${thesis.title}. ${thesis.typeLabel}, ${thesis.student.program ?? '-'}, ${campus.name}.`;

    return (
        <AcademicShell
            campus={campus}
            links={links}
            user={user}
            activeTab="Repositori"
            eyebrow="Detail karya"
            title={thesis.title}
            description={`${thesis.student.name ?? '-'} · ${thesis.student.nim ?? '-'} · ${thesis.student.program ?? '-'}`}
            icon={BookMarked}
            action={<Link href={backUrl} className="adm-btn light"><ArrowLeft size={14} /> Kembali</Link>}
        >
            <Head title={`${thesis.title} · Repositori ${campus.name}`} />

            <div className="adm-layout">
                <div className="adm-stack">
                    <section className="adm-card">
                        <div className="adm-card-head">
                            <h2 className="adm-card-title"><FileText size={16} /> Abstrak</h2>
                            <span className="adm-badge">{thesis.typeLabel}</span>
                        </div>
                        <div className="adm-card-body">
                            <p style={{ margin: 0, color: 'var(--adm-ink)', lineHeight: 1.8 }}>{thesis.abstract || 'Abstrak belum tersedia.'}</p>
                            {thesis.keywords && (
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 14 }}>
                                    {thesis.keywords.split(',').map((keyword) => keyword.trim()).filter(Boolean).map((keyword) => (
                                        <span key={keyword} className="adm-badge gray">{keyword}</span>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="adm-card">
                        <div className="adm-card-head"><h2 className="adm-card-title"><GraduationCap size={16} /> Metadata Karya</h2></div>
                        <div className="adm-card-body">
                            <div className="adm-grid adm-cols-2" style={{ gap: 16 }}>
                                <Info label="Penulis" value={thesis.student.name} hint={thesis.student.nim} />
                                <Info label="Program studi" value={thesis.student.program} hint={thesis.student.faculty} />
                                <Info label="Jenis karya" value={thesis.typeLabel} hint={thesis.year} />
                                <Info label="Tanggal selesai" value={thesis.completedAt} />
                                <Info label="Nilai akhir" value={thesis.finalScore !== null ? `${thesis.finalScore} (${thesis.finalGrade ?? '-'})` : '-'} />
                                <Info label="Topik" value={thesis.topic} />
                            </div>
                        </div>
                    </section>

                    <section className="adm-card">
                        <div className="adm-card-head"><h2 className="adm-card-title"><Users size={16} /> Pembimbing</h2></div>
                        <div className="adm-card-body">
                            {thesis.supervisors.length === 0 ? (
                                <p style={{ margin: 0, color: 'var(--adm-muted)' }}>Data pembimbing belum tersedia.</p>
                            ) : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {thesis.supervisors.map((supervisor, index) => (
                                        <div key={`${supervisor.nidn ?? index}`} style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '10px 12px', border: '1px solid var(--adm-line)', borderRadius: 10, background: 'var(--adm-soft)' }}>
                                            <div>
                                                <b style={{ color: 'var(--adm-heading)' }}>{supervisor.name ?? '-'}</b>
                                                <div style={{ color: 'var(--adm-muted)', fontSize: 12 }}>{supervisor.nidn ?? '-'}</div>
                                            </div>
                                            <span className="adm-badge gray">{supervisor.roleLabel}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="adm-card">
                        <div className="adm-card-head">
                            <h2 className="adm-card-title"><ShieldCheck size={16} /> Cek Plagiarisme</h2>
                            <span className={`adm-badge ${thesis.plagiarismStatusTone}`}>{thesis.plagiarismStatusLabel}</span>
                        </div>
                        <div className="adm-card-body">
                            <div className="adm-grid adm-cols-2" style={{ gap: 16 }}>
                                <Info label="Skor kemiripan" value={thesis.plagiarismScore !== null ? `${thesis.plagiarismScore}%` : '-'} />
                                <Info label="Alat pengecekan" value={thesis.plagiarismTool ?? '-'} hint={thesis.plagiarismCheckedAt ? `Dicek ${thesis.plagiarismCheckedAt}` : undefined} />
                            </div>
                        </div>
                    </section>
                </div>

                <aside className="adm-stack">
                    <section className="adm-card">
                        <div className="adm-card-head"><h2 className="adm-card-title"><UserRound size={16} /> Penulis</h2></div>
                        <div className="adm-card-body" style={{ display: 'grid', gap: 10 }}>
                            <Info label="Nama" value={thesis.student.name} />
                            <Info label="NIM" value={thesis.student.nim} />
                            <Info label="Program studi" value={thesis.student.program} hint={thesis.student.faculty} />
                        </div>
                    </section>

                    <section className="adm-card">
                        <div className="adm-card-head"><h2 className="adm-card-title"><Download size={16} /> Dokumen Final</h2></div>
                        <div className="adm-card-body">
                            {thesis.finalDocument ? (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    <div>
                                        <b style={{ color: 'var(--adm-heading)' }}>{thesis.finalDocument.fileName ?? 'Laporan final'}</b>
                                        <div style={{ color: 'var(--adm-muted)', fontSize: 12 }}>{thesis.finalDocument.fileSize}</div>
                                    </div>
                                    <a className="adm-btn primary" href={thesis.finalDocument.downloadUrl}>
                                        <Download size={14} /> Unduh Laporan Final
                                    </a>
                                </div>
                            ) : (
                                <p style={{ margin: 0, color: 'var(--adm-muted)' }}>Laporan final belum diunggah oleh penulis.</p>
                            )}
                        </div>
                    </section>

                    <section className="adm-card">
                        <div className="adm-card-head"><h2 className="adm-card-title"><Building2 size={16} /> Sitasi</h2></div>
                        <div className="adm-card-body">
                            <p style={{ margin: 0, color: 'var(--adm-ink)', fontSize: 12.5, lineHeight: 1.7 }}>{citation}</p>
                            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 12, color: 'var(--adm-muted)', fontSize: 12 }}>
                                <CalendarDays size={13} /> {thesis.completedAt ?? '-'}
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </AcademicShell>
    );
}
