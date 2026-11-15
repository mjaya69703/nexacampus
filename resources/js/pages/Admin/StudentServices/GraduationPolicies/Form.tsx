// Form tambah/ubah aturan yudisium.
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type PolicyPayload = {
    id?: number;
    name: string;
    study_program_id: string | number;
    minimum_semester: number;
    minimum_passed_credits: number;
    minimum_gpa: number;
    require_active_status: boolean;
    require_no_financial_hold: boolean;
    require_no_incomplete_grade: boolean;
    require_open_yudisium_period: boolean;
    is_active: boolean;
    description: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    policy: PolicyPayload;
    studyPrograms: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function GraduationPolicyForm({ shell, mode, policy, studyPrograms, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: policy.name,
        study_program_id: String(policy.study_program_id ?? ''),
        minimum_semester: policy.minimum_semester,
        minimum_passed_credits: policy.minimum_passed_credits,
        minimum_gpa: policy.minimum_gpa,
        require_active_status: policy.require_active_status,
        require_no_financial_hold: policy.require_no_financial_hold,
        require_no_incomplete_grade: policy.require_no_incomplete_grade,
        require_open_yudisium_period: policy.require_open_yudisium_period,
        is_active: policy.is_active,
        description: policy.description,
    });

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Aturan Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ShieldCheck}
                        eyebrow="Layanan Mahasiswa · Yudisium"
                        title={isCreate ? 'Tambah Aturan Yudisium' : `Edit ${policy.name}`}
                        description="Aturan aktif dipakai sistem untuk menilai kelayakan yudisium mahasiswa secara otomatis."
                        badges={isCreate ? ['Aturan baru'] : [policy.name]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ShieldCheck size={16} /> Cakupan & Ambang Kelulusan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama aturan"
                                        required
                                        placeholder="Contoh: Yudisium Reguler 2026"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <SelectField
                                        label="Program studi"
                                        value={form.data.study_program_id}
                                        onChange={(event) => form.setData('study_program_id', event.target.value)}
                                        error={form.errors.study_program_id}
                                        hint="Kosongkan untuk aturan global seluruh program studi."
                                    >
                                        <option value="">Global (semua prodi)</option>
                                        {studyPrograms.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Minimum semester"
                                        type="number"
                                        required
                                        min={1}
                                        max={20}
                                        value={form.data.minimum_semester}
                                        onChange={(event) => form.setData('minimum_semester', Number(event.target.value))}
                                        error={form.errors.minimum_semester}
                                    />
                                    <TextField
                                        label="Minimum SKS lulus"
                                        type="number"
                                        required
                                        min={0}
                                        max={300}
                                        value={form.data.minimum_passed_credits}
                                        onChange={(event) => form.setData('minimum_passed_credits', Number(event.target.value))}
                                        error={form.errors.minimum_passed_credits}
                                    />
                                    <TextField
                                        label="Minimum IPK"
                                        type="number"
                                        required
                                        min={0}
                                        max={4}
                                        step="0.01"
                                        value={form.data.minimum_gpa}
                                        onChange={(event) => form.setData('minimum_gpa', Number(event.target.value))}
                                        error={form.errors.minimum_gpa}
                                    />
                                    <SwitchField
                                        label="Aktifkan aturan"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Hanya satu aturan aktif per cakupan (global / prodi)."
                                    />
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Pemeriksaan Otomatis</h3>
                                    <div className="crud-grid">
                                        <SwitchField
                                            label="Wajib status akademik aktif"
                                            checked={form.data.require_active_status}
                                            onChange={(value) => form.setData('require_active_status', value)}
                                            error={form.errors.require_active_status}
                                        />
                                        <SwitchField
                                            label="Wajib bebas hold keuangan"
                                            checked={form.data.require_no_financial_hold}
                                            onChange={(value) => form.setData('require_no_financial_hold', value)}
                                            error={form.errors.require_no_financial_hold}
                                        />
                                        <SwitchField
                                            label="Wajib tanpa nilai incomplete"
                                            checked={form.data.require_no_incomplete_grade}
                                            onChange={(value) => form.setData('require_no_incomplete_grade', value)}
                                            error={form.errors.require_no_incomplete_grade}
                                        />
                                        <SwitchField
                                            label="Wajib periode yudisium terbuka"
                                            checked={form.data.require_open_yudisium_period}
                                            onChange={(value) => form.setData('require_open_yudisium_period', value)}
                                            error={form.errors.require_open_yudisium_period}
                                        />
                                    </div>
                                    <div style={{ marginTop: 14 }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            rows={3}
                                            placeholder="Catatan internal mengenai aturan ini…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Aturan' : 'Simpan Perubahan'}
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
