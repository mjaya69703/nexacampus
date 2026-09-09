// Form kurikulum — header + kelola MK per semester.
import { Head, router, useForm } from '@inertiajs/react';
import { GraduationCap, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type CourseRow = {
    id: number; courseId: number; code: string | null; name: string | null;
    credits: number | null; creditsOverride: number | null;
    semesterRecommendation: number | null;
    semester: number | null; required: boolean;
    sort: number; notes: string | null; isActive: boolean;
    updateUrl: string; deleteUrl: string;
};

type SemesterGroup = { semester: string; courses: CourseRow[]; sks: number; count: number };

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    curriculum: {
        id?: number; study_program_id: number | string; name: string; code: string;
        start_year: number | string; end_year: number | string;
        is_active: boolean; desc: string;
    };
    programs: { id: number; name: string }[];
    courses?: SemesterGroup[];
    courseOptions?: { id: number; label: string }[];
    urls: {
        index: string; submit: string; show?: string;
        courseStore?: string; courseBulkDestroy?: string; searchCourses?: string;
    };
};

export default function CurriculumForm({ shell, mode, curriculum, programs, courses = [], urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        study_program_id: curriculum.study_program_id,
        name: curriculum.name,
        code: curriculum.code,
        start_year: curriculum.start_year,
        end_year: curriculum.end_year,
        is_active: curriculum.is_active,
        desc: curriculum.desc,
    });

    const [addCourse, setAddCourse] = useState<(AsyncOption & { semester?: number | null }) | null>(null);
    const [addSemester, setAddSemester] = useState('');
    const [addRequired, setAddRequired] = useState(true);
    const [rowSelected, setRowSelected] = useState<number[]>([]);
    const [editingRow, setEditingRow] = useState<CourseRow | null>(null);
    const [deleteRow, setDeleteRow] = useState<CourseRow | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const editRowForm = useForm({
        course_id: 0,
        semester_no: '',
        is_required: true,
        sort_order: 0,
        credits_override: '',
        notes: '',
        is_active: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const submitAddCourse = () => {
        if (!addCourse || !urls.courseStore) return;
        setProcessing(true);
        router.post(urls.courseStore, {
            course_id: Number(addCourse.id),
            semester_no: addSemester === '' ? null : Number(addSemester),
            is_required: addRequired,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setAddCourse(null);
                setAddSemester('');
            },
        });
    };

    const openEditRow = (row: CourseRow) => {
        editRowForm.setData({
            course_id: row.courseId,
            semester_no: row.semester === null ? '' : String(row.semester),
            is_required: row.required,
            sort_order: row.sort,
            credits_override: row.creditsOverride === null ? '' : String(row.creditsOverride),
            notes: row.notes ?? '',
            is_active: row.isActive,
        });
        editRowForm.clearErrors();
        setEditingRow(row);
    };

    const submitEditRow = () => {
        if (!editingRow) return;
        editRowForm.put(editingRow.updateUrl, {
            preserveScroll: true,
            onSuccess: () => setEditingRow(null),
        });
    };

    const confirmDeleteRow = () => {
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

    const confirmBulkRows = () => {
        if (!urls.courseBulkDestroy) return;
        setProcessing(true);
        router.post(urls.courseBulkDestroy, { ids: rowSelected }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setBulkOpen(false);
                setRowSelected([]);
            },
        });
    };

    const toggleRowSelected = (id: number) => {
        setRowSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const allRowIds = courses.flatMap((g) => g.courses.map((c) => c.id));
    const errors = form.errors as Record<string, string>;

    const headerCard = (
        <section className={`db-card${!isCreate ? ' cx-sticky' : ''}`}>
            <div className="db-card-head">
                <h2 className="db-card-title"><GraduationCap size={16} /> Data Kurikulum</h2>
                {!isCreate && urls.show && <a className="db-btn ghost sm" href={urls.show}>Pratinjau</a>}
            </div>
            <div className="db-card-body">
                <form onSubmit={submit}>
                    <div className="crud-grid" style={!isCreate ? { gridTemplateColumns: '1fr' } : undefined}>
                        <SelectField
                            label="Program studi"
                            required
                            value={form.data.study_program_id}
                            onChange={(e) => form.setData('study_program_id', e.target.value === '' ? '' : Number(e.target.value))}
                            error={errors.study_program_id}
                        >
                            <option value="">— Pilih —</option>
                                        {programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                                    </SelectField>
                                    <TextField
                            label="Nama kurikulum"
                            required
                            placeholder="Contoh: Kurikulum 2024"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={errors.name}
                            hint="Unik per prodi."
                        />
                        <TextField
                            label="Kode"
                            placeholder="Contoh: K24"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={errors.code}
                        />
                        <TextField
                            label="Tahun mulai"
                            type="number"
                            value={form.data.start_year}
                            onChange={(e) => form.setData('start_year', e.target.value === '' ? '' : Number(e.target.value))}
                            error={errors.start_year}
                        />
                        <TextField
                            label="Tahun selesai"
                            type="number"
                            value={form.data.end_year}
                            onChange={(e) => form.setData('end_year', e.target.value === '' ? '' : Number(e.target.value))}
                            error={errors.end_year}
                        />
                        <SwitchField
                            label="Status aktif"
                            checked={form.data.is_active}
                            onChange={(v) => form.setData('is_active', v)}
                            error={errors.is_active}
                        />
                        <div style={{ gridColumn: '1 / -1' }}>
                            <TextareaField
                                label="Deskripsi"
                                value={form.data.desc}
                                onChange={(e) => form.setData('desc', e.target.value)}
                                error={errors.desc}
                            />
                        </div>
                    </div>

                    <FormActions
                        cancelHref={urls.index}
                        submitLabel={isCreate ? 'Simpan & Lanjutkan' : 'Simpan Perubahan'}
                        processing={form.processing}
                    />
                </form>
            </div>
        </section>
    );

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kurikulum · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Kurikulum' : `Edit ${curriculum.name}`}
                        description={isCreate ? 'Simpan dulu, lalu susun MK per semester.' : 'Kelola header dan susunan MK per semester.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    {isCreate ? headerCard : (
                    <div className="cx-form-split">
                    {headerCard}
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Susunan Mata Kuliah</h2>
                                {rowSelected.length > 0 && (
                                    <button className="db-btn danger sm" type="button" onClick={() => setBulkOpen(true)}>
                                        <Trash2 size={13} /> Hapus {rowSelected.length} baris
                                    </button>
                                )}
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 16 }}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <AsyncSelect
                                            label="Tambah MK"
                                            fetchUrl={urls.searchCourses ?? ''}
                                            value={addCourse}
                                            onChange={setAddCourse}
                                            placeholder="Ketik kode/nama MK aktif…"
                                        />
                                    </div>
                                    <SelectField
                                        label="Semester"
                                        value={addSemester}
                                        onChange={(e) => setAddSemester(e.target.value)}
                                        hint={addCourse && addCourse.semester != null ? `Rekomendasi MK ini: Smt ${addCourse.semester}.` : undefined}
                                    >
                                        <option value="">Tanpa semester</option>
                                        {[1, 2, 3, 4, 5, 6, 7, 8].map((s) => <option key={s} value={s}>Semester {s}</option>)}
                                    </SelectField>
                                    {addCourse && addCourse.semester != null && addSemester !== '' && Number(addSemester) !== addCourse.semester && (
                                        <div className="db-error" style={{ gridColumn: '1 / -1' }}>
                                            Perhatian: {addCourse.label} direkomendasikan di Smt {addCourse.semester}, bukan Smt {addSemester}. Tetap lanjut bila memang disengaja.
                                        </div>
                                    )}
                                    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10 }}>
                                        <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13 }}>
                                            <input type="checkbox" checked={addRequired} onChange={(e) => setAddRequired(e.target.checked)} style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }} />
                                            Wajib
                                        </label>
                                        <button className="db-btn primary sm" type="button" disabled={!addCourse || processing} onClick={submitAddCourse}>
                                            <Plus size={14} /> Tambah
                                        </button>
                                    </div>
                                </div>

                                {courses.length === 0 && (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada MK. Cari dan tambahkan dari panel di atas.</p>
                                )}
                                {courses.map((group) => (
                                    <div key={group.semester}>
                                        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginBottom: 8 }}>
                                            <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{group.semester}</b>
                                            <small className="db-hint">{group.count} MK · {group.sks} SKS</small>
                                        </div>
                                        <div className="crud-table-wrap">
                                            <table className="crud-table">
                                                <thead>
                                                    <tr>
                                                        <th style={{ width: 36 }}>
                                                            <input
                                                                type="checkbox"
                                                                checked={group.courses.length > 0 && group.courses.every((c) => rowSelected.includes(c.id))}
                                                                onChange={() => {
                                                                    const ids = group.courses.map((c) => c.id);
                                                                    setRowSelected((prev) => (ids.every((id) => prev.includes(id))
                                                                        ? prev.filter((id) => !ids.includes(id))
                                                                        : [...new Set([...prev, ...ids])]));
                                                                }}
                                                                aria-label={`Pilih ${group.semester}`}
                                                            />
                                                        </th>
                                                        <th>Kode</th>
                                                        <th>Mata kuliah</th>
                                                        <th style={{ textAlign: 'right' }}>SKS</th>
                                                        <th>Wajib</th>
                                                        <th style={{ textAlign: 'right' }}>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {group.courses.map((row) => (
                                                        <tr key={row.id}>
                                                            <td>
                                                                <input type="checkbox" checked={rowSelected.includes(row.id)} onChange={() => toggleRowSelected(row.id)} aria-label={`Pilih ${row.code}`} />
                                                            </td>
                                                            <td><span className="crud-name">{row.code ?? '-'}</span></td>
                                                            <td>{row.name ?? '-'}</td>
                                                            <td style={{ textAlign: 'right' }}>{row.credits ?? '-'}</td>
                                                            <td>{row.required ? <span className="db-badge">Wajib</span> : <span className="db-badge gray">Pilihan</span>}</td>
                                                            <td>
                                                                <div className="crud-row-actions">
                                                                    <button className="db-btn ghost sm" type="button" onClick={() => openEditRow(row)} title="Ubah baris">
                                                                        <Pencil size={13} />
                                                                    </button>
                                                                    <button className="db-btn danger sm" type="button" onClick={() => setDeleteRow(row)} title="Hapus baris">
                                                                        <Trash2 size={13} />
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                    )}
                </div>

                {editingRow && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setEditingRow(null)} role="dialog" aria-modal="true" aria-label="Ubah baris MK">
                            <div className="db-modal" style={{ width: 'min(28rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Ubah baris — {editingRow.code}</h3>
                                <p>Semester, urutan, SKS override, dan status baris ini.</p>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Semester"
                                            value={String(editRowForm.data.semester_no)}
                                            onChange={(e) => editRowForm.setData('semester_no', e.target.value)}
                                            error={(editRowForm.errors as Record<string, string>).semester_no}
                                            hint={editingRow?.semesterRecommendation != null ? `Rekomendasi MK ini: Smt ${editingRow.semesterRecommendation}.` : undefined}
                                        >
                                            <option value="">Tanpa semester</option>
                                            {[1, 2, 3, 4, 5, 6, 7, 8].map((s) => <option key={s} value={s}>Semester {s}</option>)}
                                        </SelectField>
                                        {editingRow?.semesterRecommendation != null && editRowForm.data.semester_no !== '' && Number(editRowForm.data.semester_no) !== editingRow.semesterRecommendation && (
                                            <div className="db-error" style={{ gridColumn: '1 / -1' }}>
                                                Perhatian: rekomendasi MK ini Smt {editingRow.semesterRecommendation}. Tetap lanjut bila memang disengaja.
                                            </div>
                                        )}
                                        <TextField
                                            label="Urutan"
                                            type="number"
                                            value={editRowForm.data.sort_order}
                                            onChange={(e) => editRowForm.setData('sort_order', Number(e.target.value))}
                                            error={(editRowForm.errors as Record<string, string>).sort_order}
                                        />
                                        <TextField
                                            label="SKS override"
                                            type="number"
                                            value={editRowForm.data.credits_override}
                                            onChange={(e) => editRowForm.setData('credits_override', e.target.value)}
                                            error={(editRowForm.errors as Record<string, string>).credits_override}
                                            hint="Kosongkan untuk memakai SKS MK."
                                        />
                                        <div style={{ display: 'flex', alignItems: 'flex-end', gap: 14, flexWrap: 'wrap' }}>
                                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                                                <input type="checkbox" checked={editRowForm.data.is_required} onChange={(e) => editRowForm.setData('is_required', e.target.checked)} style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }} />
                                                Wajib
                                            </label>
                                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                                                <input type="checkbox" checked={editRowForm.data.is_active} onChange={(e) => editRowForm.setData('is_active', e.target.checked)} style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }} />
                                                Aktif
                                            </label>
                                        </div>
                                    </div>
                                    <TextareaField
                                        label="Catatan"
                                        value={editRowForm.data.notes}
                                        onChange={(e) => editRowForm.setData('notes', e.target.value)}
                                        error={(editRowForm.errors as Record<string, string>).notes}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setEditingRow(null)}>Batal</button>
                                    <button className="db-btn primary" type="button" onClick={submitEditRow} disabled={editRowForm.processing}>
                                        {editRowForm.processing ? 'Menyimpan…' : 'Simpan'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <ConfirmModal
                    open={deleteRow !== null}
                    title="Hapus baris MK?"
                    message={deleteRow ? `${deleteRow.code ?? ''} dikeluarkan dari kurikulum.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteRow}
                    onCancel={() => setDeleteRow(null)}
                />

                <ConfirmModal
                    open={bulkOpen}
                    title={`Hapus ${rowSelected.length} baris?`}
                    message="Baris terpilih dikeluarkan dari kurikulum."
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmBulkRows}
                    onCancel={() => setBulkOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
