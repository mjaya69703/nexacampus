// Detail nilai mahasiswa — read-only + aksi lifecycle.
import { Head, router } from '@inertiajs/react';
import { Award, Megaphone, Pencil, RotateCcw, Stamp, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type GradeComponent = {
    id: number; name: string; weight: number | null; score: number | null;
    sortOrder: number | null; notes: string | null;
    updateUrl: string; deleteUrl: string;
};

type Grade = {
    id: number; student: string; nim: string | null; program: string | null;
    year: string | null; course: string; classLabel: string | null; credits: number | null;
    gradedBy: string | null; gradedAt: string | null;
    lifecycle: string; lifecycleTone: string; result: string | null; resultTone: string;
    finalScore: number | null; letter: string | null; point: number | null;
    notes: string | null; isPublished: boolean; isFinalized: boolean; isDraft: boolean;
    totalWeight: number; components: GradeComponent[]; createdAt: string | null;
};

type Props = {
    shell: ShellProps;
    grade: Grade;
    can: { update: boolean; delete: boolean };
    urls: {
        index: string; edit: string; destroy: string;
        finalize: string; publish: string; unpublish: string;
    };
    flash?: { success?: string | null; error?: string | null };
};

export default function StudentGradeShow({ shell, grade, can, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    const lifecycle = (url: string) => {
        setProcessing(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const rows: [string, React.ReactNode][] = [
        ['Mahasiswa', <b key="s">{grade.student} {grade.nim ? `(${grade.nim})` : ''}</b>],
        ['Prodi', grade.program ?? '-'],
        ['Tahun Akademik', grade.year ?? '-'],
        ['Mata Kuliah', <b key="c">{grade.course}</b>],
        ['Kelas / SKS', `${grade.classLabel ?? '-'} / ${grade.credits ?? '-'}`],
        ['Dosen Penilai', grade.gradedBy ?? '-'],
        ['Dinilai Pada', grade.gradedAt ?? '-'],
        ['Lifecycle', <span key="l" className={`db-badge ${grade.lifecycleTone}`}>{grade.lifecycle}</span>],
        ['Hasil', grade.result ? <span key="r" className={`db-badge ${grade.resultTone}`}>{grade.result}</span> : '-'],
        ['Skor Akhir', <b key="f">{grade.finalScore ?? '-'}</b>],
        ['Huruf / Indeks', `${grade.letter ?? '-'} / ${grade.point ?? '-'}`],
        ['Total Bobot', `${grade.totalWeight.toFixed(2)}%`],
        ['Catatan', grade.notes ?? '-'],
        ['Dibuat', grade.createdAt ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Akademik"
                        title={`Nilai · ${grade.student}`}
                        description={`${grade.course} — ${grade.year ?? '-'}`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>}
                            </>
                        }
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Award size={16} /> Ringkasan Nilai</h2>
                            {can.update && (
                                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    {grade.isDraft && (
                                        <button type="button" className="db-btn primary sm" disabled={processing} onClick={() => lifecycle(urls.finalize)}>
                                            <Stamp size={13} /> Finalize
                                        </button>
                                    )}
                                    {grade.isFinalized && (
                                        <button type="button" className="db-btn primary sm" disabled={processing} onClick={() => lifecycle(urls.publish)}>
                                            <Megaphone size={13} /> Publish
                                        </button>
                                    )}
                                    {grade.isPublished && (
                                        <button type="button" className="db-btn ghost sm" disabled={processing} onClick={() => lifecycle(urls.unpublish)}>
                                            <RotateCcw size={13} /> Batalkan Publish
                                        </button>
                                    )}
                                    {can.delete && (
                                        <button type="button" className="db-btn ghost sm" onClick={() => setConfirmDelete(true)}>
                                            <Trash2 size={13} /> Hapus
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="db-card-body tight">
                            {rows.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <span className="db-hint">{label}</span>
                                    <span>{value}</span>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Komponen Penilaian</h2>
                        </div>
                        <div className="db-card-body">
                            {grade.components.length === 0 ? (
                                <p className="db-hint">Belum ada komponen.</p>
                            ) : (
                                <table className="db-table">
                                    <thead>
                                        <tr><th>No</th><th>Komponen</th><th style={{ textAlign: 'center' }}>Bobot</th><th style={{ textAlign: 'center' }}>Skor</th><th>Catatan</th></tr>
                                    </thead>
                                    <tbody>
                                        {grade.components.map((c, i) => (
                                            <tr key={c.id}>
                                                <td>{i + 1}</td>
                                                <td><b>{c.name}</b></td>
                                                <td style={{ textAlign: 'center' }}>{c.weight !== null ? `${c.weight.toFixed(2)}%` : '-'}</td>
                                                <td style={{ textAlign: 'center' }}><b>{c.score ?? '-'}</b></td>
                                                <td><small className="db-hint">{c.notes ?? '-'}</small></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus nilai?"
                    message="Nilai dihapus (lunak) dan transkrip mahasiswa disinkron ulang."
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={() => {
                        setProcessing(true);
                        router.delete(urls.destroy, {
                            onFinish: () => {
                                setProcessing(false);
                                setConfirmDelete(false);
                            },
                        });
                    }}
                    onCancel={() => setConfirmDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
