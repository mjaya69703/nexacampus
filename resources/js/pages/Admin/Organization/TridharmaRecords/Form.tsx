// Form tambah/ubah portofolio Tridharma (penelitian, pengabdian, publikasi).
import { Head, useForm } from '@inertiajs/react';
import { Award, FileText, Paperclip, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type UserPayload = {
    id: number;
    name: string;
    email: string | null;
    username: string | null;
    code: string | null;
};

type Attachment = {
    id: number;
    fileName: string;
    documentType: string;
    fileSize: number;
    previewUrl: string;
};

type RecordPayload = {
    id?: number;
    type: string;
    title: string;
    scheme: string;
    abstract: string;
    starts_at: string;
    ends_at: string;
    status: string;
    funding_amount: number | string;
    funding_source: string;
    admin_notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    record: RecordPayload;
    owner: UserPayload | null;
    attachments: Attachment[];
    types: Option[];
    statuses: Option[];
    urls: { index: string; submit: string; searchUsers?: string; show?: string };
};

const ATTACH_ACCEPT = '.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xlsx';

const formatSize = (bytes: number) => {
    if (!bytes) return '0 KB';
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

export default function TridharmaRecordForm({ shell, mode, record, owner, attachments, types, statuses, urls }: Props) {
    const isCreate = mode === 'create';
    const [ownerOption, setOwnerOption] = useState<AsyncOption | null>(
        owner ? { id: owner.id, label: `${owner.name} (${owner.email ?? '-'})` } : null,
    );

    const form = useForm({
        user_id: owner?.id ?? (null as number | null),
        type: record.type,
        title: record.title,
        scheme: record.scheme,
        abstract: record.abstract,
        starts_at: record.starts_at,
        ends_at: record.ends_at,
        status: record.status,
        funding_amount: record.funding_amount === '' ? '' : String(record.funding_amount),
        funding_source: record.funding_source,
        admin_notes: record.admin_notes,
        attachment: null as File | null,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const onOwnerChange = (option: AsyncOption | null) => {
        setOwnerOption(option);
        form.setData('user_id', option ? Number(option.id) : null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Multipart selalu agar unggahan berkas tetap terkirim saat edit (spoof PUT).
        form.post(urls.submit, { forceFormData: true });
    };

    const cancelHref = isCreate ? urls.index : (urls.show ?? urls.index);

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Tridharma · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Kegiatan Tri Dharma Baru' : `Edit ${record.title}`}
                        description="Daftarkan portofolio penelitian, pengabdian masyarakat, atau publikasi beserta lampiran proposal dan rincian pendanaannya."
                        badges={isCreate ? ['Kegiatan baru'] : [types.find((type) => type.id === record.type)?.label ?? record.type]}
                        actions={<a className="db-btn light" href={cancelHref}>Kembali</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Award size={16} /> Data Kegiatan Tri Dharma</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            {isCreate ? (
                                                <AsyncSelect
                                                    label="Pegawai / dosen penanggung jawab (owner)"
                                                    required
                                                    fetchUrl={urls.searchUsers ?? ''}
                                                    value={ownerOption}
                                                    onChange={onOwnerChange}
                                                    error={form.errors.user_id}
                                                    hint="Ketik nama, email, username, atau NIDN/NIK untuk mencari dosen atau pegawai."
                                                    placeholder="Cari dosen / pegawai…"
                                                />
                                            ) : (
                                                <div>
                                                    <span className="crud-label">Pegawai / dosen penanggung jawab (owner)</span>
                                                    <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                        <span className="db-badge" style={{ width: 32, height: 32, justifyContent: 'center', borderRadius: 999, padding: 0 }}>
                                                            {(owner?.name ?? 'U').slice(0, 1).toUpperCase()}
                                                        </span>
                                                        <span style={{ flex: 1 }}>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{owner?.name ?? '-'}</b>
                                                            <small className="db-hint" style={{ marginTop: 0 }}>
                                                                {owner?.email ?? '-'}{owner?.username ? ` · ${owner.username}` : ''}{owner?.code ? ` · ${owner.code}` : ''}
                                                            </small>
                                                        </span>
                                                    </div>
                                                    <div className="crud-hint">Pemilik kegiatan tidak dapat diubah setelah data dibuat.</div>
                                                </div>
                                            )}
                                        </div>

                                        <SelectField
                                            label="Jenis kegiatan Tri Dharma"
                                            required
                                            value={form.data.type}
                                            onChange={(event) => form.setData('type', event.target.value)}
                                            error={form.errors.type}
                                        >
                                            {types.map((type) => (
                                                <option key={type.id} value={type.id}>{type.label}</option>
                                            ))}
                                        </SelectField>

                                        <TextField
                                            label="Judul kegiatan / publikasi"
                                            required
                                            placeholder="Tuliskan judul lengkap penelitian, pengabdian, atau luaran…"
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            error={form.errors.title}
                                        />

                                        <TextField
                                            label="Skema hibah / pendanaan"
                                            placeholder="Contoh: Hibah Internal Kampus / PDP Kemendikbud / Mandiri"
                                            value={form.data.scheme}
                                            onChange={(event) => form.setData('scheme', event.target.value)}
                                            error={form.errors.scheme}
                                        />

                                        <SelectField
                                            label="Status siklus kegiatan"
                                            required
                                            value={form.data.status}
                                            onChange={(event) => form.setData('status', event.target.value)}
                                            error={form.errors.status}
                                        >
                                            {statuses.map((status) => (
                                                <option key={status.id} value={status.id}>{status.label}</option>
                                            ))}
                                        </SelectField>

                                        <TextField
                                            label="Tanggal mulai kegiatan"
                                            type="date"
                                            value={form.data.starts_at}
                                            onChange={(event) => form.setData('starts_at', event.target.value)}
                                            error={form.errors.starts_at}
                                        />

                                        <TextField
                                            label="Tanggal selesai kegiatan"
                                            type="date"
                                            value={form.data.ends_at}
                                            onChange={(event) => form.setData('ends_at', event.target.value)}
                                            error={form.errors.ends_at}
                                            hint="Tidak boleh lebih awal dari tanggal mulai."
                                        />

                                        <TextField
                                            label="Lembaga / sumber dana"
                                            placeholder="Contoh: DRTPM Kemendikbud / LPPM / Swasta / Mandiri"
                                            value={form.data.funding_source}
                                            onChange={(event) => form.setData('funding_source', event.target.value)}
                                            error={form.errors.funding_source}
                                        />

                                        <TextField
                                            label="Nominal dana disetujui (Rp)"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            placeholder="0"
                                            value={form.data.funding_amount}
                                            onChange={(event) => form.setData('funding_amount', event.target.value)}
                                            error={form.errors.funding_amount}
                                        />

                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Abstrak / ringkasan deskripsi kegiatan"
                                                rows={4}
                                                placeholder="Tuliskan latar belakang singkat, tujuan, metodologi, dan target capaian kegiatan…"
                                                value={form.data.abstract}
                                                onChange={(event) => form.setData('abstract', event.target.value)}
                                                error={form.errors.abstract}
                                            />
                                        </div>

                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan khusus admin & asesor (internal)"
                                                rows={2}
                                                placeholder="Catatan internal yang hanya dapat dilihat oleh admin/operator pengelola Tri Dharma…"
                                                value={form.data.admin_notes}
                                                onChange={(event) => form.setData('admin_notes', event.target.value)}
                                                error={form.errors.admin_notes}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Paperclip size={16} /> Berkas Pendukung</h2>
                                </div>
                                <div className="db-card-body">
                                    {attachments.length > 0 && (
                                        <div style={{ display: 'grid', gap: 8, marginBottom: 14 }}>
                                            {attachments.map((attachment) => (
                                                <div key={attachment.id} className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                    <FileText size={15} />
                                                    <a
                                                        href={attachment.previewUrl}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        style={{ flex: 1, color: 'var(--db-brand)', fontWeight: 600, fontSize: 12.5 }}
                                                    >
                                                        {attachment.fileName}
                                                    </a>
                                                    <span className="db-hint" style={{ marginTop: 0 }}>{formatSize(attachment.fileSize)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    <span className="crud-label">
                                        {isCreate ? 'Lampiran proposal / evidence awal' : 'Unggah berkas baru'}
                                    </span>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept={ATTACH_ACCEPT}
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
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
                                    <div className="crud-hint">Maks 8MB. Format: PDF, DOC, DOCX, XLSX, JPG, JPEG, PNG, WEBP. Berkas disimpan privat dan hanya dapat diakses pengguna berwenang.</div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={cancelHref}
                                submitLabel={isCreate ? 'Simpan Rekor Tri Dharma' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
