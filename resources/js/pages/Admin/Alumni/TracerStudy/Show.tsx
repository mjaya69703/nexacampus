// Detail kampanye tracer study + pertanyaan & respon terbaru.
import { Head, router } from '@inertiajs/react';
import { BarChart3, ChartBarBig, CircleHelp, ClipboardList, Pencil, Send, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Question = { id: string; type: string; text: string; options: string[] };

type CampaignDetail = {
    id: number;
    title: string;
    description: string | null;
    academicYear: string | null;
    targetYears: number[];
    targetPrograms: string[];
    startDate: string | null;
    endDate: string | null;
    status: string;
    statusLabel: string;
    totalSent: number;
    totalResponded: number;
    responsesCount: number;
    responseRate: number;
    questions: Question[];
    createdAt: string | null;
    updatedAt: string | null;
    createdBy: string | null;
    updatedBy: string | null;
};

type RecentResponse = { id: number; name: string; nim: string; submittedAt: string | null; url: string };

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; activate: boolean; responses: boolean };
    campaign: CampaignDetail;
    recentResponses: RecentResponse[];
    urls: { index: string; edit: string | null; destroy: string; activate: string; responses: string; analytics: string };
};

const STATUS_BADGE: Record<string, string> = { draft: 'gray', active: 'green', closed: 'amber' };

function Detail({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <b style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{value || '-'}</b>
        </div>
    );
}

export default function TracerStudyShow({ shell, can, campaign, recentResponses, urls }: Props) {
    const [pending, setPending] = useState<'delete' | 'activate' | null>(null);
    const [processing, setProcessing] = useState(false);

    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending === 'delete') {
            router.delete(urls.destroy, { onFinish: done });
            return;
        }

        router.post(urls.activate, {}, { onFinish: done });
    };

    const targetSummary = [
        campaign.targetYears.length > 0 ? `Tahun lulus ${campaign.targetYears.join(', ')}` : null,
        campaign.targetPrograms.length > 0 ? campaign.targetPrograms.join(', ') : null,
    ].filter(Boolean).join(' · ') || 'Semua alumni aktif';

    return (
        <AdminShell shell={shell}>
            <Head title={`${campaign.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Alumni · Tracer Study"
                        title={campaign.title}
                        description={campaign.description || 'Tidak ada deskripsi kampanye.'}
                        badges={[campaign.statusLabel, `${campaign.questions.length} pertanyaan`, `${campaign.responsesCount} respon`]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && urls.edit && <a className="db-btn light" href={urls.edit}><Pencil size={14} /> Edit</a>}
                                {can.activate && <button className="db-btn light" type="button" onClick={() => setPending('activate')}><Send size={14} /> Aktifkan</button>}
                                {can.responses && <a className="db-btn light" href={urls.responses}><BarChart3 size={14} /> Respon</a>}
                                <a className="db-btn light" href={urls.analytics}><ChartBarBig size={14} /> Analytics</a>
                                {can.delete && <button className="db-btn light" type="button" onClick={() => setPending('delete')}><Trash2 size={14} /> Hapus</button>}
                            </>
                        )}
                    />

                    <section className="db-stats" aria-label="Statistik kampanye">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Send size={20} /></span>
                            <div><span className="db-stat-num">{campaign.totalSent}</span><span className="db-stat-label">Sasaran</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BarChart3 size={20} /></span>
                            <div><span className="db-stat-num">{campaign.responsesCount}</span><span className="db-stat-label">Respon masuk</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ChartBarBig size={20} /></span>
                            <div><span className="db-stat-num">{campaign.responseRate}%</span><span className="db-stat-label">Response rate</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Informasi Kampanye</h2>
                            <span className={`db-badge ${STATUS_BADGE[campaign.status] ?? 'gray'}`}>{campaign.statusLabel}</span>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <Detail label="Tahun akademik" value={campaign.academicYear} />
                                <Detail label="Periode" value={`${campaign.startDate ?? '-'} s.d. ${campaign.endDate ?? '-'}`} />
                                <Detail label="Sasaran alumni" value={targetSummary} />
                                <Detail label="Counter respon" value={String(campaign.totalResponded)} />
                                <Detail label="Dibuat" value={[campaign.createdAt, campaign.createdBy].filter(Boolean).join(' · ')} />
                                <Detail label="Diperbarui" value={[campaign.updatedAt, campaign.updatedBy].filter(Boolean).join(' · ')} />
                            </div>

                            <div style={{ marginTop: 20 }}>
                                <span className="crud-label">Progres respon</span>
                                <div style={{ height: 10, borderRadius: 999, background: 'var(--db-soft)', overflow: 'hidden' }}>
                                    <div style={{ height: '100%', width: `${Math.min(campaign.responseRate, 100)}%`, background: 'var(--db-brand)', transition: 'width .3s ease' }} />
                                </div>
                                <small className="db-hint">{campaign.responsesCount} dari {campaign.totalSent} alumni sasaran ({campaign.responseRate}%)</small>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CircleHelp size={16} /> Pertanyaan Kuesioner ({campaign.questions.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {campaign.questions.length === 0 ? (
                                <p className="db-hint">Belum ada pertanyaan tambahan pada kampanye ini.</p>
                            ) : (
                                <ol style={{ margin: 0, paddingLeft: 20, color: 'var(--db-ink)', lineHeight: 1.8 }}>
                                    {campaign.questions.map((question) => (
                                        <li key={question.id} style={{ marginBottom: 10 }}>
                                            <b style={{ color: 'var(--db-heading)' }}>{question.text}</b>
                                            <div className="db-hint">
                                                Tipe: {question.type}
                                                {question.options.length > 0 && ` · Pilihan: ${question.options.join(' / ')}`}
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><BarChart3 size={16} /> Respon Terbaru</h2>
                            {can.responses && <a className="db-btn ghost sm" href={urls.responses}>Lihat semua</a>}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr><th>Nama</th><th>NIM</th><th>Dikirim</th><th style={{ textAlign: 'right' }}>Aksi</th></tr>
                                    </thead>
                                    <tbody>
                                        {recentResponses.length === 0 && (
                                            <tr><td colSpan={4} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada respon masuk.</span></td></tr>
                                        )}
                                        {recentResponses.map((response) => (
                                            <tr key={response.id}>
                                                <td><b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{response.name}</b></td>
                                                <td>{response.nim}</td>
                                                <td>{response.submittedAt ?? '-'}</td>
                                                <td style={{ textAlign: 'right' }}>
                                                    {can.responses && <a className="db-btn ghost sm" href={response.url}><BarChart3 size={13} /> Detail</a>}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending === 'delete' ? 'Hapus kampanye?' : 'Aktifkan kampanye?'}
                    message={pending === 'delete'
                        ? `"${campaign.title}" dipindah ke sampah. Respon yang sudah masuk tetap tersimpan.`
                        : `"${campaign.title}" akan dihitung sasaran alumninya dan dibuka untuk pengisian. Status draft tidak bisa dikembalikan.`}
                    confirmLabel={pending === 'delete' ? 'Ya, hapus' : 'Ya, aktifkan'}
                    danger={pending === 'delete'}
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
