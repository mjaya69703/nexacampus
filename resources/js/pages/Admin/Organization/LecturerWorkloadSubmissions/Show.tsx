// Detail laporan BKD dosen: rincian butir kegiatan & permintaan revisi.
import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, FileSignature, Info, ListChecks, RotateCcw } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import { OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Submission = {
    id: number;
    ownerName: string;
    ownerEmail: string | null;
    periodName: string;
    periodCode: string;
    periodStatus: string | null;
    programName: string;
    status: string;
    statusLabel: string;
    teachingSks: number;
    structuralSks: number;
    tridharmaSks: number;
    totalSks: number;
    lecturerNotes: string | null;
    reviewNotes: string | null;
    submittedAt: string | null;
    approvedAt: string | null;
    canRequestRevision: boolean;
    canDelete: boolean;
};

type Item = {
    id: number;
    category: string;
    categoryLabel: string;
    sourceCode: string | null;
    title: string;
    sks: number;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    submission: Submission;
    items: Item[];
    urls: { index: string; requestRevision: string; destroy: string };
};

const statusTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'revision') return 'amber';
    if (status === 'in_approval' || status === 'submitted') return 'gold';
    return 'gray';
};

const formatSks = (value: number) => `${value.toFixed(2)} SKS`;

export default function LecturerWorkloadSubmissionShow({ shell, can, submission, items, urls }: Props) {
    const form = useForm({
        review_notes: submission.reviewNotes || 'Dikembalikan untuk revisi oleh admin.',
    });

    const submitRevision = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.requestRevision, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Laporan BKD ${submission.ownerName} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Kepegawaian · Review BKD"
                        title={`Laporan BKD: ${submission.ownerName}`}
                        description={`Periode: ${submission.periodName} · Kode Periode: ${submission.periodCode} · Program Studi: ${submission.programName}`}
                        badges={[submission.statusLabel]}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali ke daftar</a>}
                    />

                    <OrgStats
                        label="Statistik laporan BKD"
                        items={[
                            { label: 'Status Laporan', value: submission.statusLabel, icon: Info },
                            { label: 'SKS Pengajaran', value: formatSks(submission.teachingSks), icon: FileSignature },
                            { label: 'SKS Struktural', value: formatSks(submission.structuralSks), icon: ListChecks },
                            { label: 'Total SKS Diakui', value: formatSks(submission.totalSks), icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    {submission.reviewNotes && (
                        <div className="db-note warning">
                            <AlertTriangle size={16} />
                            <span>
                                <b style={{ display: 'block', marginBottom: 3 }}>Catatan Hasil Review / Revisi</b>
                                {submission.reviewNotes}
                            </span>
                        </div>
                    )}

                    {can.update && submission.canRequestRevision && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><RotateCcw size={16} /> Catatan Instruksi Revisi untuk Dosen</h2>
                                <span className={`db-badge ${statusTone(submission.status)}`.trim()}>{submission.statusLabel}</span>
                            </div>
                            <div className="db-card-body">
                                <form onSubmit={submitRevision}>
                                    <TextareaField
                                        label="Catatan revisi"
                                        rows={3}
                                        placeholder="Tuliskan bagian atau butir kegiatan yang perlu diperbaiki oleh dosen yang bersangkutan…"
                                        value={form.data.review_notes}
                                        onChange={(event) => form.setData('review_notes', event.target.value)}
                                        error={form.errors.review_notes}
                                        hint='Catatan ini akan langsung ditampilkan di dasbor BKD dosen setelah tombol "Kembalikan untuk Revisi" ditekan.'
                                    />
                                    <div className="db-form-actions" style={{ marginTop: 14, justifyContent: 'flex-end' }}>
                                        <button className="db-btn primary" type="submit" disabled={form.processing}>
                                            <RotateCcw size={15} /> Kembalikan untuk Revisi
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ListChecks size={16} /> Rincian Butir Kegiatan Tridharma & Tugas Tambahan</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Kategori Aktivitas</th>
                                            <th>Nama Butir / Judul Kegiatan</th>
                                            <th style={{ textAlign: 'center' }}>Kode Sumber</th>
                                            <th style={{ textAlign: 'right' }}>Bobot SKS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {items.length === 0 && (
                                            <tr>
                                                <td colSpan={4} style={{ textAlign: 'center', padding: 24 }}>
                                                    <span className="db-hint">Tidak ada rincian butir kegiatan yang tercatat dalam laporan ini.</span>
                                                </td>
                                            </tr>
                                        )}
                                        {items.map((item) => (
                                            <tr key={item.id}>
                                                <td><span className="db-badge">{item.categoryLabel}</span></td>
                                                <td><b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{item.title}</b></td>
                                                <td style={{ textAlign: 'center' }}><span className="db-badge gray">{item.sourceCode ?? '-'}</span></td>
                                                <td style={{ textAlign: 'right' }}><b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatSks(item.sks)}</b></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colSpan={3} style={{ textAlign: 'right', fontWeight: 700 }}>Total SKS Keseluruhan</td>
                                            <td style={{ textAlign: 'right', fontWeight: 700, color: 'var(--db-brand)' }}>{formatSks(submission.totalSks)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Info size={16} /> Informasi Pengajuan</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="db-facts">
                                <div className="db-fact"><span>Dosen Pemohon</span><b>{submission.ownerName}</b></div>
                                <div className="db-fact"><span>Email</span><b>{submission.ownerEmail ?? '-'}</b></div>
                                <div className="db-fact"><span>Program Studi</span><b>{submission.programName}</b></div>
                                <div className="db-fact"><span>Periode BKD</span><b>{submission.periodName}</b></div>
                                <div className="db-fact"><span>Diajukan Pada</span><b>{submission.submittedAt ?? '-'}</b></div>
                                <div className="db-fact"><span>Disetujui Pada</span><b>{submission.approvedAt ?? '-'}</b></div>
                                <div className="db-fact wide"><span>Catatan Dosen</span><b>{submission.lecturerNotes ?? '-'}</b></div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
