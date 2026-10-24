// Form tambah/ubah event alumni.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, Image as ImageIcon, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type EventPayload = {
    id?: number;
    title: string;
    description: string;
    event_type: string;
    event_date: string;
    end_date: string;
    location: string;
    is_online: boolean;
    meeting_url: string;
    max_participants: number | string | null;
    registration_deadline: string;
    is_published: boolean;
    poster_url: string | null;
    participants_count: number;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    event: EventPayload;
    eventTypeOptions: Record<string, string>;
    urls: { index: string; submit: string };
};

export default function AlumniEventForm({ shell, mode, event, eventTypeOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [posterPreview, setPosterPreview] = useState<string | null>(event.poster_url);
    const [removePoster, setRemovePoster] = useState(false);

    const form = useForm({
        title: event.title,
        description: event.description,
        event_type: event.event_type,
        event_date: event.event_date,
        end_date: event.end_date,
        location: event.location,
        is_online: event.is_online,
        meeting_url: event.meeting_url,
        max_participants: String(event.max_participants ?? ''),
        registration_deadline: event.registration_deadline,
        is_published: event.is_published,
        poster: null as File | null,
        remove_poster: false,
    });

    const onPosterChange = (file: File | null) => {
        form.setData('poster', file);
        setRemovePoster(false);
        form.setData('remove_poster', false);

        if (posterPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(posterPreview);
        }

        setPosterPreview(file ? URL.createObjectURL(file) : event.poster_url);
    };

    const onRemovePoster = () => {
        if (posterPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(posterPreview);
        }

        setPosterPreview(null);
        setRemovePoster(true);
        form.setData((data) => ({ ...data, poster: null, remove_poster: true }));
    };

    const submit = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();

        if (isCreate) {
            form.post(urls.submit, { forceFormData: true });
            return;
        }

        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Event Alumni · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Alumni"
                        title={isCreate ? 'Jadwalkan Event Baru' : `Edit ${event.title}`}
                        description="Lengkapi informasi acara, jadwal, lokasi atau tautan virtual, kuota peserta, dan poster kegiatan."
                        badges={isCreate ? ['Event baru'] : [event.event_type]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Identitas & Jadwal Acara</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Judul event"
                                        required
                                        placeholder="Contoh: Webinar Karier Alumni 2026"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                        error={form.errors.title}
                                    />
                                    <SelectField
                                        label="Tipe event"
                                        required
                                        value={form.data.event_type}
                                        onChange={(e) => form.setData('event_type', e.target.value)}
                                        error={form.errors.event_type}
                                    >
                                        <option value="">-- Pilih tipe event --</option>
                                        {Object.entries(eventTypeOptions).map(([value, label]) => (
                                            <option key={value} value={value}>{label}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Mulai"
                                        required
                                        type="datetime-local"
                                        value={form.data.event_date}
                                        onChange={(e) => form.setData('event_date', e.target.value)}
                                        error={form.errors.event_date}
                                    />
                                    <TextField
                                        label="Selesai"
                                        type="datetime-local"
                                        min={form.data.event_date || undefined}
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                        error={form.errors.end_date}
                                        hint="Opsional. Tidak boleh lebih awal dari waktu mulai."
                                    />
                                    <TextField
                                        label="Batas pendaftaran"
                                        type="datetime-local"
                                        max={form.data.event_date || undefined}
                                        value={form.data.registration_deadline}
                                        onChange={(e) => form.setData('registration_deadline', e.target.value)}
                                        error={form.errors.registration_deadline}
                                        hint="Opsional. Tidak boleh melewati waktu mulai acara."
                                    />
                                    <TextField
                                        label="Kuota peserta"
                                        type="number"
                                        min={1}
                                        placeholder="Kosongkan bila tanpa batas"
                                        value={form.data.max_participants}
                                        onChange={(e) => form.setData('max_participants', e.target.value)}
                                        error={form.errors.max_participants}
                                        hint={isCreate ? undefined : `Saat ini ${event.participants_count} peserta terdaftar; kuota tidak boleh lebih kecil dari jumlah tersebut.`}
                                    />
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Lokasi & Akses</h3>
                                    <div className="crud-grid">
                                        <TextField
                                            label="Tempat / lokasi"
                                            placeholder="Contoh: Auditorium Kampus A"
                                            value={form.data.location}
                                            onChange={(e) => form.setData('location', e.target.value)}
                                            error={form.errors.location}
                                            hint="Wajib untuk acara offline; opsional untuk acara online."
                                        />
                                        <TextField
                                            label="Tautan meeting"
                                            type="url"
                                            placeholder="https://zoom.us/j/…"
                                            value={form.data.meeting_url}
                                            onChange={(e) => form.setData('meeting_url', e.target.value)}
                                            error={form.errors.meeting_url}
                                            hint="Wajib diisi bila event diselenggarakan online."
                                        />
                                        <SwitchField
                                            label="Event daring (online)"
                                            checked={form.data.is_online}
                                            onChange={(value) => form.setData('is_online', value)}
                                            error={form.errors.is_online}
                                        />
                                        <SwitchField
                                            label="Terbitkan ke portal alumni"
                                            checked={form.data.is_published}
                                            onChange={(value) => form.setData('is_published', value)}
                                            error={form.errors.is_published}
                                            hint="Event draft tidak tampil dan pendaftaran dianggap belum dibuka."
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Deskripsi & Poster</h3>
                                    <div className="crud-grid-1">
                                        <TextareaField
                                            label="Deskripsi acara"
                                            rows={5}
                                            placeholder="Jelaskan agenda, pembicara, dan manfaat acara bagi alumni…"
                                            value={form.data.description}
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={form.errors.description}
                                        />

                                        <div>
                                            <span className="crud-label">Poster event</span>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
                                                {posterPreview ? (
                                                    <span style={{ position: 'relative', display: 'inline-flex' }}>
                                                        <img
                                                            src={posterPreview}
                                                            alt="Pratinjau poster"
                                                            style={{ width: 132, height: 92, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--db-line)' }}
                                                        />
                                                        <button
                                                            type="button"
                                                            className="db-btn danger sm"
                                                            onClick={onRemovePoster}
                                                            title="Hapus poster"
                                                            style={{ position: 'absolute', top: -8, right: -8, padding: 4, borderRadius: 999 }}
                                                        >
                                                            <X size={12} />
                                                        </button>
                                                    </span>
                                                ) : (
                                                    <span className="db-btn ghost sm" style={{ pointerEvents: 'none' }}>
                                                        <ImageIcon size={14} /> Belum ada poster
                                                    </span>
                                                )}
                                                <input
                                                    className="db-input"
                                                    style={{ maxWidth: 320 }}
                                                    type="file"
                                                    accept="image/jpeg,image/png,image/webp"
                                                    onChange={(e) => onPosterChange(e.target.files?.[0] ?? null)}
                                                />
                                            </div>
                                            {removePoster && <div className="crud-hint">Poster lama akan dihapus saat disimpan.</div>}
                                            {form.errors.poster && <div className="db-error">{form.errors.poster}</div>}
                                        </div>
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Event' : 'Simpan Perubahan'}
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
