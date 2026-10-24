// Detail kampanye tracer study — daftar pertanyaan & status pengisian (Inertia React).
import { Head } from '@inertiajs/react';
import {
    ArrowLeft, CheckCircle2, ClipboardList, Clock3, GraduationCap,
    ListChecks, PenLine,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Question = {
    id: string;
    type: string;
    typeLabel: string;
    text: string;
    options: string[];
};

type Campaign = {
    id: number;
    title: string;
    description: string | null;
    academicYear: string | null;
    startDate: string | null;
    endDate: string | null;
    status: string;
    statusLabel: string;
    questions: Question[];
};

type Props = {
    shell: ShellProps;
    campaign: Campaign;
    hasResponded: boolean;
    submittedAt: string | null;
    canFill: boolean;
    urls: { index: string; fill: string };
};

function DetailRow({ label, value }: { label: string; value: string | null }) {
    return (
        <tr>
            <td className="db-hint" style={{ width: '45%', padding: '9px 0', borderBottom: '1px solid var(--db-line)' }}>{label}</td>
            <td style={{ padding: '9px 0', borderBottom: '1px solid var(--db-line)', color: 'var(--db-heading)', fontWeight: 600 }}>{value || '-'}</td>
        </tr>
    );
}

export default function TracerStudyShow({ shell, campaign, hasResponded, submittedAt, canFill, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`${campaign.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Tracer study"
                        title={campaign.title}
                        description={campaign.description ?? 'Survei tracer study alumni.'}
                        badges={[
                            hasResponded ? 'Sudah diisi' : canFill ? 'Belum diisi' : campaign.statusLabel,
                            `${campaign.questions.length} pertanyaan`,
                        ]}
                        actions={(
                            <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar survei</a>
                        )}
                    />

                    <div className="db-layout">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><ListChecks size={16} /> Pertanyaan Survei</h2>
                                <span className="db-badge gray">{campaign.questions.length} item</span>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 12 }}>
                                {campaign.questions.length === 0 ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada pertanyaan yang ditambahkan.</p>
                                ) : (
                                    campaign.questions.map((question, index) => (
                                        <div
                                            key={question.id}
                                            style={{ padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-soft)' }}
                                        >
                                            <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                                                <span style={{ color: 'var(--db-muted)', fontSize: 12, fontWeight: 800, minWidth: 20 }}>{index + 1}.</span>
                                                <div style={{ minWidth: 0 }}>
                                                    <div style={{ color: 'var(--db-heading)', fontSize: 13.5, fontWeight: 600, lineHeight: 1.6 }}>{question.text || '-'}</div>
                                                    <span className="db-badge gray" style={{ marginTop: 6 }}>{question.typeLabel}</span>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </section>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Clock3 size={16} /> Informasi Kampanye</h2>
                                </div>
                                <div className="db-card-body">
                                    <table className="db-table" style={{ width: '100%' }}>
                                        <tbody>
                                            <DetailRow label="Periode" value={`${campaign.startDate ?? '-'} - ${campaign.endDate ?? '-'}`} />
                                            <DetailRow label="Tahun akademik" value={campaign.academicYear} />
                                            <DetailRow label="Status kampanye" value={campaign.statusLabel} />
                                            <DetailRow label="Jumlah pertanyaan" value={String(campaign.questions.length)} />
                                            <DetailRow label="Waktu submit" value={submittedAt} />
                                        </tbody>
                                    </table>

                                    <div style={{ marginTop: 14 }}>
                                        {hasResponded ? (
                                            <div className="db-note info">
                                                <CheckCircle2 size={15} />
                                                <span>Terima kasih! Kamu sudah mengisi survei ini pada {submittedAt ?? '-'}.</span>
                                            </div>
                                        ) : canFill ? (
                                            <a className="db-btn primary" href={urls.fill} style={{ width: '100%' }}>
                                                <PenLine size={15} /> Isi Survei Sekarang
                                            </a>
                                        ) : (
                                            <div className="db-note muted">
                                                <GraduationCap size={15} />
                                                <span>Survei ini sudah tidak aktif atau belum dibuka.</span>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
