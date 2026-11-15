// Form tambah/ubah mata kuliah — scope reaktif + prasyarat checklist.
import { Head, useForm } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { Checklist } from '../../../../components/Shared/Crud/Checklist';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    course: {
        id?: number; scope_type: string; scope_id: number | string; code: string;
        name: string; short_name: string; credits: number;
        semester_recommendation: number | string; requirement_type: string;
        category_type: string; is_active: boolean; desc: string;
        prerequisite_ids: (number | string)[];
    };
    options: {
        faculties: { id: number; name: string }[];
        programs: { id: number; name: string }[];
        prerequisites: { id: number; label: string }[];
        requirements: string[];
        categories: string[];
    };
    urls: { index: string; submit: string };
};

export default function CourseForm({ shell, mode, course, options, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        scope_type: course.scope_type,
        scope_id: course.scope_id,
        code: course.code,
        name: course.name,
        short_name: course.short_name,
        credits: course.credits,
        semester_recommendation: course.semester_recommendation,
        requirement_type: course.requirement_type,
        category_type: course.category_type,
        is_active: course.is_active,
        desc: course.desc,
        prerequisite_ids: course.prerequisite_ids as number[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const setScopeType = (value: string) => {
        form.setData('scope_type', value);
        if (value === 'global') form.setData('scope_id', '');
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Mata Kuliah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Mata Kuliah' : `Edit ${course.code}`}
                        description="MK aktif wajib berscope aktif. Prasyarat tidak boleh dirinya sendiri."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><BookOpen size={16} /> Form Mata Kuliah</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Scope"
                                        required
                                        value={form.data.scope_type}
                                        onChange={(e) => setScopeType(e.target.value)}
                                        error={form.errors.scope_type}
                                    >
                                        <option value="global">Global (semua)</option>
                                        <option value="faculty">Fakultas</option>
                                        <option value="study_program">Program studi</option>
                                    </SelectField>
                                    {form.data.scope_type === 'faculty' && (
                                        <SelectField
                                            label="Fakultas tujuan"
                                            required
                                            value={form.data.scope_id}
                                            onChange={(e) => form.setData('scope_id', e.target.value === '' ? '' : Number(e.target.value))}
                                            error={form.errors.scope_id}
                                        >
                                            <option value="">— Pilih —</option>
                                            {options.faculties.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
                                        </SelectField>
                                    )}
                                    {form.data.scope_type === 'study_program' && (
                                        <SelectField
                                            label="Prodi tujuan"
                                            required
                                            value={form.data.scope_id}
                                            onChange={(e) => form.setData('scope_id', e.target.value === '' ? '' : Number(e.target.value))}
                                            error={form.errors.scope_id}
                                        >
                                            <option value="">— Pilih —</option>
                                            {options.programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </SelectField>
                                    )}
                                    <TextField
                                        label="Kode MK"
                                        required
                                        placeholder="Contoh: TI101"
                                        hint="Unik, maks 20 karakter."
                                        value={form.data.code}
                                        onChange={(e) => form.setData('code', e.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Nama MK"
                                        required
                                        placeholder="Contoh: Algoritma dan Struktur Data"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="SKS"
                                        required
                                        type="number"
                                        value={form.data.credits}
                                        onChange={(e) => form.setData('credits', e.target.value === '' ? 0 : Number(e.target.value))}
                                        error={form.errors.credits}
                                        hint="1–24"
                                    />
                                    <TextField
                                        label="Semester rekomendasi"
                                        type="number"
                                        value={form.data.semester_recommendation}
                                        onChange={(e) => form.setData('semester_recommendation', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={form.errors.semester_recommendation}
                                        hint="1–14"
                                    />
                                    <SelectField
                                        label="Sifat"
                                        required
                                        value={form.data.requirement_type}
                                        onChange={(e) => form.setData('requirement_type', e.target.value)}
                                        error={form.errors.requirement_type}
                                    >
                                        {options.requirements.map((r) => <option key={r} value={r}>{r}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Kategori"
                                        required
                                        value={form.data.category_type}
                                        onChange={(e) => form.setData('category_type', e.target.value)}
                                        error={form.errors.category_type}
                                    >
                                        {options.categories.map((c) => <option key={c} value={c}>{c}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Nama singkat"
                                        value={form.data.short_name}
                                        onChange={(e) => form.setData('short_name', e.target.value)}
                                        error={form.errors.short_name}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            value={form.data.desc}
                                            onChange={(e) => form.setData('desc', e.target.value)}
                                            error={form.errors.desc}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Prasyarat</span>
                                        <Checklist
                                            options={options.prerequisites}
                                            selected={form.data.prerequisite_ids}
                                            onChange={(ids) => form.setData('prerequisite_ids', ids as number[])}
                                            placeholder="Cari MK prasyarat…"
                                            error={form.errors.prerequisite_ids}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan MK' : 'Simpan Perubahan'}
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
