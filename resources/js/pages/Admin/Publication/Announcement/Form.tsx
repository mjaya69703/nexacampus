// Form tambah/ubah pengumuman.
import { Head, useForm } from '@inertiajs/react';
import { Megaphone, Paperclip, X } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

const RichTextField = lazy(() =>
    import('../../../../components/Shared/Crud/RichTextField').then((m) => ({ default: m.RichTextField })),
);

type AnnouncementPayload = {
    id?: number;
    title: string;
    content: string;
    target_type: string;
    target_id: number | null;
    target_label: string | null;
    priority: string;
    is_pinned: boolean;
    is_published: boolean;
    published_at: string;
    scheduled_at: string;
    attachment_name: string | null;
    attachment_size?: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    announcement: AnnouncementPayload;
    targetTypeOptions: { id: string; name: string }[];
    priorityOptions: { id: string; name: string }[];
    urls: { index: string; submit: string; searchTargets: string };
};

const ATTACH_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png';

export default function AnnouncementForm({ shell, mode, announcement, targetTypeOptions, priorityOptions, urls }: Props) {
    const isCreate = mode === 'create';
    const [targetOption, setTargetOption] = useState<AsyncOption | null>(
        announcement.target_id && announcement.target_label
            ? { id: announcement.target_id, label: announcement.target_label }
            : null,
    );

    const form = useForm({
        title: announcement.title,
        content: announcement.content,
        target_type: announcement.target_type,
        target_id: announcement.target_id as number | null,
        priority: announcement.priority,
        is_pinned: announcement.is_pinned,
        is_published: announcement.is_published,
        published_at: announcement.published_at,
        scheduled_at: announcement.scheduled_at,
        attachment: null as File | null,
        remove_attachment: false as boolean,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const needsTarget = form.data.target_type !== 'global';

    const onTargetTypeChange = (value: string) => {
        form.setData('target_type', value);
        form.setData('target_id', null);
        setTargetOption(null);
    };

    const onTargetChange = (option: AsyncOption | null) => {
        setTargetOption(option);
        form.setData('target_id', option ? Number(option.id) : null);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            // Multipart selalu (spoof PUT + dukung lampiran) — lihat pola di atas.
            form.post(urls.submit, { forceFormData: true });
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Buat' : 'Edit'} Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Megaphone}
                        eyebrow="Publikasi"
                        title={isCreate ? 'Buat Pengumuman' : `Edit ${announcement.title}`}
                        description="Tentukan target penerima, prioritas, dan jadwal publikasi agar informasi tepat sasaran."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Megaphone size={16} /> Form Pengumuman</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextField
                                            label="Judul pengumuman"
                                            required
                                            placeholder="Masukkan judul pengumuman…"
                                            value={form.data.title}
                                            onChange={(e) => form.setData('title', e.target.value)}
                                            error={form.errors.title}
                                        />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <Suspense fallback={<div className="db-input">Memuat editor…</div>}>
                                            <RichTextField
                                                label="Isi pengumuman"
                                                required
                                                height={350}
                                                value={form.data.content}
                                                onChange={(v) => form.setData('content', v)}
                                                error={form.errors.content}
                                            />
                                        </Suspense>
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Lampiran</span>
                                        {!isCreate && announcement.attachment_name && !form.data.remove_attachment && !form.data.attachment && (
                                            <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
                                                <Paperclip size={14} />
                                                <span style={{ flex: 1 }}>{announcement.attachment_name} ({announcement.attachment_size})</span>
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer', fontSize: 12 }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={form.data.remove_attachment}
                                                        onChange={(e) => form.setData('remove_attachment', e.target.checked)}
                                                    />
                                                    Hapus
                                                </label>
                                            </div>
                                        )}
                                        <input
                                            type="file"
                                            className="db-input"
                                            accept={ATTACH_ACCEPT}
                                            onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)}
                                        />
                                        {form.data.attachment && (
                                            <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                                                <Paperclip size={14} />
                                                <span style={{ flex: 1 }}>{form.data.attachment.name}</span>
                                                <button className="db-btn ghost sm" type="button" onClick={() => form.setData('attachment', null)}>
                                                    <X size={13} /> Batal
                                                </button>
                                            </div>
                                        )}
                                        {form.errors.attachment && <div className="db-error">{form.errors.attachment}</div>}
                                        <div className="crud-hint">Maks 5MB. Format: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.</div>
                                    </div>
                                    <SelectField
                                        label="Tipe target"
                                        required
                                        value={form.data.target_type}
                                        onChange={(e) => onTargetTypeChange(e.target.value)}
                                        error={form.errors.target_type}
                                        hint="Global = semua pengguna."
                                    >
                                        {targetTypeOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    {needsTarget && (
                                        <AsyncSelect
                                            key={form.data.target_type}
                                            label="Pilih target"
                                            required
                                            fetchUrl={`${urls.searchTargets}/${form.data.target_type}`}
                                            value={targetOption}
                                            onChange={onTargetChange}
                                            error={form.errors.target_id}
                                            hint="Ketik untuk mencari (maks 20 hasil)."
                                        />
                                    )}
                                    <SelectField
                                        label="Prioritas"
                                        value={form.data.priority}
                                        onChange={(e) => form.setData('priority', e.target.value)}
                                        error={form.errors.priority}
                                    >
                                        {priorityOptions.map((opt) => (
                                            <option key={opt.id} value={opt.id}>{opt.name}</option>
                                        ))}
                                    </SelectField>
                                    <SwitchField
                                        label="Pin pengumuman"
                                        checked={form.data.is_pinned}
                                        onChange={(v) => form.setData('is_pinned', v)}
                                        error={form.errors.is_pinned}
                                        hint="Yang di-pin selalu di urutan teratas."
                                    />
                                    <SwitchField
                                        label="Publikasikan"
                                        checked={form.data.is_published}
                                        onChange={(v) => form.setData('is_published', v)}
                                        error={form.errors.is_published}
                                        hint="Draft disembunyikan dari penerima."
                                    />
                                    {form.data.is_published && (
                                        <div>
                                            <label className="crud-label" htmlFor="crud-published-at">Tanggal publikasi</label>
                                            <input
                                                id="crud-published-at"
                                                type="datetime-local"
                                                className="db-input"
                                                value={form.data.published_at}
                                                onChange={(e) => form.setData('published_at', e.target.value)}
                                            />
                                            {form.errors.published_at && <div className="db-error">{form.errors.published_at}</div>}
                                        </div>
                                    )}
                                    <div>
                                        <label className="crud-label" htmlFor="crud-scheduled-at">Jadwalkan (opsional)</label>
                                        <input
                                            id="crud-scheduled-at"
                                            type="datetime-local"
                                            className="db-input"
                                            value={form.data.scheduled_at}
                                            onChange={(e) => form.setData('scheduled_at', e.target.value)}
                                        />
                                        {form.errors.scheduled_at && <div className="db-error">{form.errors.scheduled_at}</div>}
                                        <div className="crud-hint">Kosongkan bila tidak dijadwalkan. Terbit ditunda sampai waktu tiba.</div>
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Pengumuman' : 'Simpan Perubahan'}
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
