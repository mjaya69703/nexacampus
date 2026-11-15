// Form tambah/ubah album galeri.
import { Head, router, useForm } from '@inertiajs/react';
import { Image, Images, Trash2, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type AlbumPayload = {
    id?: number;
    title: string;
    slug: string;
    category_id: number | null;
    description: string;
    is_published: boolean;
    cover_url: string | null;
};

type ExistingImage = {
    id: number;
    url: string;
    caption: string;
    sortOrder: number;
    updateUrl: string;
    destroyUrl: string;
    coverUrl: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    album: AlbumPayload;
    images: ExistingImage[];
    categoryOptions: { id: number; name: string }[];
    urls: { index: string; submit: string; storeImages: string | null };
};

function slugify(value: string) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-');
}

export default function GalleryForm({ shell, mode, album, images, categoryOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [slugTouched, setSlugTouched] = useState(!isCreate);
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const [newPreviews, setNewPreviews] = useState<{ file: File; url: string }[]>([]);
    const [pendingImage, setPendingImage] = useState<ExistingImage | null>(null);
    const [processingImage, setProcessingImage] = useState(false);
    const [extraFiles, setExtraFiles] = useState<File[]>([]);
    const [uploading, setUploading] = useState(false);

    const form = useForm({
        title: album.title,
        slug: album.slug,
        category_id: album.category_id as number | null,
        description: album.description,
        is_published: album.is_published,
        cover_image: null as File | null,
        remove_cover: false as boolean,
        new_images: [] as File[],
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    useEffect(() => {
        return () => {
            if (coverPreview) URL.revokeObjectURL(coverPreview);
            newPreviews.forEach((p) => URL.revokeObjectURL(p.url));
        };
    }, [coverPreview, newPreviews]);

    const onTitleChange = (value: string) => {
        form.setData('title', value);
        if (!slugTouched) {
            form.setData('slug', slugify(value));
        }
    };

    const onCoverChange = (file: File | null) => {
        if (coverPreview) URL.revokeObjectURL(coverPreview);
        form.setData('cover_image', file);
        setCoverPreview(file ? URL.createObjectURL(file) : null);
    };

    const onNewImages = (files: File[]) => {
        newPreviews.forEach((p) => URL.revokeObjectURL(p.url));
        const previews = files.map((file) => ({ file, url: URL.createObjectURL(file) }));
        form.setData('new_images', files);
        setNewPreviews(previews);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.post(urls.submit, { forceFormData: true });
        }
    };

    const submitExtra = () => {
        if (!urls.storeImages || extraFiles.length === 0) return;
        setUploading(true);
        router.post(urls.storeImages, { images: extraFiles }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setExtraFiles([]);
            },
        });
    };

    const saveCaption = (image: ExistingImage, caption: string) => {
        router.patch(image.updateUrl, { caption }, { preserveScroll: true });
    };

    const setCover = (image: ExistingImage) => {
        router.post(image.coverUrl, {}, { preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Album Galeri · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Images}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Buat Album' : `Edit ${album.title}`}
                        description={isCreate
                            ? 'Buat album lalu tambah foto — sampul dan foto bisa diunggah langsung di sini.'
                            : 'Kelola sampul, tambah foto baru, ubah keterangan, atau jadikan foto sebagai sampul.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Images size={16} /> Form Album</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul album"
                                            required
                                            placeholder="Contoh: Wisuda 2026"
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
                                            label="Deskripsi"
                                            rows={3}
                                            placeholder="Deskripsi singkat album…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Gambar sampul</span>
                                        {!isCreate && album.cover_url && !form.data.remove_cover && !form.data.cover_image && (
                                            <div style={{ marginBottom: 8 }}>
                                                <img src={album.cover_url} alt="Sampul saat ini" style={{ maxWidth: 320, borderRadius: 10, display: 'block' }} />
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 12, marginTop: 6 }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.remove_cover}
                                                        onChange={(e) => form.setData('remove_cover', e.target.checked)}
                                                    />
                                                    Hapus sampul
                                                </label>
                                            </div>
                                        )}
                                        <input
                                            type="file"
                                            className="db-input"
                                            accept=".jpg,.jpeg,.png,.webp"
                                            onChange={(e) => onCoverChange(e.target.files?.[0] ?? null)}
                                        />
                                        {coverPreview && (
                                            <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                <Image size={14} />
                                                <img src={coverPreview} alt="Pratinjau sampul" style={{ maxWidth: 160, borderRadius: 8 }} />
                                                <span style={{ flex: 1 }}>{form.data.cover_image?.name}</span>
                                                <button className="db-btn ghost sm" type="button" onClick={() => onCoverChange(null)}>
                                                    <X size={13} /> Batal
                                                </button>
                                            </div>
                                        )}
                                        {form.errors.cover_image && <div className="db-error">{form.errors.cover_image}</div>}
                                        <div className="crud-hint">JPG/PNG/WebP, maks 2MB.</div>
                                    </div>
                                    {isCreate && (
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <span className="crud-label">Foto album (opsional)</span>
                                            <input
                                                type="file"
                                                className="db-input"
                                                accept=".jpg,.jpeg,.png,.webp"
                                                multiple
                                                onChange={(e) => onNewImages(Array.from(e.target.files ?? []).slice(0, 20))}
                                            />
                                            {newPreviews.length > 0 && (
                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginTop: 8 }}>
                                                    {newPreviews.map((p) => (
                                                        <img key={p.url} src={p.url} alt="" style={{ width: 96, height: 72, objectFit: 'cover', borderRadius: 8 }} />
                                                    ))}
                                                </div>
                                            )}
                                            {form.errors.new_images && <div className="db-error">{form.errors.new_images}</div>}
                                            <div className="crud-hint">Maks 20 foto sekali simpan. Keterangan bisa ditambah setelah album tersimpan.</div>
                                        </div>
                                    )}
                                    <SwitchField
                                        label="Publikasikan"
                                        checked={form.data.is_published}
                                        onChange={(v) => form.setData('is_published', v)}
                                        error={form.errors.is_published}
                                        hint="Draft disembunyikan dari publik."
                                    />
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Album' : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>

                    {!isCreate && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Image size={16} /> Foto Album ({images.length})</h2>
                                <label className="db-btn ghost sm" style={{ cursor: 'pointer' }}>
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        multiple
                                        hidden
                                        onChange={(e) => setExtraFiles(Array.from(e.target.files ?? []).slice(0, 20))}
                                    />
                                    + Tambah foto
                                </label>
                            </div>
                            <div className="db-card-body">
                                {extraFiles.length > 0 && (
                                    <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                                        <span style={{ flex: 1 }}>{extraFiles.length} foto siap diunggah.</span>
                                        <button className="db-btn ghost sm" type="button" onClick={() => setExtraFiles([])}>Batal</button>
                                        <button className="db-btn primary sm" type="button" disabled={uploading} onClick={submitExtra}>
                                            {uploading ? 'Mengunggah…' : 'Unggah sekarang'}
                                        </button>
                                    </div>
                                )}
                                {images.length === 0 ? (
                                    <p className="db-hint">Belum ada foto. Tambahkan lewat tombol di atas.</p>
                                ) : (
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 12 }}>
                                        {images.map((image) => (
                                            <div key={image.id} style={{ border: '1px solid var(--db-line)', borderRadius: 12, overflow: 'hidden', background: 'var(--db-card)' }}>
                                                <img src={image.url} alt="" style={{ width: '100%', height: 140, objectFit: 'cover', display: 'block' }} />
                                                <div style={{ padding: 10, display: 'grid', gap: 8 }}>
                                                    <input
                                                        className="db-input"
                                                        defaultValue={image.caption}
                                                        placeholder="Keterangan foto…"
                                                        maxLength={255}
                                                        onBlur={(e) => {
                                                            if (e.target.value !== image.caption) saveCaption(image, e.target.value);
                                                        }}
                                                        aria-label="Keterangan foto"
                                                    />
                                                    <div style={{ display: 'flex', gap: 6 }}>
                                                        <button className="db-btn ghost sm" type="button" title="Jadikan sampul" onClick={() => setCover(image)}>
                                                            <Image size={13} /> Sampul
                                                        </button>
                                                        <button className="db-btn ghost sm" type="button" title="Hapus foto" onClick={() => setPendingImage(image)}>
                                                            <Trash2 size={13} />
                                                        </button>
                                                        <span className="db-hint" style={{ marginLeft: 'auto' }}>#{image.sortOrder}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </section>
                    )}
                </div>

                <ConfirmModal
                    open={pendingImage !== null}
                    title="Hapus foto?"
                    message="Foto dihapus permanen dari album dan storage."
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processingImage}
                    onConfirm={() => {
                        if (!pendingImage) return;
                        setProcessingImage(true);
                        router.delete(pendingImage.destroyUrl, {
                            preserveScroll: true,
                            onFinish: () => {
                                setProcessingImage(false);
                                setPendingImage(null);
                            },
                        });
                    }}
                    onCancel={() => setPendingImage(null)}
                />
            </div>
        </AdminShell>
    );
}
