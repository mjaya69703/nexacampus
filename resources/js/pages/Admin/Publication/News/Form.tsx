// Form tambah/ubah berita.
import { Head, useForm } from '@inertiajs/react';
import { Image, Newspaper, X } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

const RichTextField = lazy(() =>
    import('../../../../components/Shared/Crud/RichTextField').then((m) => ({ default: m.RichTextField })),
);

type NewsPayload = {
    id?: number;
    title: string;
    slug: string;
    category_id: number | null;
    excerpt: string;
    content: string;
    is_published: boolean;
    published_at: string;
    image_url: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    news: NewsPayload;
    categoryOptions: { id: number; name: string }[];
    urls: { index: string; submit: string };
};

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-');
}

export default function NewsForm({ shell, mode, news, categoryOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [slugTouched, setSlugTouched] = useState(!isCreate);
    const [imagePreview, setImagePreview] = useState<string | null>(null);

    const form = useForm({
        title: news.title,
        slug: news.slug,
        category_id: news.category_id as number | null,
        excerpt: news.excerpt,
        content: news.content,
        is_published: news.is_published,
        published_at: news.published_at,
        featured_image: null as File | null,
        remove_image: false as boolean,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const onTitleChange = (value: string) => {
        form.setData('title', value);
        if (!slugTouched) {
            form.setData('slug', slugify(value));
        }
    };

    const onFileChange = (file: File | null) => {
        if (imagePreview) URL.revokeObjectURL(imagePreview);
        form.setData('featured_image', file);
        setImagePreview(file ? URL.createObjectURL(file) : null);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.post(urls.submit, { forceFormData: true });
        }
    };

    const excerptLen = form.data.excerpt.length;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tulis' : 'Edit'} Berita · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Newspaper}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Tulis Berita' : `Edit ${news.title}`}
                        description="Lengkapi judul, isi, gambar sampul, dan jadwal publikasi berita."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Newspaper size={16} /> Form Berita</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul berita"
                                            required
                                            placeholder="Masukkan judul berita…"
                                            value={form.data.title}
                                            onChange={(e) => onTitleChange(e.target.value)}
                                            error={form.errors.title}
                                        />
                                    </div>
                                    <TextField
                                        label="Slug"
                                        placeholder="auto-dari-judul"
                                        hint="Terisi otomatis dari judul. Ubah manual bila perlu."
                                        value={form.data.slug}
                                        onChange={(e) => {
                                            setSlugTouched(true);
                                            form.setData('slug', e.target.value);
                                        }}
                                        error={form.errors.slug}
                                    />
                                    <SelectField
                                        label="Kategori"
                                        value={form.data.category_id === null ? '' : String(form.data.category_id)}
                                        onChange={(e) => form.setData('category_id', e.target.value === '' ? null : Number(e.target.value))}
                                        error={form.errors.category_id}
                                        hint="Hanya kategori aktif."
                                    >
                                        <option value="">Tanpa kategori</option>
                                        {categoryOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Ringkasan (excerpt)"
                                            rows={2}
                                            placeholder="Ringkasan singkat untuk kartu berita…"
                                            value={form.data.excerpt}
                                            onChange={(e) => form.setData('excerpt', e.target.value)}
                                            error={form.errors.excerpt}
                                            hint={`${excerptLen}/500 karakter.`}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <Suspense fallback={<div className="db-input">Memuat editor…</div>}>
                                            <RichTextField
                                                label="Isi berita"
                                                required
                                                height={400}
                                                value={form.data.content}
                                                onChange={(v) => form.setData('content', v)}
                                                error={form.errors.content}
                                            />
                                        </Suspense>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Gambar sampul</span>
                                        {!isCreate && news.image_url && !form.data.remove_image && !form.data.featured_image && (
                                            <div style={{ marginBottom: 8 }}>
                                                <img src={news.image_url} alt="Sampul saat ini" style={{ maxWidth: 320, borderRadius: 10, display: 'block' }} />
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 12, marginTop: 6 }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.remove_image}
                                                        onChange={(e) => form.setData('remove_image', e.target.checked)}
                                                    />
                                                    Hapus gambar
                                                </label>
                                            </div>
                                        )}
                                        <input
                                            type="file"
                                            className="db-input"
                                            accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.bmp"
                                            onChange={(e) => onFileChange(e.target.files?.[0] ?? null)}
                                        />
                                        {imagePreview && (
                                            <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                <Image size={14} />
                                                <img src={imagePreview} alt="Pratinjau" style={{ maxWidth: 160, borderRadius: 8 }} />
                                                <span style={{ flex: 1 }}>{form.data.featured_image?.name}</span>
                                                <button className="db-btn ghost sm" type="button" onClick={() => onFileChange(null)}>
                                                    <X size={13} /> Batal
                                                </button>
                                            </div>
                                        )}
                                        {form.errors.featured_image && <div className="db-error">{form.errors.featured_image}</div>}
                                        <div className="crud-hint">Gambar (JPG/PNG/WebP, maks 2MB).</div>
                                    </div>
                                    <SwitchField
                                        label="Publikasikan"
                                        checked={form.data.is_published}
                                        onChange={(v) => form.setData('is_published', v)}
                                        error={form.errors.is_published}
                                        hint="Draft disembunyikan dari publik."
                                    />
                                    {form.data.is_published && (
                                        <div>
                                            <label className="crud-label" htmlFor="crud-news-published-at">Tanggal publikasi</label>
                                            <input
                                                id="crud-news-published-at"
                                                type="datetime-local"
                                                className="db-input"
                                                value={form.data.published_at}
                                                onChange={(e) => form.setData('published_at', e.target.value)}
                                            />
                                            {form.errors.published_at && <div className="db-error">{form.errors.published_at}</div>}
                                            <div className="crud-hint">Kosongi untuk waktu sekarang.</div>
                                        </div>
                                    )}
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Berita' : 'Simpan Perubahan'}
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
