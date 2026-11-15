// Detail BKD dosen — rekap SKS, rincian item, alur approval, dan pengajuan.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, BookOpen, Briefcase, CheckCircle2, ClipboardList, Clock, FileText, FlaskConical,
    RefreshCw, Send, ShieldCheck, Users,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Item = { id: number; category: string; title: string; sks: number; description: string | null };
type Step = { id: number; order: number | null; status: string; approverRole: string | null; actedAt: string | null };

type Submission = {
    id: number; period: string; periodStatus: string; status: string;
    teachingSks: number; structuralSks: number; tridharmaSks: number; totalSks: number;
    minimumSks: number; maximumSks: number; notes: string | null;
    generatedAt: string | null; submittedAt: string | null; approvedAt: string | null;
    items: Item[]; steps: Step[];
};

type Props = {
    shell: ShellProps;
    id: number;
    submission: Submission;
    urls: { index: string; submit: string; regenerate: string };
};

const statusTone = (status: string) => {
    const value = status.toLowerCase();
    if (['approved', 'approved_by_dean', 'completed'].includes(value)) return 'green';
    if (['in_approval', 'submitted', 'pending', 'menunggu'].includes(value)) return 'amber';
    if (['rejected', 'cancelled'].includes(value)) return 'red';
    return 'gray';
};

const stepTone = (status: string) => {
    const value = status.toLowerCase();
    if (['approved', 'completed', 'done'].includes(value)) return 'db-timeline-dot green';
    if (['pending', 'waiting'].includes(value)) return 'db-timeline-dot gold';
    return 'db-timeline-dot';
};

const categoryLabel: Record<string, string> = {
    teaching: 'Pengajaran', structural: 'Struktural', tridharma: 'Tridharma', research: 'Penelitian',
    community_service: 'Pengabdian', supporting: 'Penunjang',
};

