import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, GraduationCap } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type App = { id: number; number: string; status: string; statusLabel: string; graduationPeriod: string | null; graduationDate: string | null; thesisTitle: string | null; reason: string | null; studentNotes: string | null; adminNotes: string | null; attachmentUrl: string | null; academicPeriod: string | null; graduationBatch: string | null; eligibilitySnapshot: any; histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[]; approval: { status: string; waitingMessage: string | null } | null };
type CheckItem = { key: string; label: string; checked: boolean; checkedAt: string | null; checkedBy: string | null; notes: string | null };
type Doc = { id: number; fileName: string; verificationStatus: string; verificationNotes: string | null; verifiedAt: string | null; verifiedBy: string | null; previewUrl: string; isPdf: boolean; isImage: boolean };

type Props = { shell: ShellProps; application: App; checklistItems: CheckItem[]; checklistCompleted: number; documents: Doc[]; urls: { index: string; edit: string | null } };

export default function GraduationsShow({ shell, application, checklistItems, checklistCompleted, documents, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={GraduationCap} eyebrow="Student Services" title={application.number} description={application.graduationPeriod ?? '-'} badges={[application.statusLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Status:</b> {application.statusLabel}</div>
                            {application.thesisTitle && <div><b>Judul:</b> {application.thesisTitle}</div>}
                            {application.reason && <div><b>Keterangan:</b> {application.reason}</div>}
                            {application.studentNotes && <div><b>Catatan Mahasiswa:</b> {application.studentNotes}</div>}
                            {application.adminNotes && <div><b>Catatan Admin:</b> {application.adminNotes}</div>}
                            {application.attachmentUrl && <a href={application.attachmentUrl} target="_blank" className="db-btn ghost sm">Lampiran</a>}
                            {urls.edit && <Link href={urls.edit} className="db-btn primary sm">Perbaiki</Link>}
                        </div>
                    </section>
                    {checklistItems.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><CheckCircle2 size={16} /> Checklist Admin ({checklistCompleted}/{checklistItems.length})</h2></div>
                            <div className="db-card-body">
                                {checklistItems.map((c) => (
                                    <div key={c.key} style={{ display: 'flex', gap: 8, alignItems: 'center', padding: '4px 0' }}>
                                        <span className={`db-badge ${c.checked ? 'green' : 'gray'}`}>{c.checked ? '✓' : '○'}</span>
                                        <span>{c.label}</span>
                                        {c.checkedAt && <small style={{ color: 'var(--db-muted)' }}>{c.checkedAt}</small>}
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                    {documents.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Dokumen</h2></div>
                            <div className="db-card-body">
                                {documents.map((d) => (
                                    <div key={d.id} style={{ display: 'flex', gap: 8, alignItems: 'center', padding: '4px 0' }}>
                                        <a href={d.previewUrl} target="_blank" className="db-btn ghost sm">{d.fileName}</a>
                                        <span className="db-badge gray">{d.verificationStatus}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Riwayat</h2></div>
                        <div className="db-card-body">
                            {application.histories.map((h) => (
                                <div key={h.id} style={{ padding: '6px 0', borderBottom: '1px solid var(--db-line)' }}>
                                    <b>{h.to}</b> · {h.changedAt} · {h.changedBy ?? '-'}{h.notes ? ` — ${h.notes}` : ''}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
