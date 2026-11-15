// Form tambah/ubah jenis cuti pegawai.
import { Head, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Template = { id: number; name: string; code: string };

type LeaveTypePayload = {
    id?: number;
    name: string;
    code: string;
    approval_template_id: number | string;
    default_days_per_year: number;
    requires_approval: boolean;
    is_paid: boolean;
    is_active: boolean;
    description: string;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    leaveType: LeaveTypePayload;
    templates: Template[];
    urls: { index: string; submit: string };
};

export default function EmployeeLeaveTypeForm({ shell, mode, leaveType, templates, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: leaveType.name,
        code: leaveType.code,
        approval_template_id: leaveType.approval_template_id === null ? '' : String(leaveType.approval_template_id),
        default_days_per_year: leaveType.default_days_per_year,
        requires_approval: leaveType.requires_approval,
        is_paid: leaveType.is_paid,
        is_active: leaveType.is_active,
        description: leaveType.description,
    });

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Jenis Cuti Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Jenis Cuti Pegawai' : `Edit ${leaveType.name}`}
                        description="Tentukan klasifikasi cuti, jatah default per tahun, status digaji, serta template alur persetujuannya."
                        badges={isCreate ? ['Jenis cuti baru'] : [leaveType.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Identitas Jenis Cuti</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama jenis cuti"
                                        required
                                        placeholder="Contoh: Cuti Tahunan"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode klasifikasi"
                                        required
                                        placeholder="Contoh: ANNUAL_LEAVE"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Kode unik (huruf kapital) untuk identifikasi sistem."
                                    />
                                    <TextField
                                        label="Jatah default (hari/tahun)"
                                        type="number"
                                        min={0}
                                        step={0.5}
                                        value={form.data.default_days_per_year}
                                        onChange={(event) => form.setData('default_days_per_year', event.target.value === '' ? 0 : Number(event.target.value))}
                                        error={form.errors.default_days_per_year}
                                        hint="Jatah hari cuti yang otomatis dialokasikan ke pegawai."
                                    />
                                    <SelectField
                                        label="Template alur persetujuan"
                                        value={form.data.approval_template_id}
                                        onChange={(event) => form.setData('approval_template_id', event.target.value)}
                                        error={form.errors.approval_template_id}
                                        hint="Kosongkan untuk memakai persetujuan standar atasan langsung."
                                    >
                                        <option value="">Tanpa template (persetujuan standar atasan)</option>
                                        {templates.map((template) => (
                                            <option key={template.id} value={template.id}>{template.name} ({template.code})</option>
                                        ))}
                                    </SelectField>
                                    <SwitchField
                                        label="Wajib approval"
                                        checked={form.data.requires_approval}
                                        onChange={(value) => form.setData('requires_approval', value)}
                                        error={form.errors.requires_approval}
                                        hint="Jika aktif, pengajuan cuti harus melewati alur persetujuan."
                                        onLabel="Ya, wajib"
                                        offLabel="Tidak wajib"
                                    />
                                    <SwitchField
                                        label="Digaji (paid leave)"
                                        checked={form.data.is_paid}
                                        onChange={(value) => form.setData('is_paid', value)}
                                        error={form.errors.is_paid}
                                        hint="Menentukan apakah cuti ini tetap dihitung sebagai hari kerja bergaji."
                                        onLabel="Digaji"
                                        offLabel="Tidak digaji"
                                    />
                                    <SwitchField
                                        label="Status jenis cuti aktif"
                                        checked={form.data.is_active}
                                        onChange={(value) => form.setData('is_active', value)}
                                        error={form.errors.is_active}
                                        hint="Jenis cuti nonaktif tidak muncul pada pilihan pengajuan baru."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi & syarat pengajuan"
                                            rows={3}
                                            placeholder="Jelaskan ketentuan penggunaan jenis cuti ini atau dokumen yang wajib dilampirkan…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Jenis Cuti' : 'Simpan Perubahan'}
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
