// Form tambah/ubah event kemahasiswaan (admin).
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, ImagePlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    event: {
        id: number | null;
        title: string;
        category: string;
        student_organization_id: string;
        organizer: string;
        location: string;
        starts_at: string;
        ends_at: string;
        registration_deadline: string;
        quota: number | string;
        points: number;
        status: string;
        is_featured: boolean;
        description: string;
        cover_url: string | null;
    };
    options: {
        categories: Record<string, string>;
        statuses: Record<string, string>;
        organizations: Array<{ id: number; name: string }>;
    };
    urls: { index: string; submit: string };
};

export default function EventForm({ shell, mode, event, options, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        title: event.title,
        category: event.category,
        student_organization_id: event.student_organization_id,
        organizer: event.organizer,
        location: event.location,
        starts_at: event.starts_at,
        ends_at: event.ends_at,
        registration_deadline: event.registration_deadline,
        quota: event.quota,
        points: event.points,
        status: event.status,
        is_featured: event.is_featured,
        description: event.description,
        cover: null as File | null,
        remove_cover: false,
    });

    const submit = (formEvent: FormEvent) => {
        formEvent.preventDefault();

        const payload = {
            ...form.data,
            student_organization_id: form.data.student_organization_id === '' ? null : form.data.student_organization_id,
            quota: form.data.quota === '' ? null : form.data.quota,
            ends_at: form.data.ends_at === '' ? null : form.data.ends_at,
            registration_deadline: form.data.registration_deadline === '' ? null : form.data.registration_deadline,
        };

        form.transform(() => payload);

        if (isCreate) {
            form.post(urls.submit, { forceFormData: true });
            return;
        }

        form.put(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Event Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kemahasiswaan"
                        title={isCreate ? 'Tambah Event Mahasiswa' : `Edit ${event.title}`}
                        description="Atur jadwal, kuota, poin keikutsertaan, dan status publikasi event."
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Informasi Event</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextField
                                                label="Judul event"
                                                required
                                                value={form.data.title}
                                                onChange={(e) => form.setData('title', e.target.value)}
                                                error={form.errors.title}
                                                placeholder="Contoh: Seminar Nasional Kepemimpinan Mahasiswa"
                                            />
                                        </div>
                                        <SelectField
                                            label="Kategori"
                                            required
                                            value={form.data.category}
                                            onChange={(e) => form.setData('category', e.target.value)}
                                            error={form.errors.category}
                                        >
                                            {Object.entries(options.categories).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Organisasi penyelenggara"
                                            value={form.data.student_organization_id}
                                            onChange={(e) => form.setData('student_organization_id', e.target.value)}
                                            error={form.errors.student_organization_id}
                                            hint="Opsional — kosongkan untuk event lintas organisasi."
                                        >
                                            <option value="">Lintas organisasi</option>
                                            {options.organizations.map((organization) => (
                                                <option key={organization.id} value={organization.id}>{organization.name}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Nama penyelenggara"
                                            value={form.data.organizer}
                                            onChange={(e) => form.setData('organizer', e.target.value)}
                                            error={form.errors.organizer}
                                            placeholder="Contoh: BEM NexaCampus"
                                        />
                                        <TextField
                                            label="Lokasi"
                                            value={form.data.location}
                                            onChange={(e) => form.setData('location', e.target.value)}
                                            error={form.errors.location}
                                            placeholder="Contoh: Aula Gedung A"
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi"
                                                rows={4}
                                                value={form.data.description}
                                                onChange={(e) => form.setData('description', e.target.value)}
                                                error={form.errors.description}
                                                placeholder="Tujuan, agenda, dan informasi penting lainnya."
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Jadwal & Kuota</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Mulai"
                                            type="datetime-local"
                                            required
                                            value={form.data.starts_at}
                                            onChange={(e) => form.setData('starts_at', e.target.value)}
                                            error={form.errors.starts_at}
                                        />
                                        <TextField
                                            label="Selesai"
                                            type="datetime-local"
                                            value={form.data.ends_at}
                                            onChange={(e) => form.setData('ends_at', e.target.value)}
                                            error={form.errors.ends_at}
                                        />
                                        <TextField
                                            label="Batas pendaftaran"
                                            type="datetime-local"
                                            value={form.data.registration_deadline}
                                            onChange={(e) => form.setData('registration_deadline', e.target.value)}
                                            error={form.errors.registration_deadline}
                                            hint="Kosongkan bila pendaftaran dibuka sampai acara dimulai."
                                        />
                                        <TextField
                                            label="Kuota peserta"
                                            type="number"
                                            min={1}
                                            value={form.data.quota}
                                            onChange={(e) => form.setData('quota', e.target.value)}
                                            error={form.errors.quota}
                                            hint="Kosongkan bila tidak ada batas peserta."
                                        />
                                        <TextField
                                            label="Poin keikutsertaan"
                                            type="number"
                                            min={0}
                                            required
                                            value={form.data.points}
                                            onChange={(e) => form.setData('points', Number(e.target.value))}
                                            error={form.errors.points}
                                            hint="Diberikan otomatis saat kehadiran dicatat."
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Publikasi</h2></div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Status event"
                                            required
                                            value={form.data.status}
                                            onChange={(e) => form.setData('status', e.target.value)}
                                            error={form.errors.status}
                                            hint="Hanya event dipublikasikan/berlangsung yang tampil di portal mahasiswa."
                                        >
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </SelectField>
                                        <SwitchField
                                            label="Sorotan"
                                            checked={form.data.is_featured}
                                            onChange={(value) => form.setData('is_featured', value)}
                                            onLabel="Tampil sebagai sorotan"
                                            offLabel="Tidak disorot"
                                        />
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Cover Event</h2></div>
                                <div className="db-card-body">
                                    {event.cover_url && (
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                                            <img
                                                src={event.cover_url}
                                                alt=""
                                                style={{ width: 92, height: 56, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--db-line)' }}
                                            />
                                            <div style={{ flex: 1 }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>Cover saat ini</b>
                                                <a className="db-hint" href={event.cover_url} target="_blank" rel="noopener noreferrer">Lihat gambar</a>
                                            </div>
                                            <label className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.remove_cover}
                                                    onChange={(e) => form.setData('remove_cover', e.target.checked)}
                                                />
                                                Hapus
                                            </label>
                                        </div>
                                    )}
                                    <label className="crud-label" htmlFor="event-cover"><ImagePlus size={13} /> Unggah cover baru</label>
                                    <input
                                        id="event-cover"
                                        className="db-input"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(e) => form.setData('cover', e.target.files?.[0] ?? null)}
                                    />
                                    {form.errors.cover && <div className="db-error">{form.errors.cover}</div>}
                                    <div className="crud-hint">Format PNG, JPG, atau WEBP. Maksimal 4 MB.</div>
                                </div>
                            </section>

                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Event' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
