// Form tambah/ubah penugasan jabatan pegawai.
import { Head, useForm } from '@inertiajs/react';
import { BriefcaseBusiness, Building2, GraduationCap, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };
type PositionOption = Option & { scopeType: string; scopeLabel: string };

type AssignmentPayload = {
    id?: number;
    employee: { id: number | null; name: string; email: string | null; employee_number: string | null } | null;
    organizational_position_id: string;
    faculty_id: string;
    study_program_id: string;
    work_unit_id: string;
    starts_at: string;
    ends_at: string;
    is_primary: boolean;
    is_active: boolean;
    notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    assignment: AssignmentPayload;
    options: {
        positions: PositionOption[];
        faculties: Option[];
        studyPrograms: Option[];
        workUnits: Option[];
    };
    urls: { index: string; submit: string; searchEmployees?: string };
};

export default function EmployeePositionAssignmentForm({ shell, mode, assignment, options, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        employee_profile_id: assignment.employee?.id ?? null,
        organizational_position_id: assignment.organizational_position_id,
        faculty_id: assignment.faculty_id,
        study_program_id: assignment.study_program_id,
        work_unit_id: assignment.work_unit_id,
        starts_at: assignment.starts_at,
        ends_at: assignment.ends_at,
        is_primary: assignment.is_primary,
        is_active: assignment.is_active,
        notes: assignment.notes,
    });

    const selectedPosition = options.positions.find((position) => position.id === form.data.organizational_position_id);
    const scopeType = selectedPosition?.scopeType ?? 'none';

    const selectedEmployee: AsyncOption | null = assignment.employee?.id
        ? {
            id: assignment.employee.id,
            label: assignment.employee.name,
            meta: { email: assignment.employee.email, employee_number: assignment.employee.employee_number },
        }
        : form.data.employee_profile_id
            ? { id: form.data.employee_profile_id, label: 'Pegawai terpilih' }
            : null;

    const changePosition = (value: string) => {
        form.setData('organizational_position_id', value);
        form.setData('faculty_id', '');
        form.setData('study_program_id', '');
        form.setData('work_unit_id', '');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Penugasan Jabatan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BriefcaseBusiness}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Penugasan Jabatan' : `Edit Penugasan ${assignment.employee?.name ?? 'Pegawai'}`}
                        description="Tetapkan pegawai pada jabatan organisasi dengan lingkup yang tepat. Penugasan aktif pada unit kerja otomatis menyinkronkan keanggotaan unit."
                        badges={isCreate ? ['Penugasan baru'] : [selectedPosition?.label ?? 'Penugasan jabatan']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserRound size={16} /> Pegawai & Jabatan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            {isCreate && urls.searchEmployees ? (
                                                <AsyncSelect
                                                    label="Pilih pegawai"
                                                    required
                                                    fetchUrl={urls.searchEmployees}
                                                    value={selectedEmployee}
                                                    onChange={(option) => form.setData('employee_profile_id', option ? Number(option.id) : null)}
                                                    error={form.errors.employee_profile_id}
                                                    hint="Hanya pegawai aktif yang dapat ditugaskan."
                                                    placeholder="Cari nama, email, atau nomor pegawai…"
                                                />
                                            ) : (
                                                <div style={{ padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-card)' }}>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{assignment.employee?.name ?? '-'}</b>
                                                    <small className="db-hint">
                                                        {assignment.employee?.employee_number ?? 'Tanpa nomor pegawai'}
                                                        {assignment.employee?.email ? ` · ${assignment.employee.email}` : ''}
                                                    </small>
                                                    <div className="db-hint" style={{ marginTop: 6 }}>Pegawai tidak dapat diubah saat mengedit penugasan.</div>
                                                </div>
                                            )}
                                        </div>
                                        <SelectField
                                            label="Jabatan organisasi"
                                            required
                                            value={form.data.organizational_position_id}
                                            onChange={(event) => changePosition(event.target.value)}
                                            error={form.errors.organizational_position_id}
                                        >
                                            <option value="">-- Pilih jabatan --</option>
                                            {options.positions.map((position) => (
                                                <option key={position.id} value={position.id}>
                                                    {position.label} · {position.scopeLabel}
                                                </option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Tanggal mulai"
                                            type="date"
                                            value={form.data.starts_at}
                                            onChange={(event) => form.setData('starts_at', event.target.value)}
                                            error={form.errors.starts_at}
                                        />
                                        <TextField
                                            label="Tanggal berakhir"
                                            type="date"
                                            value={form.data.ends_at}
                                            onChange={(event) => form.setData('ends_at', event.target.value)}
                                            error={form.errors.ends_at}
                                            hint="Kosongkan untuk penugasan tanpa batas waktu."
                                        />
                                    </div>
                                </div>
                            </section>

                            {scopeType !== 'none' && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title">
                                            {scopeType === 'faculty' ? <GraduationCap size={16} /> : <Building2 size={16} />} Lingkup Penugasan
                                        </h2>
                                    </div>
                                    <div className="db-card-body">
                                        <div className="crud-grid">
                                            {scopeType === 'faculty' && (
                                                <SelectField
                                                    label="Fakultas"
                                                    required
                                                    value={form.data.faculty_id}
                                                    onChange={(event) => form.setData('faculty_id', event.target.value)}
                                                    error={form.errors.faculty_id}
                                                >
                                                    <option value="">-- Pilih fakultas --</option>
                                                    {options.faculties.map((option) => (
                                                        <option key={option.id} value={option.id}>{option.label}</option>
                                                    ))}
                                                </SelectField>
                                            )}
                                            {scopeType === 'study_program' && (
                                                <SelectField
                                                    label="Program studi"
                                                    required
                                                    value={form.data.study_program_id}
                                                    onChange={(event) => form.setData('study_program_id', event.target.value)}
                                                    error={form.errors.study_program_id}
                                                >
                                                    <option value="">-- Pilih program studi --</option>
                                                    {options.studyPrograms.map((option) => (
                                                        <option key={option.id} value={option.id}>{option.label}</option>
                                                    ))}
                                                </SelectField>
                                            )}
                                            {scopeType === 'work_unit' && (
                                                <SelectField
                                                    label="Unit kerja"
                                                    required
                                                    value={form.data.work_unit_id}
                                                    onChange={(event) => form.setData('work_unit_id', event.target.value)}
                                                    error={form.errors.work_unit_id}
                                                >
                                                    <option value="">-- Pilih unit kerja --</option>
                                                    {options.workUnits.map((option) => (
                                                        <option key={option.id} value={option.id}>{option.label}</option>
                                                    ))}
                                                </SelectField>
                                            )}
                                        </div>
                                        <div className="db-hint" style={{ marginTop: 10 }}>
                                            Jabatan ini memiliki lingkup {selectedPosition?.scopeLabel.toLowerCase()}. Pilih entitas lingkup agar penugasan valid.
                                        </div>
                                    </div>
                                </section>
                            )}

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><BriefcaseBusiness size={16} /> Status & Catatan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <SwitchField
                                            label="Jabatan utama"
                                            checked={form.data.is_primary}
                                            onChange={(value) => form.setData('is_primary', value)}
                                            error={form.errors.is_primary}
                                            onLabel="Jabatan utama"
                                            offLabel="Bukan jabatan utama"
                                            hint="Menandai jabatan struktural utama pegawai."
                                        />
                                        <SwitchField
                                            label="Status penugasan"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Penugasan nonaktif tidak dihitung pada statistik aktif dan tidak menyinkronkan unit kerja."
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan penugasan"
                                                rows={3}
                                                placeholder="Dasar penugasan, nomor SK, atau keterangan lain…"
                                                value={form.data.notes}
                                                onChange={(event) => form.setData('notes', event.target.value)}
                                                error={form.errors.notes}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Penugasan' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
