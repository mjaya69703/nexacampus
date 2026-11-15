// Form pengajuan/edit skripsi — mahasiswa dipilih via pencarian server-side.
import { Head, useForm } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    thesis: {
        id?: number;
        student_profile_id: number | string;
        student_label: string;
        academic_year_id: number | string;
        type: string;
        title: string;
        topic: string;
        abstract: string;
        keywords: string;
        notes: string;
    };
    years: { id: number; name: string }[];
    types: Record<string, string>;
    urls: { index: string; submit: string; searchStudents: string };
};

export default function ThesisForm({ shell, mode, thesis, years, types, urls }: Props) {
    const isCreate = mode === 'create';
    const [student, setStudent] = useState<AsyncOption | null>(
        thesis.student_profile_id ? { id: Number(thesis.student_profile_id), label: thesis.student_label } : null,
    );

    const form = useForm({
        student_profile_id: thesis.student_profile_id as number | '',
        academic_year_id: thesis.academic_year_id as number | '',
        type: thesis.type,
        title: thesis.title,
        topic: thesis.topic,
        abstract: thesis.abstract,
        keywords: thesis.keywords,
        notes: thesis.notes,
        proposal_file: null as File | null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { forceFormData: true };
        isCreate ? form.post(urls.submit, options) : form.put(urls.submit, options);
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Skripsi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tambah Pengajuan Skripsi' : 'Edit Pengajuan Skripsi'}
                        description="Satu mahasiswa hanya boleh memiliki satu skripsi/tugas akhir aktif. Pembimbing ditetapkan saat persetujuan."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><BookOpen size={16} /> Data Pengajuan</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    {isCreate ? (
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <AsyncSelect
                                                label="Mahasiswa"
                                                required
                                                fetchUrl={urls.searchStudents}
                                                value={student}
                                                onChange={(option) => {
                                                    setStudent(option);
                                                    form.setData('student_profile_id', option ? Number(option.id) : '');
                                                }}
                                                placeholder="Ketik NIM/nama/email mahasiswa…"
                                                error={errors.student_profile_id}
                                            />
                                        </div>
                                    ) : (
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextField label="Mahasiswa" value={thesis.student_label} readOnly hint="Mahasiswa tidak dapat diubah setelah pengajuan dibuat." />
                                        </div>
                                    )}

                                    <SelectField
                                        label="Tahun Akademik"
                                        required
                                        value={form.data.academic_year_id}
                                        onChange={(event) => form.setData('academic_year_id', event.target.value === '' ? '' : Number(event.target.value))}
                                        error={errors.academic_year_id}
                                    >
                                        {years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                    </SelectField>

                                    <SelectField
                                        label="Jenis"
                                        required
                                        value={form.data.type}
                                        onChange={(event) => form.setData('type', event.target.value)}
                                        error={errors.type}
                                    >
                                        {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </SelectField>

                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul"
                                            required
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            error={errors.title}
                                            placeholder="Judul sementara/final penelitian"
                                        />
                                    </div>

                                    <TextField
                                        label="Topik / Bidang"
                                        value={form.data.topic}
                                        onChange={(event) => form.setData('topic', event.target.value)}
                                        error={errors.topic}
                                        placeholder="Contoh: Kecerdasan Buatan"
                                    />

                                    <TextField
                                        label="Kata Kunci"
                                        value={form.data.keywords}
                                        onChange={(event) => form.setData('keywords', event.target.value)}
                                        error={errors.keywords}
                                        placeholder="Dipisahkan koma"
                                    />

                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Abstrak"
                                            rows={4}
                                            value={form.data.abstract}
                                            onChange={(event) => form.setData('abstract', event.target.value)}
                                            error={errors.abstract}
                                        />
                                    </div>

                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            rows={2}
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={errors.notes}
                                            hint="Catatan internal admin/prodi, tidak ditampilkan ke mahasiswa."
                                        />
                                    </div>

                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label className="crud-label">Berkas Proposal</label>
                                        <input
                                            type="file"
                                            className="db-input"
                                            accept=".pdf,.doc,.docx"
                                            onChange={(event) => form.setData('proposal_file', event.target.files?.[0] ?? null)}
                                        />
                                        {errors.proposal_file && <div className="db-error">{errors.proposal_file}</div>}
                                        <div className="crud-hint">PDF/DOC/DOCX maksimal 10 MB. Mengunggah berkas baru akan menambah versi dokumen.</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <FormActions
                            cancelHref={urls.index}
                            submitLabel={isCreate ? 'Simpan Pengajuan' : 'Simpan Perubahan'}
                            processing={form.processing}
                        />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
