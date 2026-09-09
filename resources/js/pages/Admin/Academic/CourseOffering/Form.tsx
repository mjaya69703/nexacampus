// Form buka/ubah penawaran kelas.
import { Head, useForm } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    offering: {
        id?: number; academic_year_id: number | string; study_program_id: number | string;
        curriculum_id: number | string; course_id: number | string;
        label: string; code: string; semester_no: number | string;
        capacity: number | string; credits: number | string;
        total_meetings: number | string; class_start_date: string; class_end_date: string;
        is_required: boolean; delivery_mode: string; status: string; notes: string;
    };
    options: {
        years: { id: number; name: string }[];
        programs: { id: number; name: string }[];
        curriculums: { id: number; name: string }[];
        courses: { id: number; label: string }[];
    };
    deliveryModes: string[];
    statuses: string[];
    urls: { index: string; submit: string; workspace?: string };
};

export default function CourseOfferingForm({ shell, mode, offering, options, deliveryModes, statuses, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        academic_year_id: offering.academic_year_id,
        study_program_id: offering.study_program_id,
        curriculum_id: offering.curriculum_id,
        course_id: offering.course_id,
        label: offering.label,
        code: offering.code,
        semester_no: offering.semester_no,
        capacity: offering.capacity,
        credits: offering.credits,
        total_meetings: offering.total_meetings,
        class_start_date: offering.class_start_date,
        class_end_date: offering.class_end_date,
        is_required: offering.is_required,
        delivery_mode: offering.delivery_mode,
        status: offering.status,
        notes: offering.notes,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const num = (v: unknown) => (v === '' ? '' : Number(v));

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buka' : 'Edit'} Kelas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik"
                        title={isCreate ? 'Buka Kelas Baru' : 'Edit Kelas'}
                        description="Setelah tersimpan, tugaskan dosen dan susun jadwal dari workspace."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ClipboardList size={16} /> Form Kelas</h2>
                            {!isCreate && urls.workspace && <a className="db-btn ghost sm" href={urls.workspace}>Workspace</a>}
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Tahun akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.academic_year_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {options.years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Program studi"
                                        required
                                        value={form.data.study_program_id}
                                        onChange={(e) => form.setData('study_program_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.study_program_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {options.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Mata kuliah"
                                        required
                                        value={form.data.course_id}
                                        onChange={(e) => form.setData('course_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.course_id}
                                    >
                                        <option value="">— Pilih —</option>
                                        {options.courses.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Kurikulum acuan"
                                        value={form.data.curriculum_id}
                                        onChange={(e) => form.setData('curriculum_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.curriculum_id}
                                    >
                                        <option value="">— Tanpa acuan —</option>
                                        {options.curriculums.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Label kelas"
                                        placeholder="Contoh: A"
                                        hint="Unik per tahun + prodi + MK."
                                        value={form.data.label}
                                        onChange={(e) => form.setData('label', e.target.value)}
                                        error={form.errors.label}
                                    />
                                    <TextField
                                        label="Kode kelas"
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Semester"
                                        type="number"
                                        value={form.data.semester_no}
                                        onChange={(e) => form.setData('semester_no', num(e.target.value))}
                                        error={form.errors.semester_no}
                                    />
                                    <TextField
                                        label="Kapasitas"
                                        type="number"
                                        value={form.data.capacity}
                                        onChange={(e) => form.setData('capacity', num(e.target.value))}
                                        error={form.errors.capacity}
                                    />
                                    <TextField
                                        label="SKS"
                                        type="number"
                                        value={form.data.credits}
                                        onChange={(e) => form.setData('credits', num(e.target.value))}
                                        error={form.errors.credits}
                                        hint="Kosongkan untuk ikut SKS MK."
                                    />
                                    <TextField
                                        label="Total pertemuan"
                                        type="number"
                                        value={form.data.total_meetings}
                                        onChange={(e) => form.setData('total_meetings', num(e.target.value))}
                                        error={form.errors.total_meetings}
                                        hint="1–32, syarat generate sesi."
                                    />
                                    <TextField
                                        label="Kelas mulai"
                                        type="date"
                                        value={form.data.class_start_date}
                                        onChange={(e) => form.setData('class_start_date', e.target.value)}
                                        error={form.errors.class_start_date}
                                    />
                                    <TextField
                                        label="Kelas selesai"
                                        type="date"
                                        value={form.data.class_end_date}
                                        onChange={(e) => form.setData('class_end_date', e.target.value)}
                                        error={form.errors.class_end_date}
                                    />
                                    <SelectField
                                        label="Mode"
                                        required
                                        value={form.data.delivery_mode}
                                        onChange={(e) => form.setData('delivery_mode', e.target.value)}
                                        error={form.errors.delivery_mode}
                                    >
                                        {deliveryModes.map((m) => <option key={m} value={m}>{m}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Status"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={form.errors.status}
                                    >
                                        {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                    </SelectField>
                                    <SwitchField
                                        label="Wajib"
                                        checked={form.data.is_required}
                                        onChange={(v) => form.setData('is_required', v)}
                                        error={form.errors.is_required}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={form.errors.notes}
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
                </div>
            </div>
        </AdminShell>
    );
}
