// Form KRS — header + kelola detail MK per offering.
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type DetailRow = {
    id: number; offeringId: number; code: string | null; name: string | null;
    offeringLabel: string | null; credits: number | null; isRepeat: boolean;
    status: string; notes: string | null;
    updateUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    plan: {
        id?: number; student_profile_id: number | string; student_label?: string;
        academic_year_id: number | string; student_registration_id: number | string;
        semester_no: number | string; status: string; notes: string;
        submitted_at?: string | null; approved_at?: string | null; approved_by?: string | null;
    };
    years: { id: number; name: string }[];
    statuses: string[];
    detailStatuses?: string[];
    details?: DetailRow[];
    programId?: number | null;
    registrationOptions?: { id: number; label: string }[];
    urls: {
        index: string; submit: string; show?: string;
        searchStudents: string; registrationOptions: string; offeringOptions: string;
        detailStore?: string; detailBulkDestroy?: string;
    };
};

const DETAIL_STATUSES = ['Draft', 'Taken', 'Dropped', 'Cancelled'];

export default function StudyPlanForm({ shell, mode, plan, years, statuses, detailStatuses = DETAIL_STATUSES, details = [], programId = null, registrationOptions = [], urls }: Props) {
    const isCreate = mode === 'create';
    const [student, setStudent] = useState<AsyncOption | null>(
        plan.student_profile_id ? { id: Number(plan.student_profile_id), label: plan.student_label ?? '' } : null,
    );
    const [registrations, setRegistrations] = useState<{ id: number; label: string }[]>(registrationOptions);
    const [addOffering, setAddOffering] = useState<AsyncOption | null>(null);
    const [addStatus, setAddStatus] = useState('Taken');
    const [rowSelected, setRowSelected] = useState<number[]>([]);
    const [editingRow, setEditingRow] = useState<DetailRow | null>(null);
    const [deleteRow, setDeleteRow] = useState<DetailRow | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const form = useForm({
        student_profile_id: plan.student_profile_id as number | '',
        academic_year_id: plan.academic_year_id as number | '',
        student_registration_id: plan.student_registration_id as number | '',
        semester_no: plan.semester_no,
        status: plan.status,
        notes: plan.notes,
    });

    const detailForm = useForm({
        course_offering_id: 0,
        credits: '',
        is_repeat: false,
        status: 'Taken',
        notes: '',
    });

    const loadRegistrations = async (studentId: number | '', yearId: number | '') => {
        if (!studentId || !yearId) {
            setRegistrations([]);
            return;
        }
        const response = await fetch(`${urls.registrationOptions}?student=${studentId}&year=${yearId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json();
        setRegistrations(json.options ?? []);
    };

    const pickStudent = (option: AsyncOption | null) => {
        setStudent(option);
        const id = option ? Number(option.id) : '';
        form.setData('student_profile_id', id);
        form.setData('student_registration_id', '');
        if (id && form.data.academic_year_id) {
            loadRegistrations(id, Number(form.data.academic_year_id));
        } else {
            setRegistrations([]);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const submitAddDetail = () => {
        if (!addOffering || !urls.detailStore) return;
        setProcessing(true);
        const selected = offerings.find((o) => Number(o.id) === Number(addOffering.id));
        router.post(urls.detailStore, {
            course_offering_id: Number(addOffering.id),
            credits: selected?.credits ?? null,
            status: addStatus,
        }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setAddOffering(null);
            },
        });
    };

    const openEditRow = (row: DetailRow) => {
        detailForm.setData({
            course_offering_id: row.offeringId,
            credits: row.credits === null ? '' : String(row.credits),
            is_repeat: row.isRepeat,
            status: row.status,
            notes: row.notes ?? '',
        });
        detailForm.clearErrors();
        setEditingRow(row);
    };

    const submitEditRow = () => {
        if (!editingRow) return;
        detailForm.put(editingRow.updateUrl, {
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
        if (!urls.detailBulkDestroy) return;
        setProcessing(true);
        router.post(urls.detailBulkDestroy, { ids: rowSelected }, {
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

    const errors = form.errors as Record<string, string>;
    const detailErrors = detailForm.errors as Record<string, string>;
    const totalSks = details.reduce((sum, row) => sum + (row.credits ?? 0), 0);

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} KRS · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik"
                        title={isCreate ? 'Buat KRS' : `Edit KRS ${plan.student_label ?? ''}`}
                        description={isCreate ? 'Simpan header dulu, lalu susun MK.' : 'Perubahan status mengirim notifikasi ke mahasiswa.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Data KRS</h2>
                            {!isCreate && urls.show && <a className="db-btn ghost sm" href={urls.show}>Detail</a>}
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        {isCreate ? (
                                            <AsyncSelect
                                                label="Mahasiswa"
                                                required
                                                fetchUrl={urls.searchStudents}
                                                value={student}
                                                onChange={pickStudent}
                                                placeholder="Ketik NIM/nama…"
                                                error={errors.student_profile_id}
                                            />
                                        ) : (
                                            <div>
                                                <span className="crud-label">Mahasiswa</span>
                                                <div><span className="db-badge">{plan.student_label}</span></div>
                                            </div>
                                        )}
                                    </div>
                                    <SelectField
                                        label="Tahun akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => {
                                            const value = e.target.value === '' ? '' : Number(e.target.value);
                                            form.setData('academic_year_id', value);
                                            form.setData('student_registration_id', '');
                                            if (form.data.student_profile_id && value) {
                                                loadRegistrations(Number(form.data.student_profile_id), Number(value));
                                            } else {
                                                setRegistrations([]);
                                            }
                                        }}
                                        error={errors.academic_year_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Registrasi terkait"
                                        value={form.data.student_registration_id}
                                        onChange={(e) => form.setData('student_registration_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.student_registration_id}
                                        hint="Opsional. Terisi otomatis bila mahasiswa + tahun dipilih."
                                    >
                                        <option value="">— Tanpa kaitan —</option>
                                        {registrations.map((registration) => <option key={registration.id} value={registration.id}>{registration.label}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester"
                                        type="number"
                                        value={form.data.semester_no}
                                        onChange={(e) => form.setData('semester_no', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.semester_no}
                                    />
                                    <SelectField
                                        label="Status"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={errors.status}
                                        hint="Perubahan status mengirim notifikasi."
                                    >
                                        {statuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </SelectField>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                {!isCreate && (plan.submitted_at || plan.approved_at) && (
                                    <div className="db-note info" style={{ marginTop: 14 }}>
                                        <span>
                                            {plan.submitted_at && <>Diajukan {plan.submitted_at}. </>}
                                            {plan.approved_at && <>Disetujui {plan.approved_at}{plan.approved_by ? ` oleh ${plan.approved_by}` : ''}.</>}
                                        </span>
                                    </div>
                                )}

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan & Lanjutkan' : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>

                    {!isCreate && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Mata Kuliah ({details.length} MK · {totalSks} SKS)</h2>
                                {rowSelected.length > 0 && (
                                    <button className="db-btn danger sm" type="button" onClick={() => setBulkOpen(true)}>
                                        <Trash2 size={13} /> Hapus {rowSelected.length} baris
                                    </button>
                                )}
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <AsyncSelect
                                            label="Tambah MK dari penawaran"
                                            fetchUrl={`${urls.offeringOptions}?year=${form.data.academic_year_id}${programId ? `&program=${programId}` : ''}`}
                                            value={addOffering}
                                            onChange={setAddOffering}
                                            placeholder="Ketik kode/nama MK…"
                                        />
                                    </div>
                                    <SelectField
                                        label="Status baris"
                                        value={addStatus}
                                        onChange={(e) => setAddStatus(e.target.value)}
                                    >
                                        {detailStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </SelectField>
                                    <div style={{ display: 'flex', alignItems: 'flex-end' }}>
                                        <button className="db-btn primary sm" type="button" disabled={!addOffering || processing} onClick={submitAddDetail}>
                                            <Plus size={14} /> Tambah
                                        </button>
                                    </div>
                                </div>

                                {details.length === 0 && (
                                    <p className="db-hint" style={{ margin: 0 }}>Belum ada MK. Tambahkan dari penawaran yang terbuka.</p>
                                )}
                                {details.length > 0 && (
                                    <div className="crud-table-wrap">
                                        <table className="crud-table">
                                            <thead>
                                                <tr>
                                                    <th style={{ width: 36 }}>
                                                        <input
                                                            type="checkbox"
                                                            checked={details.every((d) => rowSelected.includes(d.id))}
                                                            onChange={() => {
                                                                const ids = details.map((d) => d.id);
                                                                setRowSelected((prev) => (ids.every((id) => prev.includes(id))
                                                                    ? prev.filter((id) => !ids.includes(id))
                                                                    : [...new Set([...prev, ...ids])]));
                                                            }}
                                                            aria-label="Pilih semua baris"
                                                        />
                                                    </th>
                                                    <th>Kode</th>
                                                    <th>Mata kuliah</th>
                                                    <th style={{ textAlign: 'right' }}>SKS</th>
                                                    <th>Status</th>
                                                    <th style={{ textAlign: 'right' }}>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {details.map((row) => (
                                                    <tr key={row.id}>
                                                        <td>
                                                            <input type="checkbox" checked={rowSelected.includes(row.id)} onChange={() => toggleRowSelected(row.id)} aria-label={`Pilih ${row.code}`} />
                                                        </td>
                                                        <td><span className="crud-name">{row.code ?? '-'}</span></td>
                                                        <td>{row.name ?? '-'}{row.isRepeat ? ' ' : ''}{row.isRepeat && <span className="db-badge amber">Mengulang</span>}</td>
                                                        <td style={{ textAlign: 'right' }}>{row.credits ?? '-'}</td>
                                                        <td><span className={`db-badge ${row.status === 'Taken' ? 'green' : row.status === 'Dropped' ? 'red' : 'gray'}`}>{row.status}</span></td>
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
                                )}
                            </div>
                        </section>
                    )}
                </div>

                {editingRow && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setEditingRow(null)} role="dialog" aria-modal="true" aria-label="Ubah baris KRS">
                            <div className="db-modal" style={{ width: 'min(28rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Ubah baris — {editingRow.code}</h3>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <div className="crud-grid">
                                        <TextField
                                            label="SKS"
                                            type="number"
                                            value={detailForm.data.credits}
                                            onChange={(e) => detailForm.setData('credits', e.target.value)}
                                            error={(detailForm.errors as Record<string, string>).credits}
                                        />
                                        <SelectField
                                            label="Status"
                                            value={detailForm.data.status}
                                            onChange={(e) => detailForm.setData('status', e.target.value)}
                                            error={(detailForm.errors as Record<string, string>).status}
                                        >
                                            {detailStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                        </SelectField>
                                    </div>
                                    <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                                        <input type="checkbox" checked={detailForm.data.is_repeat} onChange={(e) => detailForm.setData('is_repeat', e.target.checked)} style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }} />
                                        Mengulang
                                    </label>
                                    <TextareaField
                                        label="Catatan"
                                        value={detailForm.data.notes}
                                        onChange={(e) => detailForm.setData('notes', e.target.value)}
                                        error={(detailForm.errors as Record<string, string>).notes}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setEditingRow(null)}>Batal</button>
                                    <button className="db-btn primary" type="button" onClick={submitEditRow} disabled={detailForm.processing}>
                                        {detailForm.processing ? 'Menyimpan…' : 'Simpan'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <ConfirmModal
                    open={deleteRow !== null}
                    title="Hapus baris MK?"
                    message={deleteRow ? `${deleteRow.code ?? ''} dikeluarkan dari KRS.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDeleteRow}
                    onCancel={() => setDeleteRow(null)}
                />

                <ConfirmModal
                    open={bulkOpen}
                    title={`Hapus ${rowSelected.length} baris?`}
                    message="Baris terpilih dikeluarkan dari KRS."
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
