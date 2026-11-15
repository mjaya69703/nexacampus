// Form tambah/ubah profil pegawai.
import { Head, useForm } from '@inertiajs/react';
import { IdCard, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type ProfilePayload = {
    id?: number;
    user: { id: number | null; name: string; email: string | null; username: string | null } | null;
    employee_number: string;
    employment_type: string;
    employment_status: string;
    join_date: string;
    end_date: string;
    primary_work_unit_id: string;
    notes: string;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    profile: ProfilePayload;
    options: {
        employmentTypes: Option[];
        employmentStatuses: Option[];
        workUnits: Option[];
    };
    urls: { index: string; submit: string; searchUsers?: string };
};

export default function EmployeeProfileForm({ shell, mode, profile, options, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        user_id: profile.user?.id ?? null,
        employee_number: profile.employee_number,
        employment_type: profile.employment_type,
        employment_status: profile.employment_status,
        join_date: profile.join_date,
        end_date: profile.end_date,
        primary_work_unit_id: profile.primary_work_unit_id,
        notes: profile.notes,
        is_active: profile.is_active,
    });

    const selectedUser: AsyncOption | null = profile.user?.id
        ? {
            id: profile.user.id,
            label: profile.user.name,
            meta: { email: profile.user.email, username: profile.user.username },
        }
        : form.data.user_id
            ? { id: form.data.user_id, label: 'Pegawai terpilih' }
            : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={IdCard}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Profil Pegawai' : `Edit ${profile.user?.name ?? 'Profil Pegawai'}`}
                        description="Tautkan akun pengguna dengan data kepegawaian agar penugasan jabatan, absensi, cuti, dan alur persetujuan dapat berjalan."
                        badges={isCreate ? ['Profil pegawai baru'] : [profile.employee_number || 'Tanpa nomor pegawai']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><UserRound size={16} /> Akun Pengguna</h2>
                                </div>
                                <div className="db-card-body">
                                    {isCreate && urls.searchUsers ? (
                                        <AsyncSelect
                                            label="Cari akun pengguna"
                                            required
                                            fetchUrl={urls.searchUsers}
                                            value={selectedUser}
                                            onChange={(option) => form.setData('user_id', option ? Number(option.id) : null)}
                                            error={form.errors.user_id}
                                            hint="Hanya pengguna aktif yang belum memiliki profil pegawai yang ditampilkan."
                                            placeholder="Cari nama, email, username, atau kode pengguna…"
                                        />
                                    ) : (
                                        <div style={{ padding: '12px 14px', border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-card)' }}>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{profile.user?.name ?? '-'}</b>
                                            <small className="db-hint">
                                                {profile.user?.email ?? '-'}{profile.user?.username ? ` · ${profile.user.username}` : ''}
                                            </small>
                                            <div className="db-hint" style={{ marginTop: 6 }}>Akun pengguna tidak dapat diubah setelah profil dibuat.</div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><IdCard size={16} /> Data Kepegawaian</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nomor induk pegawai (NIP/NIDN)"
                                            placeholder="Contoh: 198504122010011002"
                                            value={form.data.employee_number}
                                            onChange={(event) => form.setData('employee_number', event.target.value)}
                                            error={form.errors.employee_number}
                                            hint="Opsional, namun disarankan unik untuk identifikasi internal."
                                        />
                                        <SelectField
                                            label="Jenis kepegawaian"
                                            required
                                            value={form.data.employment_type}
                                            onChange={(event) => form.setData('employment_type', event.target.value)}
                                            error={form.errors.employment_type}
                                        >
                                            {options.employmentTypes.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Status kerja"
                                            required
                                            value={form.data.employment_status}
                                            onChange={(event) => form.setData('employment_status', event.target.value)}
                                            error={form.errors.employment_status}
                                        >
                                            {options.employmentStatuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                        <SelectField
                                            label="Unit kerja utama"
                                            value={form.data.primary_work_unit_id}
                                            onChange={(event) => form.setData('primary_work_unit_id', event.target.value)}
                                            error={form.errors.primary_work_unit_id}
                                        >
                                            <option value="">Belum ditentukan</option>
                                            {options.workUnits.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Tanggal mulai kerja"
                                            type="date"
                                            value={form.data.join_date}
                                            onChange={(event) => form.setData('join_date', event.target.value)}
                                            error={form.errors.join_date}
                                        />
                                        <TextField
                                            label="Tanggal berakhir"
                                            type="date"
                                            value={form.data.end_date}
                                            onChange={(event) => form.setData('end_date', event.target.value)}
                                            error={form.errors.end_date}
                                            hint="Isi bila masa kerja berbatas waktu (kontrak)."
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan kepegawaian"
                                                rows={3}
                                                placeholder="Catatan tambahan mengenai status, riwayat, atau kondisi khusus pegawai…"
                                                value={form.data.notes}
                                                onChange={(event) => form.setData('notes', event.target.value)}
                                                error={form.errors.notes}
                                            />
                                        </div>
                                        <SwitchField
                                            label="Status keaktifan profil pegawai"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Profil nonaktif tidak muncul pada picker penugasan, cuti, dan absensi."
                                        />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Profil Pegawai' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
