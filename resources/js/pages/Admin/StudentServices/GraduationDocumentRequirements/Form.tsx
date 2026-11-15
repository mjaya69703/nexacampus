// Form tambah/ubah syarat dokumen yudisium.
import { Head, useForm } from '@inertiajs/react';
import { FileCheck2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RequirementPayload = {
    id?: number;
    study_program_id: string | number;
    document_type: string;
    label: string;
    is_required: boolean;
    allowed_extensions: string;
    max_size_kb: number;
    sort_order: number;
    is_active: boolean;
    description: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    requirement: RequirementPayload;
    studyPrograms: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

export default function GraduationDocumentRequirementForm({
    shell,
    mode,
    requirement,
    studyPrograms,
    urls,
}: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        study_program_id: String(requirement.study_program_id ?? ''),
        document_type: requirement.document_type,
        label: requirement.label,
        is_required: requirement.is_required,
        allowed_extensions: requirement.allowed_extensions,
        max_size_kb: requirement.max_size_kb,
        sort_order: requirement.sort_order,
        is_active: requirement.is_active,
        description: requirement.description,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Dokumen Yudisium · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileCheck2}
                        eyebrow="Layanan Mahasiswa · Yudisium"
                        title={isCreate ? 'Tambah Syarat Dokumen' : `Edit ${requirement.label}`}
                        description="Tentukan dokumen yang wajib diunggah mahasiswa saat mengajukan yudisium."
                        badges={isCreate ? ['Syarat baru'] : [requirement.document_type]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileCheck2 size={16} /> Identitas Dokumen</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Program studi"
                                        value={form.data.study_program_id}
                                        onChange={(event) => form.setData('study_program_id', event.target.value)}
                                        error={form.errors.study_program_id}
                                        hint="Kosongkan untuk berlaku di seluruh program studi."
                                    >
                                        <option value="">Global (semua prodi)</option>
                                        {studyPrograms.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Tipe dokumen"
                                        required
                                        placeholder="Contoh: ijazah_sma"
                                        value={form.data.document_type}
                                        onChange={(event) => form.setData('document_type', event.target.value)}
                                        error={form.errors.document_type}
                                        hint="Huruf kecil, angka, garis bawah, atau strip. Unik per cakupan."
                                    />
                                    <TextField
                                        label="Label dokumen"
                                        required
                                        placeholder="Contoh: Ijazah SMA / Sederajat"
                                        value={form.data.label}
                                        onChange={(event) => form.setData('label', event.target.value)}
                                        error={form.errors.label}
                                    />
                                    <TextField
                                        label="Format diizinkan"
                                        placeholder="pdf,jpg,jpeg,png"
                                        value={form.data.allowed_extensions}
                                        onChange={(event) => form.setData('allowed_extensions', event.target.value)}
                                        error={form.errors.allowed_extensions}
                                        hint="Pisahkan dengan koma. Hanya pdf, jpg, jpeg, png yang diterima."
                                    />
                                    <TextField
                                        label="Ukuran maksimal (KB)"
                                        type="number"
                                        min={1}
                                        max={51200}
                                        value={form.data.max_size_kb}
                                        onChange={(event) => form.setData('max_size_kb', Number(event.target.value))}
                                        error={form.errors.max_size_kb}
                                    />
                                    <TextField
                                        label="Urutan tampil"
                                        type="number"
                                        min={0}
                                        value={form.data.sort_order}
                                        onChange={(event) => form.setData('sort_order', Number(event.target.value))}
                                        error={form.errors.sort_order}
                                        hint="Angka kecil tampil lebih dahulu."
                                    />
                                    <SwitchField
                                        label="Dokumen wajib"
                                        checked={form.data.is_required}
                                        onChange={(value) => form.setData('is_required', value)}
                                        error={form.errors.is_required}
                                        hint="Dokumen wajib harus terverifikasi sebelum pengajuan bisa disetujui."
                                    />
                                    <SwitchField
                                        label="Aktifkan syarat dokumen"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            rows={3}
                                            placeholder="Keterangan tambahan untuk mahasiswa…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Syarat' : 'Simpan Perubahan'}
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
