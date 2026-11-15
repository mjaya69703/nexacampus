// Form tambah/ubah buku perpustakaan (termasuk unggah sampul).
import { Head, useForm } from '@inertiajs/react';
import { BookOpen, ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type BookPayload = {
    id?: number;
    book_category_id: number | '';
    title: string;
    author: string;
    publisher: string;
    isbn: string;
    publication_year: number | '';
    language: string;
    edition: string;
    description: string;
    shelf_location: string;
    is_active: boolean;
    initial_copies: number;
    coverUrl: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    book: BookPayload;
    categories: Array<{ id: number; name: string }>;
    urls: { index: string; submit: string };
};

export default function BookForm({ shell, mode, book, categories, urls }: Props) {
    const isCreate = mode === 'create';
    const [coverPreview, setCoverPreview] = useState<string | null>(null);

    const form = useForm({
        book_category_id: book.book_category_id === '' ? '' : String(book.book_category_id),
        title: book.title,
        author: book.author,
        publisher: book.publisher,
        isbn: book.isbn,
        publication_year: book.publication_year === '' ? '' : String(book.publication_year),
        language: book.language,
        edition: book.edition,
        description: book.description,
        shelf_location: book.shelf_location,
        is_active: book.is_active,
        initial_copies: book.initial_copies,
        cover: null as File | null,
        remove_cover: false,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    useEffect(() => {
        return () => {
            if (coverPreview) URL.revokeObjectURL(coverPreview);
        };
    }, [coverPreview]);

    const onCoverChange = (file: File | null) => {
        if (coverPreview) URL.revokeObjectURL(coverPreview);
        form.setData('cover', file);
        setCoverPreview(file ? URL.createObjectURL(file) : null);

        if (file) {
            form.setData('remove_cover', false);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { forceFormData: true });
    };

    const preview = coverPreview ?? (!form.data.remove_cover ? book.coverUrl : null);

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Buku · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Perpustakaan"
                        title={isCreate ? 'Tambah Buku' : `Edit ${book.title}`}
                        description="Lengkapi data bibliografi. Eksemplar dapat ditambah otomatis saat membuat buku baru."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Informasi Buku</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <SelectField
                                        label="Kategori"
                                        required
                                        value={form.data.book_category_id}
                                        onChange={(event) => form.setData('book_category_id', event.target.value)}
                                        error={form.errors.book_category_id}
                                    >
                                        <option value="">Pilih kategori…</option>
                                        {categories.map((category) => (
                                            <option key={category.id} value={category.id}>{category.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Judul buku"
                                        required
                                        value={form.data.title}
                                        onChange={(event) => form.setData('title', event.target.value)}
                                        error={form.errors.title}
                                        placeholder="Judul lengkap buku"
                                    />
                                    <TextField
                                        label="Penulis"
                                        value={form.data.author}
                                        onChange={(event) => form.setData('author', event.target.value)}
                                        error={form.errors.author}
                                        placeholder="Nama penulis / editor"
                                    />
                                    <TextField
                                        label="Penerbit"
                                        value={form.data.publisher}
                                        onChange={(event) => form.setData('publisher', event.target.value)}
                                        error={form.errors.publisher}
                                        placeholder="Nama penerbit"
                                    />
                                    <TextField
                                        label="ISBN"
                                        value={form.data.isbn}
                                        onChange={(event) => form.setData('isbn', event.target.value)}
                                        error={form.errors.isbn}
                                        placeholder="978…"
                                    />
                                    <TextField
                                        label="Tahun terbit"
                                        type="number"
                                        min={1000}
                                        max={new Date().getFullYear() + 1}
                                        value={form.data.publication_year}
                                        onChange={(event) => form.setData('publication_year', event.target.value)}
                                        error={form.errors.publication_year}
                                    />
                                    <TextField
                                        label="Bahasa"
                                        required
                                        value={form.data.language}
                                        onChange={(event) => form.setData('language', event.target.value)}
                                        error={form.errors.language}
                                    />
                                    <TextField
                                        label="Edisi"
                                        value={form.data.edition}
                                        onChange={(event) => form.setData('edition', event.target.value)}
                                        error={form.errors.edition}
                                        placeholder="Contoh: Edisi 3"
                                    />
                                    <TextField
                                        label="Lokasi rak"
                                        value={form.data.shelf_location}
                                        onChange={(event) => form.setData('shelf_location', event.target.value)}
                                        error={form.errors.shelf_location}
                                        placeholder="Contoh: TI-A1"
                                    />
                                    {isCreate && (
                                        <TextField
                                            label="Jumlah eksemplar awal"
                                            type="number"
                                            min={0}
                                            max={20}
                                            value={form.data.initial_copies}
                                            onChange={(event) => form.setData('initial_copies', Number(event.target.value))}
                                            error={form.errors.initial_copies}
                                            hint="Kode eksemplar dibuat otomatis (BK-0001-01, BK-0001-02, …)."
                                        />
                                    )}
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                            placeholder="Ringkasan isi buku…"
                                        />
                                    </div>
                                    <SwitchField
                                        label="Status buku"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Buku nonaktif tidak tampil di katalog mahasiswa."
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Sampul Buku</h2>
                            </div>
                            <div className="db-card-body">
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 18, alignItems: 'flex-start' }}>
                                    {preview ? (
                                        <img
                                            src={preview}
                                            alt="Pratinjau sampul"
                                            style={{ width: 110, height: 150, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--db-line)' }}
                                        />
                                    ) : (
                                        <div
                                            className="db-stat-icon"
                                            style={{ width: 110, height: 150, borderRadius: 10 }}
                                            aria-hidden="true"
                                        >
                                            <ImagePlus size={28} />
                                        </div>
                                    )}
                                    <div style={{ display: 'grid', gap: 10, flex: '1 1 240px' }}>
                                        <label className="crud-label" htmlFor="book-cover">Berkas sampul</label>
                                        <input
                                            id="book-cover"
                                            className="db-input"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            onChange={(event) => onCoverChange(event.target.files?.[0] ?? null)}
                                        />
                                        {form.errors.cover && <div className="db-error">{form.errors.cover}</div>}
                                        <span className="crud-hint">Format JPG, PNG, atau WEBP. Maksimal 2 MB.</span>
                                        {!isCreate && book.coverUrl && (
                                            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--db-ink)' }}>
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.remove_cover}
                                                    onChange={(event) => {
                                                        form.setData('remove_cover', event.target.checked);
                                                        if (event.target.checked) onCoverChange(null);
                                                    }}
                                                    style={{ width: 17, height: 17, accentColor: 'var(--db-brand)' }}
                                                />
                                                <Trash2 size={14} /> Hapus sampul saat ini
                                            </label>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </section>

                        <FormActions
                            cancelHref={urls.index}
                            submitLabel={isCreate ? 'Simpan Buku' : 'Simpan Perubahan'}
                            processing={form.processing}
                        />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
