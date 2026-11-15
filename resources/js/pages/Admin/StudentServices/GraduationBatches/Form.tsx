// Form tambah/ubah batch yudisium.
import { Head, useForm } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type BatchPayload = {
    id?: number;
    academic_period_id: string | number;
    study_program_id: string | number;
    name: string;
    code: string;
    yudisium_date: string;
    sk_number: string;
    sk_date: string;
    status: string;
    notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    batch: BatchPayload;
    academicPeriods: { id: number; name: string }[];
    studyPrograms: { id: number; name: string }[];
    statuses: { id: string; name: string }[];
    urls: { index: string; submit: string };
};

export default function GraduationBatchForm({ shell, mode, batch, academicPeriods, studyPrograms, statuses, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        academic_period_id: String(batch.academic_period_id ?? ''),
        study_program_id: String(batch.study_program_id ?? ''),
        name: batch.name,
        code: batch.code,
        yudisium_date: batch.yudisium_date,
        sk_number: batch.sk_number,
        sk_date: batch.sk_date,
        status: batch.status,
        notes: batch.notes,
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
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Batch Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Layanan Mahasiswa · Yudisium"
                        title={isCreate ? 'Buat Batch Yudisium' : `Edit ${batch.name}`}
                        description="Tentukan periode, cakupan program studi, tanggal yudisium, dan nomor SK kelulusan."
                        badges={isCreate ? ['Batch baru'] : [batch.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Informasi Batch</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Periode akademik"
                                        required
                                        value={form.data.academic_period_id}
                                        onChange={(event) => form.setData('academic_period_id', event.target.value)}
                                        error={form.errors.academic_period_id}
                                        hint="Hanya periode bertipe Yudisium yang tersedia."
                                    >
                                        <option value="">-- Pilih periode --</option>
                                        {academicPeriods.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Program studi"
                                        value={form.data.study_program_id}
                                        onChange={(event) => form.setData('study_program_id', event.target.value)}
                                        error={form.errors.study_program_id}
                                        hint="Kosongkan untuk batch seluruh program studi."
                                    >
                                        <option value="">Semua Program Studi</option>
                                        {studyPrograms.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Nama batch"
                                        required
                                        placeholder="Contoh: Yudisium Ganjil 2026/2027"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode batch"
                                        required
                                        placeholder="Contoh: YUD-2026-GANJIL"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                    />
                                    <TextField
                                        label="Tanggal yudisium"
                                        type="date"
                                        value={form.data.yudisium_date}
                                        onChange={(event) => form.setData('yudisium_date', event.target.value)}
                                        error={form.errors.yudisium_date}
                                        hint="Wajib diisi sebelum finalisasi kelulusan dapat dilakukan."
                                    />
                                    <SelectField
                                        label="Status batch"
                                        required
                                        value={form.data.status}
                                        onChange={(event) => form.setData('status', event.target.value)}
                                        error={form.errors.status}
                                    >
                                        {statuses.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Nomor SK"
                                        placeholder="Contoh: 123/UN/2026"
                                        value={form.data.sk_number}
                                        onChange={(event) => form.setData('sk_number', event.target.value)}
                                        error={form.errors.sk_number}
                                    />
                                    <TextField
                                        label="Tanggal SK"
                                        type="date"
                                        value={form.data.sk_date}
                                        onChange={(event) => form.setData('sk_date', event.target.value)}
                                        error={form.errors.sk_date}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            rows={3}
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Batch' : 'Simpan Perubahan'}
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
