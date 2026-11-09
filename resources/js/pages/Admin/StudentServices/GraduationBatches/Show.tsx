// Detail batch yudisium + daftar pengajuan & bulk finalize.
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Eye, GraduationCap, Pencil, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import { DetailGrid, HistoryTimeline, StatusBadge } from '../../../../components/Shared/StudentServices/ServiceUI';
import { WorkflowModal } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type ApplicationRow = {
    id: number;
    number: string;
    student: string;
    nim: string;
    studyProgram: string;
    status: string;
    submittedAt: string | null;
    showUrl: string;
};

type Batch = {
    id: number;
    name: string;
    code: string;
    period: string;
    academicYear: string;
    scope: string;
    yudisiumDate: string | null;
    skNumber: string | null;
    skDate: string | null;
    status: string;
    notes: string | null;
    applicationsCount: number;
    approvedCount: number;
    finalizedCount: number;
    applications: ApplicationRow[];
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; viewApplication: boolean };
    batch: Batch;
    urls: { index: string; edit: string; bulkFinalize: string };
};

const STATUS_LABELS: Record<string, string> = {
    draft: 'Draf',
    open: 'Dibuka',
    review: 'Review',
    finalized: 'Final',
    cancelled: 'Dibatalkan',
};

export default function GraduationBatchShow({ shell, can, batch, urls }: Props) {
    const [finalizeOpen, setFinalizeOpen] = useState(false);
    const form = useForm({ notes: '' });

    const submitFinalize = () => {
        form.post(urls.bulkFinalize, {
            preserveScroll: true,
            onSuccess: () => {
                setFinalizeOpen(false);
                form.reset();
            },
        });
    };

    const canFinalize = can.update && batch.approvedCount > 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`${batch.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Layanan Mahasiswa · Batch Yudisium"
                        title={batch.name}
                        description={`${batch.period} · ${batch.scope}`}
                        badges={[STATUS_LABELS[batch.status] ?? batch.status]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.update && <a className="db-btn primary" href={urls.edit}><Pencil size={15} /> Edit Batch</a>}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Informasi Batch</h2>
                            <StatusBadge status={batch.status} label={STATUS_LABELS[batch.status]} />
                        </div>
                        <div className="db-card-body">
                            <DetailGrid
                                items={[
                                    { label: 'Kode Batch', value: batch.code },
                                    { label: 'Periode', value: batch.period },
                                    { label: 'Tahun Akademik', value: batch.academicYear },
                                    { label: 'Cakupan', value: batch.scope },
                                    { label: 'Tanggal Yudisium', value: batch.yudisiumDate ?? '-' },
                                    { label: 'Nomor SK', value: batch.skNumber ?? '-' },
                                    { label: 'Tanggal SK', value: batch.skDate ?? '-' },
                                    { label: 'Catatan', value: batch.notes ?? '-' },
                                ]}
                            />
                        </div>
                    </section>

                    <section className="db-stats" aria-label="Statistik batch">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Users size={20} /></span>
                            <div><span className="db-stat-num">{batch.applicationsCount}</span><span className="db-stat-label">Total Pengajuan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num">{batch.approvedCount}</span><span className="db-stat-label">Siap Difinalisasi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{batch.finalizedCount}</span><span className="db-stat-label">Sudah Final</span></div>
                        </div>
                    </section>

                    {canFinalize && (
                        <div className="db-note info">
                            <CheckCircle2 size={15} />
                            <span>
                                <b>{batch.approvedCount} pengajuan disetujui</b> siap difinalisasi. Pastikan tanggal yudisium batch sudah diisi.
                            </span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Pengajuan dalam Batch</h2>
                            {canFinalize && (
                                <button className="db-btn primary sm" type="button" onClick={() => setFinalizeOpen(true)}>
                                    <CheckCircle2 size={14} /> Finalisasi Semua
                                </button>
                            )}
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Nomor</th>
                                            <th>Mahasiswa</th>
                                            <th>Program Studi</th>
                                            <th>Status</th>
                                            <th>Diajukan</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {batch.applications.length === 0 && (
                                            <tr>
                                                <td colSpan={6} style={{ textAlign: 'center', padding: 24 }}>
                                                    <span className="db-hint">Belum ada pengajuan pada batch ini.</span>
                                                </td>
                                            </tr>
                                        )}
                                        {batch.applications.map((application) => (
                                            <tr key={application.id}>
                                                <td><b className="crud-name">{application.number}</b></td>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{application.student}</b>
                                                    <small className="db-hint">{application.nim}</small>
                                                </td>
                                                <td>{application.studyProgram}</td>
                                                <td><StatusBadge status={application.status} /></td>
                                                <td>{application.submittedAt ?? '-'}</td>
                                                <td>
                                                    <div className="crud-row-actions">
                                                        {can.viewApplication && (
                                                            <a className="db-btn ghost sm" href={application.showUrl} title="Lihat detail">
                                                                <Eye size={13} />
                                                            </a>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <WorkflowModal
                    open={finalizeOpen}
                    title="Finalisasi Pengajuan Disetujui"
                    message={`Semua pengajuan berstatus Disetujui (${batch.approvedCount}) akan difinalisasi. Status mahasiswa berubah menjadi Lulus dan profil dinonaktifkan.`}
                    confirmLabel="Finalisasi Sekarang"
                    processing={form.processing}
                    onConfirm={submitFinalize}
                    onCancel={() => setFinalizeOpen(false)}
                >
                    <TextareaField
                        label="Catatan finalisasi"
                        rows={3}
                        placeholder="Opsional…"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        error={form.errors.notes}
                    />
                </WorkflowModal>
            </div>
        </AdminShell>
    );
}
