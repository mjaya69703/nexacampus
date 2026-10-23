// Form tambah/ubah kuota penerimaan PMB.
import { Head, useForm } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    quota: {
        id?: number;
        admission_period_id: number | string;
        faculty_id: number | string;
        study_program_id: number | string;
        class_type: string;
        quota: number;
    };
    periodOptions: { id: number; label: string }[];
    facultyOptions: { id: number; label: string }[];
    programOptions: { id: number; facultyId: number; label: string }[];
    classTypeOptions: { id: string; label: string }[];
    urls: { index: string; submit: string };
};

export default function AdmissionQuotaForm({ shell, mode, quota, periodOptions, facultyOptions, programOptions, classTypeOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        admission_period_id: quota.admission_period_id,
        faculty_id: quota.faculty_id,
        study_program_id: quota.study_program_id,
        class_type: quota.class_type,
        quota: quota.quota,
    });

    const visiblePrograms = form.data.faculty_id === ''
        ? programOptions
        : programOptions.filter((program) => String(program.facultyId) === String(form.data.faculty_id));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Kuota PMB · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Gauge}
                        eyebrow="Admission"
                        title={isCreate ? 'Tambah Kuota PMB' : 'Edit Kuota PMB'}
                        description="Kosongkan fakultas/program studi/kelas untuk membuat kuota yang berlaku lebih luas."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Gauge size={16} /> Penetapan Kuota</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Gelombang / periode PMB"
                                        required
                                        value={form.data.admission_period_id}
                                        onChange={(e) => form.setData('admission_period_id', e.target.value)}
                                        error={form.errors.admission_period_id}
                                    >
                                        <option value="">-- Pilih periode --</option>
                                        {periodOptions.map((period) => (
                                            <option key={period.id} value={period.id}>{period.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Fakultas (opsional)"
                                        value={form.data.faculty_id}
                                        onChange={(e) => {
                                            form.setData('faculty_id', e.target.value);
                                            form.setData('study_program_id', '');
                                        }}
                                        error={form.errors.faculty_id}
                                        hint="Kosongkan untuk kuota semua fakultas."
                                    >
                                        <option value="">-- Semua fakultas --</option>
                                        {facultyOptions.map((faculty) => (
                                            <option key={faculty.id} value={faculty.id}>{faculty.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Program studi (opsional)"
                                        value={form.data.study_program_id}
                                        onChange={(e) => form.setData('study_program_id', e.target.value)}
                                        error={form.errors.study_program_id}
                                        hint="Kosongkan untuk kuota semua program studi."
                                    >
                                        <option value="">-- Semua program studi --</option>
                                        {visiblePrograms.map((program) => (
                                            <option key={program.id} value={program.id}>{program.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Jenis kelas (opsional)"
                                        value={form.data.class_type}
                                        onChange={(e) => form.setData('class_type', e.target.value)}
                                        error={form.errors.class_type}
                                        hint="Kosongkan untuk kuota semua kelas."
                                    >
                                        <option value="">-- Semua kelas --</option>
                                        {classTypeOptions.map((type) => (
                                            <option key={type.id} value={type.id}>{type.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Jumlah kuota"
                                        required
                                        type="number"
                                        min={1}
                                        value={String(form.data.quota)}
                                        onChange={(e) => form.setData('quota', e.target.value === '' ? 1 : Number(e.target.value))}
                                        error={form.errors.quota}
                                        hint="Batas maksimal pendaftar yang diterima pada cakupan ini."
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Kuota' : 'Simpan Perubahan'}
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