export default function WorkloadsShow({ shell, submission, urls }: Props) {
    const [confirmRegenerate, setConfirmRegenerate] = useState(false);
    const form = useForm<{ notes: string }>({ notes: submission.notes ?? '' });
    const canSubmit = ['draft', 'revision', 'rejected', 'cancelled'].includes(submission.status);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { preserveScroll: true });
    };

    const regenerate = () => {
        router.post(urls.regenerate, {}, { preserveScroll: true, onFinish: () => setConfirmRegenerate(false) });
    };

    const progress = submission.maximumSks > 0 ? Math.min(100, (submission.totalSks / submission.maximumSks) * 100) : 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`BKD ${submission.period} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik · Beban Kerja Dosen"
                        title={`BKD ${submission.period}`}
                        description={`Status pengajuan: ${submission.status} · Periode ${submission.periodStatus}`}
                        badges={[`${submission.totalSks} SKS`, `Min ${submission.minimumSks}`, `Maks ${submission.maximumSks}`]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar BKD</Link>
                                <button className="db-btn light" type="button" onClick={() => setConfirmRegenerate(true)}><RefreshCw size={15} /> Siapkan ulang</button>
                            </div>
                        }
                    />

                    {confirmRegenerate && (
                        <div className="db-note warning">
                            <RefreshCw size={15} />
                            <span>Draf BKD akan dihitung ulang dari data terbaru dan menimpa item yang ada. Lanjutkan?</span>
                            <span style={{ display: 'inline-flex', gap: 6, marginLeft: 'auto' }}>
                                <button className="db-btn primary sm" type="button" onClick={regenerate}>Ya, hitung ulang</button>
                                <button className="db-btn ghost sm" type="button" onClick={() => setConfirmRegenerate(false)}>Batal</button>
                            </span>
                        </div>
                    )}

                    <section className="db-stats" aria-label="Rekap SKS">
                        <div className="db-stat"><span className="db-stat-icon"><BookOpen size={20} /></span><div><span className="db-stat-num">{submission.teachingSks}</span><span className="db-stat-label">Pengajaran</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon gold"><Briefcase size={20} /></span><div><span className="db-stat-num">{submission.structuralSks}</span><span className="db-stat-label">Struktural</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon green"><FlaskConical size={20} /></span><div><span className="db-stat-num">{submission.tridharmaSks}</span><span className="db-stat-label">Tridharma</span></div></div>
                        <div className="db-stat"><span className="db-stat-icon"><ClipboardList size={20} /></span><div><span className="db-stat-num">{submission.totalSks}</span><span className="db-stat-label">Total SKS</span></div></div>
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title">Status & progres</h2></div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span className="db-hint">Status pengajuan</span>
                                    <span className={`db-badge ${statusTone(submission.status)}`}>{submission.status}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span className="db-hint">Status periode</span>
                                    <span className="db-badge gray">{submission.periodStatus}</span>
                                </div>
                                <div>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                                        <span>Terhadap batas maksimal</span><b>{Math.round(progress)}%</b>
                                    </div>
                                    <div className="db-progress"><i style={{ width: `${progress}%` }} /></div>
                                    <div className="crud-hint">Rentang ideal {submission.minimumSks}–{submission.maximumSks} SKS per periode.</div>
                                </div>
                                <div style={{ display: 'grid', gap: 4, fontSize: 13 }}>
                                    <div><b>Dibuat:</b> {submission.generatedAt ?? '-'}</div>
                                    <div><b>Diajukan:</b> {submission.submittedAt ?? '-'}</div>
                                    <div><b>Disetujui:</b> {submission.approvedAt ?? '-'}</div>
                                </div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><ShieldCheck size={16} /> Alur persetujuan</h2></div>
                            <div className="db-card-body">
                                {submission.steps.length === 0 ? (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada alur approval. Ajukan BKD untuk memulai review.</p>
                                ) : (
                                    <div className="db-timeline">
                                        {submission.steps.map((step) => (
                                            <div className="db-timeline-item" key={step.id}>
                                                <span className={stepTone(step.status)} />
                                                <div className="db-timeline-main">
                                                    <b>Langkah {step.order ?? '-'} · {step.approverRole ?? 'Approver'}</b>
                                                    <small>Status: {step.status}</small>
                                                </div>
                                                <div className="db-timeline-when">
                                                    <small><Clock size={12} /> {step.actedAt ?? 'Menunggu'}</small>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Rincian item ({submission.items.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {submission.items.length === 0 ? (
                                <EmptyState icon={ClipboardList} title="Belum ada item BKD" description="Klik Siapkan ulang untuk menghitung dari data kelas dan kegiatan." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>Kategori</th><th>Kegiatan</th><th>SKS</th><th>Keterangan</th></tr></thead>
                                        <tbody>
                                            {submission.items.map((item) => (
                                                <tr key={item.id}>
                                                    <td><span className="db-badge gray">{categoryLabel[item.category] ?? item.category}</span></td>
                                                    <td><b>{item.title}</b></td>
                                                    <td>{item.sks}</td>
                                                    <td>{item.description ?? '-'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Send size={16} /> Pengajuan BKD</h2>
                        </div>
                        <div className="db-card-body">
                            {!canSubmit ? (
                                <div className="db-note success">
                                    <CheckCircle2 size={15} />
                                    <span>BKD sudah diajukan. Pantau status pada alur persetujuan di atas.</span>
                                </div>
                            ) : (
                                <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                    <div>
                                        <label className="crud-label">Catatan untuk reviewer</label>
                                        <textarea
                                            className="db-input"
                                            rows={4}
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            placeholder="Tambahkan konteks kegiatan atau dokumen pendukung yang relevan."
                                        />
                                        {form.errors.notes && <div className="db-error">{form.errors.notes}</div>}
                                        {(form.errors as Record<string, string>).submission && <div className="db-error">{(form.errors as Record<string, string>).submission}</div>}
                                    </div>
                                    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                        <button className="db-btn primary" type="submit" disabled={form.processing}>
                                            <Send size={14} /> {form.processing ? 'Mengajukan…' : 'Ajukan BKD'}
                                        </button>
                                        <Link className="db-btn ghost" href={urls.index}><ArrowLeft size={14} /> Batal</Link>
                                        <span className="db-hint" style={{ marginLeft: 'auto', display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                                            <Users size={13} /> Pastikan total SKS sudah sesuai sebelum diajukan.
                                        </span>
                                    </div>
                                </form>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <Link className="db-btn ghost" href={urls.index}><ArrowLeft size={14} /> Kembali</Link>
                            <span className="db-hint" style={{ marginLeft: 'auto', display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                                <FileText size={13} /> Data dihitung otomatis dari kelas, publikasi, dan kegiatan tridharma.
                            </span>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
