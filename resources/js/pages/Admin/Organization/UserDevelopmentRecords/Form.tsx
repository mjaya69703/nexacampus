// Form tambah/ubah riwayat sertifikasi & pelatihan pegawai.
import { Head, useForm } from '@inertiajs/react';
import { Award, BadgeCheck, FileText, Paperclip, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
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
    fileSize: number;
    previewUrl: string;
};

type RecordPayload = {
    id?: number;
    type: string;
    title: string;
    organizer: string;
    credential_number: string;
    start_date: string;
    end_date: string;
    expires_at: string;
    cost: number | string;
    description: string;
    is_verified: boolean;
    verification_notes: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    record: RecordPayload;
    user: UserPayload | null;
    attachments: Attachment[];
    types: Option[];
    urls: { index: string; submit: string; searchUsers?: string };
};

const ATTACH_ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp';

const formatSize = (bytes: number) => {
    if (!bytes) return '0 KB';
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
};

export default function UserDevelopmentRecordForm({ shell, mode, record, user, attachments, types, urls }: Props) {
    const isCreate = mode === 'create';
    const [userOption, setUserOption] = useState<AsyncOption | null>(
        user ? { id: user.id, label: `${user.name} (${user.email ?? '-'})` } : null,
    );

    const form = useForm({
        user_id: user?.id ?? null as number | null,
        type: record.type,
        title: record.title,
        organizer: record.organizer,
        credential_number: record.credential_number,
        start_date: record.start_date,
        end_date: record.end_date,
        expires_at: record.expires_at,
        cost: record.cost === '' ? '' : String(record.cost),
        description: record.description,
        is_verified: record.is_verified,
        verification_notes: record.verification_notes,
        attachment: null as File | null,
        ...(isCreate ? {} : { _method: 'PUT' as const }),
    });

    const onUserChange = (option: AsyncOption | null) => {
        setUserOption(option);
        form.setData('user_id', option ? Number(option.id) : null);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Multipart selalu agar unggahan berkas tetap terkirim saat edit (spoof PUT).
        form.post(urls.submit, { forceFormData: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Sertifikasi & Pelatihan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Riwayat Pengembangan' : `Edit ${record.title}`}
                        description="Catat sertifikasi profesi, pelatihan, workshop, seminar, penghargaan, atau lisensi pegawai beserta berkas pendukungnya."
                        badges={isCreate ? ['Riwayat baru'] : [types.find((type) => type.id === record.type)?.label ?? record.type]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Award size={16} /> Data Pengembangan Kompetensi</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            {isCreate ? (
                                                <AsyncSelect
                                                    label="Pegawai pemilik riwayat"
                                                    required
                                                    fetchUrl={urls.searchUsers ?? ''}
                                                    value={userOption}
                                                    onChange={onUserChange}
                                                    error={form.errors.user_id}
                                                    hint="Ketik nama, email, username, atau NIDN/NIK untuk mencari pegawai."
                                                    placeholder="Cari dosen / pegawai…"
                                                />
                                            ) : (
                                                <div>
                                                    <span className="crud-label">Pegawai pemilik riwayat</span>
                                                    <div className="db-note" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                        <span className="db-badge" style={{ width: 32, height: 32, justifyContent: 'center', borderRadius: 999, padding: 0 }}>
                                                            {(user?.name ?? 'U').slice(0, 1).toUpperCase()}
                                                        </span>
                                                        <span style={{ flex: 1 }}>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{user?.name ?? '-'}</b>
                                                            <small className="db-hint" style={{ marginTop: 0 }}>
                                                                {user?.email ?? '-'}{user?.username ? ` · ${user.username}` : ''}{user?.code ? ` · ${user.code}` : ''}
                                                            </small>
                                                        </span>
                                                    </div>
                                                    <div className="crud-hint">Pemilik riwayat tidak dapat diubah setelah data dibuat.</div>
                                                </div>
                                            )}
                                        </div>

                                        <SelectField
                                            label="Tipe pengembangan"
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
                                            label="Judul sertifikasi / pelatihan"
                                            required
                                            placeholder="Contoh: Sertifikasi Auditor Internal ISO 9001:2015"
                                            value={form.data.title}
                                            onChange={(event) => form.setData('title', event.target.value)}
                                            error={form.errors.title}
                                        />

                                        <TextField
                                            label="Penyelenggara / instansi"
                                            placeholder="Contoh: Badan Standardisasi Nasional"
                                            value={form.data.organizer}
                                            onChange={(event) => form.setData('organizer', event.target.value)}
                                            error={form.errors.organizer}
                                        />

                                        <TextField
                                            label="Nomor kredensial / sertifikat"
                                            placeholder="Contoh: ISO/IA/2026/00123"
                                            value={form.data.credential_number}
                                            onChange={(event) => form.setData('credential_number', event.target.value)}
                                            error={form.errors.credential_number}
                                        />

                                        <TextField
                                            label="Tanggal mulai"
                                            type="date"
                                            required
                                            value={form.data.start_date}
                                            onChange={(event) => form.setData('start_date', event.target.value)}
                                            error={form.errors.start_date}
                                        />

                                        <TextField
                                            label="Tanggal selesai"
                                            type="date"
                                            value={form.data.end_date}
                                            onChange={(event) => form.setData('end_date', event.target.value)}
                                            error={form.errors.end_date}
                                            hint="Kosongkan jika kegiatan berlangsung sehari atau masih berjalan."
                                        />

                                        <TextField
                                            label="Berlaku sampai"
                                            type="date"
                                            value={form.data.expires_at}
                                            onChange={(event) => form.setData('expires_at', event.target.value)}
                                            error={form.errors.expires_at}
                                            hint="Kosongkan jika sertifikat tidak memiliki masa berlaku."
                                        />

                                        <TextField
                                            label="Biaya pengembangan (Rp)"
                                            type="number"
                                            min={0}
                                            step="0.01"
                                            placeholder="0"
                                            value={form.data.cost}
                                            onChange={(event) => form.setData('cost', event.target.value)}
                                            error={form.errors.cost}
                                        />

                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi / catatan kegiatan"
                                                rows={3}
                                                placeholder="Ringkasan materi, capaian pembelajaran, atau keterangan tambahan…"
                                                value={form.data.description}
                                                onChange={(event) => form.setData('description', event.target.value)}
                                                error={form.errors.description}
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

                                    <span className="crud-label">Unggah berkas baru</span>
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
                                    <div className="crud-hint">Maks 5MB. Format: PDF, JPG, JPEG, PNG, WEBP. Berkas disimpan privat dan hanya dapat diakses pengguna berwenang.</div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><BadgeCheck size={16} /> Verifikasi Asesor</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <SwitchField
                                            label="Status verifikasi"
                                            checked={form.data.is_verified}
                                            onChange={(value) => form.setData('is_verified', value)}
                                            error={form.errors.is_verified}
                                            onLabel="Terverifikasi"
                                            offLabel="Menunggu verifikasi"
                                            hint="Verifikasi mencatat nama asesor dan waktu verifikasi secara otomatis."
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan verifikasi"
                                                rows={2}
                                                placeholder="Catatan asesor mengenai keabsahan berkas atau hasil verifikasi…"
                                                value={form.data.verification_notes}
                                                onChange={(event) => form.setData('verification_notes', event.target.value)}
                                                error={form.errors.verification_notes}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Riwayat' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
