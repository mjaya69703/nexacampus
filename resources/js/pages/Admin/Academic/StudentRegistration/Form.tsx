// Form tambah/ubah registrasi mahasiswa.
import { Head, useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    registration: {
        id?: number;
        student_profile_id: number | string;
        student_label?: string;
        academic_year_id: number | string;
        semester_no: number | string;
        academic_status: string;
        registration_status: string;
        notes: string;
        is_active: boolean;
        submitted_at?: string | null;
        approved_at?: string | null;
        approved_by?: string | null;
    };
    years: { id: number; name: string }[];
    academicStatuses: string[];
    registrationStatuses: string[];
    urls: {
        index: string; submit: string; show?: string; approve?: string;
        searchStudents: string;
    };
};

export default function StudentRegistrationForm({ shell, mode, registration, years, academicStatuses, registrationStatuses, urls }: Props) {
    const isCreate = mode === 'create';
    const [student, setStudent] = useState<AsyncOption | null>(
        registration.student_profile_id
            ? { id: Number(registration.student_profile_id), label: registration.student_label ?? '' }
            : null,
    );

    const form = useForm({
        student_profile_id: registration.student_profile_id as number | '',
        academic_year_id: registration.academic_year_id as number | '',
        semester_no: registration.semester_no,
        academic_status: registration.academic_status,
        registration_status: registration.registration_status,
        notes: registration.notes,
        is_active: registration.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Registrasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardCheck}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Registrasi' : 'Edit Registrasi'}
                        description="Satu mahasiswa satu registrasi per tahun. Approval ada di halaman daftar/detail."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardCheck size={16} /> Form Registrasi</h2>
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
                                                onChange={(o) => {
                                                    setStudent(o);
                                                    form.setData('student_profile_id', o ? Number(o.id) : '');
                                                }}
                                                placeholder="Ketik NIM/nama…"
                                                error={errors.student_profile_id}
                                            />
                                        ) : (
                                            <div>
                                                <span className="crud-label">Mahasiswa</span>
                                                <div><span className="db-badge">{registration.student_label}</span></div>
                                            </div>
                                        )}
                                    </div>
                                    <SelectField
                                        label="Tahun akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.academic_year_id}
                                        disabled={!isCreate}
                                        hint={!isCreate ? 'Tahun tidak bisa diubah setelah dibuat.' : undefined}
                                    >
                                        <option value="">— Pilih —</option>
                                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester"
                                        type="number"
                                        value={form.data.semester_no}
                                        onChange={(e) => form.setData('semester_no', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.semester_no}
                                        hint="Dorong ke profil saat disetujui."
                                    />
                                    <SelectField
                                        label="Status akademik"
                                        required
                                        value={form.data.academic_status}
                                        onChange={(e) => form.setData('academic_status', e.target.value)}
                                        error={errors.academic_status}
                                    >
                                        {academicStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                    </SelectField>
                                    {isCreate ? (
                                        <SelectField
                                            label="Status registrasi"
                                            required
                                            value={form.data.registration_status}
                                            onChange={(e) => form.setData('registration_status', e.target.value)}
                                            error={errors.registration_status}
                                        >
                                            {registrationStatuses.map((status) => <option key={status} value={status}>{status}</option>)}
                                        </SelectField>
                                    ) : (
                                        <div>
                                            <span className="crud-label">Status registrasi</span>
                                            <div><span className="db-badge">{registration.registration_status}</span></div>
                                            <div className="crud-hint">Diubah lewat approval, bukan form ini.</div>
                                        </div>
                                    )}
                                    <SwitchField
                                        label="Aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                {!isCreate && (registration.submitted_at || registration.approved_at) && (
                                    <div className="db-note info" style={{ marginTop: 14 }}>
                                        <span>
                                            {registration.submitted_at && <>Diajukan {registration.submitted_at}. </>}
                                            {registration.approved_at && <>Disetujui {registration.approved_at}{registration.approved_by ? ` oleh ${registration.approved_by}` : ''}.</>}
                                        </span>
                                    </div>
                                )}

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Registrasi' : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
