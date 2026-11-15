// Form tambah/ubah pertanyaan EDOM.
import { Head, useForm } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type QuestionPayload = {
    id?: number;
    category: string;
    question_text: string;
    answer_type: string;
    sort_order: number;
    is_required: boolean;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    question: QuestionPayload;
    categories: Option[];
    answerTypes: Option[];
    urls: { index: string; submit: string };
};

export default function EdomQuestionForm({ shell, mode, question, categories, answerTypes, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        category: question.category,
        question_text: question.question_text,
        answer_type: question.answer_type,
        sort_order: question.sort_order,
        is_required: question.is_required,
        is_active: question.is_active,
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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Pertanyaan EDOM · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CircleHelp}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Pertanyaan EDOM' : 'Edit Pertanyaan EDOM'}
                        description="Lengkapi teks pertanyaan, kategori penilaian, format jawaban, dan urutan tampil pada kuesioner mahasiswa."
                        badges={isCreate ? ['Pertanyaan baru'] : [`Urutan ${question.sort_order}`]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CircleHelp size={16} /> Instrumen Kuesioner</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Kategori penilaian"
                                        required
                                        value={form.data.category}
                                        onChange={(event) => form.setData('category', event.target.value)}
                                        error={form.errors.category}
                                    >
                                        {categories.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <SelectField
                                        label="Tipe jawaban"
                                        required
                                        value={form.data.answer_type}
                                        onChange={(event) => form.setData('answer_type', event.target.value)}
                                        error={form.errors.answer_type}
                                        hint="Skala 1-5 dipakai untuk perhitungan nilai kuantitatif EDOM."
                                    >
                                        {answerTypes.map((option) => (
                                            <option key={option.id} value={option.id}>{option.label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Nomor urut tampil"
                                        required
                                        type="number"
                                        min={0}
                                        value={form.data.sort_order}
                                        onChange={(event) => form.setData('sort_order', event.target.value === '' ? 0 : Number(event.target.value))}
                                        error={form.errors.sort_order}
                                        hint="Menentukan posisi pertanyaan pada halaman pengisian kuesioner."
                                    />
                                    <SwitchField
                                        label="Wajib diisi mahasiswa"
                                        checked={form.data.is_required}
                                        onChange={(value) => form.setData('is_required', value)}
                                        error={form.errors.is_required}
                                        onLabel="Wajib"
                                        offLabel="Opsional"
                                    />
                                    <SwitchField
                                        label="Status pertanyaan aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Pertanyaan nonaktif tidak muncul pada kuesioner EDOM."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Teks pertanyaan evaluasi"
                                            required
                                            rows={3}
                                            placeholder="Tuliskan butir pertanyaan yang akan ditanyakan kepada mahasiswa…"
                                            value={form.data.question_text}
                                            onChange={(event) => form.setData('question_text', event.target.value)}
                                            error={form.errors.question_text}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Pertanyaan' : 'Simpan Perubahan'}
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
