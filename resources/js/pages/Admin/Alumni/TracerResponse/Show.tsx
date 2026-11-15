// Detail satu respon tracer study.
import { Head } from '@inertiajs/react';
import { BarChart3, Briefcase, CircleHelp, GraduationCap, User } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type ResponseDetail = {
    id: number;
    campaignId: number | null;
    campaignTitle: string | null;
    alumniName: string;
    nim: string;
    studyProgram: string;
    graduationYear: number | null;
    employmentStatus: string | null;
    employmentStatusLabel: string;
    employerName: string | null;
    jobTitle: string | null;
    jobRelevance: string | null;
    jobRelevanceLabel: string;
    timeToEmployment: number | null;
    salaryRange: string | null;
    furtherStudy: boolean;
    submittedAt: string | null;
};

type Answer = { id: string; type: string; text: string; answer: string };

type Props = {
    shell: ShellProps;
    response: ResponseDetail;
    answers: Answer[];
    urls: { index: string; campaign: string | null };
};

function Detail({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <b style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{value ?? '-'}</b>
        </div>
    );
}

export default function TracerResponseShow({ shell, response, answers, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Respon ${response.alumniName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BarChart3}
                        eyebrow="Alumni · Tracer Study"
                        title={response.alumniName}
                        description={`Respon untuk kampanye ${response.campaignTitle ?? '-'}, dikirim ${response.submittedAt ?? '-'}.`}
                        badges={[response.employmentStatusLabel, `Relevansi: ${response.jobRelevanceLabel}`, response.furtherStudy ? 'Studi lanjut' : 'Tidak studi lanjut']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar respon</a>
                                {urls.campaign && <a className="db-btn light" href={urls.campaign}>Detail kampanye</a>}
                            </>
                        )}
                    />

                    <div className="crud-grid">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><User size={16} /> Data Alumni</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <Detail label="Nama lengkap" value={response.alumniName} />
                                    <Detail label="NIM" value={response.nim} />
                                    <Detail label="Program studi" value={response.studyProgram} />
                                    <Detail label="Tahun lulus" value={response.graduationYear} />
                                </div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Briefcase size={16} /> Status Pekerjaan</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <Detail label="Status" value={response.employmentStatusLabel} />
                                    <Detail label="Perusahaan / instansi" value={response.employerName} />
                                    <Detail label="Jabatan" value={response.jobTitle} />
                                    <Detail label="Relevansi bidang" value={response.jobRelevanceLabel} />
                                    <Detail label="Waktu ke pekerjaan pertama" value={response.timeToEmployment !== null ? `${response.timeToEmployment} bulan` : null} />
                                    <Detail label="Rentang gaji" value={response.salaryRange} />
                                    <Detail label="Studi lanjut" value={response.furtherStudy ? 'Ya' : 'Tidak'} />
                                    <Detail label="Dikirim" value={response.submittedAt} />
                                </div>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CircleHelp size={16} /> Jawaban Kuesioner ({answers.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {answers.length === 0 ? (
                                <p className="db-hint">Kampanye ini tidak memiliki pertanyaan tambahan.</p>
                            ) : (
                                <div className="db-stack">
                                    {answers.map((answer, index) => (
                                        <div key={answer.id} style={{ paddingBottom: 14, borderBottom: index < answers.length - 1 ? '1px solid var(--db-line)' : 'none' }}>
                                            <span className="crud-label" style={{ marginBottom: 4 }}>{index + 1}. {answer.text}</span>
                                            <div style={{ color: 'var(--db-ink)', whiteSpace: 'pre-line', lineHeight: 1.7 }}>{answer.answer}</div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body">
                            <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: 'var(--db-muted)', fontSize: 13 }}>
                                <GraduationCap size={16} />
                                Data ini diisi langsung oleh alumni melalui portal tracer study dan tidak dapat diubah dari panel admin.
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
