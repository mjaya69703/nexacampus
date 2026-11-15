// Form nilai mahasiswa — create (header) + edit (header, komponen, lifecycle).
import { Head, router, useForm } from '@inertiajs/react';
import { Award, ListOrdered, Megaphone, RotateCcw, Stamp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
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
    mode: 'create' | 'edit';
    form: { study_plan_detail_id: number | null; graded_by: number | null; notes: string };
    grade?: Grade;
    detailOptions: AsyncOption[];
    graderOptions: AsyncOption[];
    urls: {
        index: string; submit: string; show?: string;
        finalize?: string; publish?: string; unpublish?: string;
        searchDetails: string; graderOptions: string; componentStore?: string;
    };
    flash?: { success?: string | null; error?: string | null };
};

export default function StudentGradeForm({ shell, mode, form: initial, grade, detailOptions, graderOptions: initialGraders, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        study_plan_detail_id: initial.study_plan_detail_id ? String(initial.study_plan_detail_id) : '',
        graded_by: initial.graded_by ? String(initial.graded_by) : '',
        notes: initial.notes ?? '',
    });
    const [detail, setDetail] = useState<AsyncOption | null>(detailOptions[0] ?? null);
    const [graders, setGraders] = useState<AsyncOption[]>(initialGraders);
    const [deleteRow, setDeleteRow] = useState<GradeComponent | null>(null);
    const [processing, setProcessing] = useState(false);

    const componentForm = useForm({ name: '', weight_percentage: '', score: '', sort_order: '', notes: '' });
    const [editingComponent, setEditingComponent] = useState<GradeComponent | null>(null);
    const [showComponentForm, setShowComponentForm] = useState(false);

    useEffect(() => {
        if (isCreate || !detail) return;
        fetch(`${urls.graderOptions}?detail=${detail.id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => r.json())
            .then((json) => setGraders(json.options ?? []))
            .catch(() => setGraders([]));
    }, [detail?.id]);

    const pickDetail = (option: AsyncOption | null) => {
        setDetail(option);
        form.setData('study_plan_detail_id', option ? String(option.id) : '');
        form.setData('graded_by', '');
        if (isCreate) {
            fetch(`${urls.graderOptions}?detail=${option?.id ?? ''}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => r.json())
                .then((json) => setGraders(json.options ?? []))
                .catch(() => setGraders([]));
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit, { preserveScroll: true });
        }
    };

    const openAddComponent = () => {
        componentForm.setData({ name: '', weight_percentage: '', score: '', sort_order: '', notes: '' });
        componentForm.clearErrors();
        setEditingComponent(null);
        setShowComponentForm(true);
    };

    const openEditComponent = (row: GradeComponent) => {
        componentForm.setData({
            name: row.name,
            weight_percentage: row.weight === null ? '' : String(row.weight),
            score: row.score === null ? '' : String(row.score),
            sort_order: row.sortOrder === null ? '' : String(row.sortOrder),
            notes: row.notes ?? '',
        });
        componentForm.clearErrors();
        setEditingComponent(row);
        setShowComponentForm(true);
    };

    const submitComponent = () => {
        if (editingComponent) {
            componentForm.put(editingComponent.updateUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingComponent(null);
                    setShowComponentForm(false);
                },
            });
        } else if (urls.componentStore) {
            componentForm.post(urls.componentStore, {
                preserveScroll: true,
                onSuccess: () => setShowComponentForm(false),
            });
        }
    };

    const confirmDeleteComponent = () => {
        if (!deleteRow) return;
        setProcessing(true);
        router.delete(deleteRow.deleteUrl, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setDeleteRow(null);
            },
        });
    };

    const lifecycle = (url?: string, label = '') => {
        if (!url) return;
        setProcessing(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const errors = form.errors as Record<string, string>;
    const componentErrors = componentForm.errors as Record<string, string>;
    const locked = grade?.isPublished ?? false;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Akademik"
                        title={isCreate ? 'Buat Nilai Mahasiswa' : `Edit Nilai · ${grade?.student ?? ''}`}
                        description={isCreate
                            ? 'Simpan header dulu, lalu susun komponen di halaman edit.'
                            : 'Nilai Published dikunci — batalkan publish dulu untuk mengoreksi.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Award size={16} /> Header Nilai</h2>
                            {!isCreate && urls.show && <a className="db-btn ghost sm" href={urls.show}>Detail</a>}
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        {isCreate || (!locked && (grade?.isDraft ?? true) && (grade?.components.length ?? 0) === 0) ? (
                                            <AsyncSelect
                                                label="Detail KRS"
                                                required
                                                fetchUrl={urls.searchDetails}
                                                value={detail}
                                                placeholder="Ketik NIM, nama, atau kode MK…"
                                                onChange={pickDetail}
                                                error={errors.study_plan_detail_id}
                                                hint="Hanya detail KRS terdaftar resmi yang belum punya nilai."
                                            />
                                        ) : (
                                            <div>
                                                <span className="crud-label">Detail KRS</span>
                                                <div className="db-input" style={{ background: 'var(--db-muted)' }}>
                                                    {grade?.student} ({grade?.nim ?? '-'}) — {grade?.course}
                                                </div>
                                                <div className="crud-hint">Kaitan KRS dikunci karena komponen sudah ada / status bukan Draft.</div>
                                            </div>
                                        )}
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="graded_by">Dosen Penilai</label>
                                        <select
                                            id="graded_by"
                                            className="db-input"
                                            value={form.data.graded_by}
                                            disabled={locked || graders.length === 0}
                                            onChange={(e) => form.setData('graded_by', e.target.value)}
                                        >
                                            <option value="">Pilih penilai (dosen offering)</option>
                                            {graders.map((g) => <option key={g.id} value={g.id}>{g.label}</option>)}
                                        </select>
                                        {errors.graded_by && <div className="db-error">{errors.graded_by}</div>}
                                    </div>
                                    <div>
                                        <span className="crud-label">Lifecycle</span>
                                        <div>
                                            {grade
                                                ? <span className={`db-badge ${grade.lifecycleTone}`}>{grade.lifecycle}</span>
                                                : <span className="db-badge">Draft</span>}
                                            {grade?.result && <span className={`db-badge ${grade.resultTone}`} style={{ marginLeft: 8 }}>{grade.result}</span>}
                                        </div>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label className="crud-label" htmlFor="notes">Catatan Evaluasi</label>
                                        <textarea
                                            id="notes"
                                            className="db-input"
                                            rows={2}
                                            value={form.data.notes}
                                            disabled={locked}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                        />
                                        {errors.notes && <div className="db-error">{errors.notes}</div>}
                                    </div>
                                </div>
                                {!locked && (
                                    <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 16 }}>
                                        <button type="submit" className="db-btn primary" disabled={form.processing}>
                                            {isCreate ? 'Buat & Isi Komponen' : 'Simpan Header'}
                                        </button>
                                    </div>
                                )}
                            </form>

                            {!isCreate && grade && (
                                <div className="crud-grid" style={{ marginTop: 16 }}>
                                    <div><span className="crud-label">Total Bobot</span><b>{grade.totalWeight.toFixed(2)}%</b></div>
                                    <div><span className="crud-label">Skor Akhir</span><b>{grade.finalScore ?? '-'}</b></div>
                                    <div><span className="crud-label">Huruf / Indeks</span><b>{grade.letter ?? '-'} / {grade.point ?? '-'}</b></div>
                                    <div>
                                        <span className="crud-label">Aksi Lifecycle</span>
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
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>

                    {!isCreate && grade && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><ListOrdered size={16} /> Komponen Penilaian</h2>
                                {!locked && (
                                    <button type="button" className="db-btn ghost sm" onClick={openAddComponent}>
                                        Tambah Komponen
                                    </button>
                                )}
                            </div>
                            <div className="db-card-body">
                                {locked && (
                                    <p className="db-hint" style={{ marginBottom: 12 }}>
                                        Nilai Published dikunci. Batalkan publish untuk mengoreksi, atau lanjutkan lewat keberatan nilai.
                                    </p>
                                )}
                                {showComponentForm && !locked && (
                                    <div className="db-card" style={{ marginBottom: 12 }}>
                                        <div className="db-card-body">
                                            <div className="crud-grid">
                                                <div>
                                                    <label className="crud-label">Nama Komponen *</label>
                                                    <input
                                                        className="db-input"
                                                        value={componentForm.data.name}
                                                        placeholder="Tugas / Kuis / UTS / UAS"
                                                        onChange={(e) => componentForm.setData('name', e.target.value)}
                                                    />
                                                    {componentErrors.name && <div className="db-error">{componentErrors.name}</div>}
                                                </div>
                                                <div>
                                                    <label className="crud-label">Bobot (%)</label>
                                                    <input
                                                        className="db-input"
                                                        type="number" step="0.01" min={0} max={100}
                                                        value={componentForm.data.weight_percentage}
                                                        onChange={(e) => componentForm.setData('weight_percentage', e.target.value)}
                                                    />
                                                    {componentErrors.weight_percentage && <div className="db-error">{componentErrors.weight_percentage}</div>}
                                                </div>
                                                <div>
                                                    <label className="crud-label">Skor (0–100)</label>
                                                    <input
                                                        className="db-input"
                                                        type="number" step="0.01" min={0} max={100}
                                                        value={componentForm.data.score}
                                                        onChange={(e) => componentForm.setData('score', e.target.value)}
                                                    />
                                                    {componentErrors.score && <div className="db-error">{componentErrors.score}</div>}
                                                </div>
                                                <div>
                                                    <label className="crud-label">Urutan</label>
                                                    <input
                                                        className="db-input"
                                                        type="number" min={0}
                                                        value={componentForm.data.sort_order}
                                                        onChange={(e) => componentForm.setData('sort_order', e.target.value)}
                                                    />
                                                </div>
                                                <div style={{ gridColumn: '1 / -1' }}>
                                                    <label className="crud-label">Catatan</label>
                                                    <input
                                                        className="db-input"
                                                        value={componentForm.data.notes}
                                                        onChange={(e) => componentForm.setData('notes', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 12 }}>
                                                <button type="button" className="db-btn ghost sm" onClick={() => { setEditingComponent(null); setShowComponentForm(false); }}>Batal</button>
                                                <button
                                                    type="button"
                                                    className="db-btn primary sm"
                                                    disabled={componentForm.processing}
                                                    onClick={submitComponent}
                                                >
                                                    Simpan Komponen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {grade.components.length === 0 ? (
                                    <p className="db-hint">Belum ada komponen. Tambahkan bobot + skor, lalu finalize saat total 100%.</p>
                                ) : (
                                    <div className="db-table-wrap"><table className="db-table">
                                        <thead>
                                            <tr><th>No</th><th>Komponen</th><th style={{ textAlign: 'center' }}>Bobot</th><th style={{ textAlign: 'center' }}>Skor</th><th>Catatan</th><th style={{ textAlign: 'right' }}>Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            {grade.components.map((c, i) => (
                                                <tr key={c.id}>
                                                    <td>{i + 1}</td>
                                                    <td><b>{c.name}</b></td>
                                                    <td style={{ textAlign: 'center' }}>{c.weight !== null ? `${c.weight.toFixed(2)}%` : '-'}</td>
                                                    <td style={{ textAlign: 'center' }}><b>{c.score ?? '-'}</b></td>
                                                    <td><small className="db-hint">{c.notes ?? '-'}</small></td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        {!locked && (
                                                            <>
                                                                <button type="button" className="db-btn ghost sm" onClick={() => openEditComponent(c)}>Ubah</button>{' '}
                                                                <button type="button" className="db-btn ghost sm" onClick={() => setDeleteRow(c)}>Hapus</button>
                                                            </>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table></div>
                                )}
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={deleteRow !== null}
                    title="Hapus komponen?"
                    message={`Komponen "${deleteRow?.name}" dihapus dan snapshot dihitung ulang.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteComponent}
                    onCancel={() => setDeleteRow(null)}
                />
            </div>
        </AdminShell>
    );
}
