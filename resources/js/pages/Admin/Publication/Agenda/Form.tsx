// Form tambah/ubah agenda.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type AgendaPayload = {
    id?: number;
    title: string;
    slug: string;
    category_id: number | null;
    description: string;
    location: string;
    event_date: string;
    event_time: string;
    event_end_date: string;
    is_published: boolean;
    published_at: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    agenda: AgendaPayload;
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

export default function AgendaForm({ shell, mode, agenda, categoryOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [slugTouched, setSlugTouched] = useState(!isCreate);

    const form = useForm({
        title: agenda.title,
        slug: agenda.slug,
        category_id: agenda.category_id as number | null,
        description: agenda.description,
        location: agenda.location,
        event_date: agenda.event_date,
        event_time: agenda.event_time,
        event_end_date: agenda.event_end_date,
        is_published: agenda.is_published,
        published_at: agenda.published_at,
    });

    const onTitleChange = (value: string) => {
        form.setData('title', value);
        if (!slugTouched) {
            form.setData('slug', slugify(value));
        }
    };

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
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Agenda · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Buat Agenda' : `Edit ${agenda.title}`}
                        description="Lengkapi jadwal, lokasi, dan status publikasi agenda kegiatan."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Form Agenda</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul agenda"
                                            required
                                            placeholder="Masukkan judul agenda…"
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
                                            rows={4}
                                            placeholder="Deskripsi kegiatan…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Lokasi"
                                            placeholder="Contoh: Aula Utama"
                                            value={form.data.location}
                                            onChange={(e) => form.setData('location', e.target.value)}
                                            error={form.errors.location}
                                        />
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-event-date">Tanggal acara <span>*</span></label>
                                        <input
                                            id="crud-event-date"
                                            type="date"
                                            className="db-input"
                                            value={form.data.event_date}
                                            onChange={(e) => form.setData('event_date', e.target.value)}
                                        />
                                        {form.errors.event_date && <div className="db-error">{form.errors.event_date}</div>}
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-event-time">Jam</label>
                                        <input
                                            id="crud-event-time"
                                            type="time"
                                            className="db-input"
                                            value={form.data.event_time}
                                            onChange={(e) => form.setData('event_time', e.target.value)}
                                        />
                                        {form.errors.event_time && <div className="db-error">{form.errors.event_time}</div>}
                                        <div className="crud-hint">Format jam:menit.</div>
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="crud-event-end">Tanggal selesai</label>
                                        <input
                                            id="crud-event-end"
                                            type="date"
                                            className="db-input"
                                            value={form.data.event_end_date}
                                            onChange={(e) => form.setData('event_end_date', e.target.value)}
                                        />
                                        {form.errors.event_end_date && <div className="db-error">{form.errors.event_end_date}</div>}
                                        <div className="crud-hint">Minimal sama dengan tanggal acara.</div>
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
                                            <label className="crud-label" htmlFor="crud-agenda-published-at">Tanggal publikasi</label>
                                            <input
                                                id="crud-agenda-published-at"
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
                                    submitLabel={isCreate ? 'Simpan Agenda' : 'Simpan Perubahan'}
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
