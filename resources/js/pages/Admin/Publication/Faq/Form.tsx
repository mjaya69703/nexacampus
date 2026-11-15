// Form tambah/ubah FAQ.
import { Head, useForm } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

const RichTextField = lazy(() =>
    import('../../../../components/Shared/Crud/RichTextField').then((m) => ({ default: m.RichTextField })),
);

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    faq: { id?: number; type: string; category: string; question: string; answer: string; sort_order: number; is_active: boolean };
    typeOptions: { id: string; name: string }[];
    categoryOptions: string[];
    urls: { index: string; submit: string };
};

export default function FaqForm({ shell, mode, faq, typeOptions, categoryOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        type: faq.type,
        category: faq.category,
        question: faq.question,
        answer: faq.answer,
        sort_order: faq.sort_order,
        is_active: faq.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} FAQ · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CircleHelp}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Tambah FAQ' : 'Edit FAQ'}
                        description="Kelompok bersifat bebas dan tidak terikat Kategori Publikasi. Urutan terkecil tampil lebih atas."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CircleHelp size={16} /> Form FAQ</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Pertanyaan"
                                            required
                                            rows={2}
                                            placeholder="Tuliskan pertanyaan singkat dan jelas…"
                                            value={form.data.question}
                                            onChange={(e) => form.setData('question', e.target.value)}
                                            error={form.errors.question}
                                            hint="Maks 1000 karakter."
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <Suspense fallback={<div className="db-input">Memuat editor…</div>}>
                                            <RichTextField
                                                label="Jawaban lengkap"
                                                required
                                                height={320}
                                                value={form.data.answer}
                                                onChange={(v) => form.setData('answer', v)}
                                                error={form.errors.answer}
                                            />
                                        </Suspense>
                                    </div>
                                    <SelectField
                                        label="Tipe modul"
                                        required
                                        value={form.data.type}
                                        onChange={(e) => form.setData('type', e.target.value)}
                                        error={form.errors.type}
                                    >
                                        {typeOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Kelompok"
                                        required
                                        list="faq-category-options"
                                        placeholder="Contoh: Pendaftaran, Biaya, Dokumen"
                                        hint="Bebas — ketik baru atau pilih dari daftar."
                                        value={form.data.category}
                                        onChange={(e) => form.setData('category', e.target.value)}
                                        error={form.errors.category}
                                    />
                                    <TextField
                                        label="Urutan tampil"
                                        type="number"
                                        min={0}
                                        hint="Terkecil (0, 1, 2) tampil lebih atas."
                                        value={String(form.data.sort_order)}
                                        onChange={(e) => form.setData('sort_order', e.target.value === '' ? 0 : Number(e.target.value))}
                                        error={form.errors.sort_order}
                                    />
                                    <SwitchField
                                        label="Status aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={form.errors.is_active}
                                        hint="FAQ nonaktif disembunyikan dari publik."
                                    />
                                </div>

                                <datalist id="faq-category-options">
                                    {categoryOptions.map((c) => (
                                        <option key={c} value={c} />
                                    ))}
                                </datalist>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan FAQ' : 'Simpan Perubahan'}
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
