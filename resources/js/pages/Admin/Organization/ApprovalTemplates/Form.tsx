// Form tambah/ubah template alur persetujuan dengan langkah dinamis.
import { Head, useForm } from '@inertiajs/react';
import { GitBranch, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type Step = {
    name: string;
    approver_type: string;
    approver_role: string;
    approver_permission: string;
    organizational_position_id: string;
    work_unit_id: string;
    is_required: boolean;
    can_reject: boolean;
    sla_hours: string;
    description: string;
};

type TemplatePayload = {
    id?: number;
    name: string;
    code: string;
    module: string;
    description: string;
    is_active: boolean;
    steps: Step[];
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    template: TemplatePayload;
    options: {
        approverTypes: Option[];
        roles: Option[];
        permissions: Option[];
        positions: Option[];
        workUnits: Option[];
        modules: Option[];
    };
    urls: { index: string; submit: string };
};

const defaultStep = (): Step => ({
    name: '',
    approver_type: 'permission',
    approver_role: '',
    approver_permission: '',
    organizational_position_id: '',
    work_unit_id: '',
    is_required: true,
    can_reject: true,
    sla_hours: '',
    description: '',
});

export default function ApprovalTemplateForm({ shell, mode, template, options, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: template.name,
        code: template.code,
        module: template.module,
        description: template.description,
        is_active: template.is_active,
        steps: template.steps.length > 0 ? template.steps : [defaultStep()],
    });

    const updateStep = (index: number, patch: Partial<Step>) => {
        form.setData('steps', form.data.steps.map((step, current) => (
            current === index ? { ...step, ...patch } : step
        )));
    };

    const changeApproverType = (index: number, value: string) => {
        updateStep(index, {
            approver_type: value,
            approver_role: '',
            approver_permission: '',
            organizational_position_id: '',
            work_unit_id: '',
        });
    };

    const addStep = () => {
        form.setData('steps', [...form.data.steps, defaultStep()]);
    };

    const removeStep = (index: number) => {
        if (form.data.steps.length <= 1) return;
        form.setData('steps', form.data.steps.filter((_, current) => current !== index));
    };

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
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Template Approval · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GitBranch}
                        eyebrow="Kepegawaian"
                        title={isCreate ? 'Tambah Template Approval' : `Edit ${template.name}`}
                        description="Susun alur persetujuan berjenjang. Setiap langkah menentukan siapa yang berwenang memutuskan dan berapa lama tenggat penyelesaiannya."
                        badges={isCreate ? ['Template baru'] : [template.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><GitBranch size={16} /> Identitas Template</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nama template"
                                            required
                                            placeholder="Contoh: Persetujuan Cuti Tahunan Pegawai"
                                            value={form.data.name}
                                            onChange={(event) => form.setData('name', event.target.value)}
                                            error={form.errors.name}
                                        />
                                        <TextField
                                            label="Kode template"
                                            required
                                            placeholder="Contoh: CUTI_TAHUNAN"
                                            value={form.data.code}
                                            onChange={(event) => form.setData('code', event.target.value)}
                                            error={form.errors.code}
                                            hint="Kode unik (otomatis huruf kapital) yang dipakai modul untuk memanggil alur ini."
                                            style={{ textTransform: 'uppercase' }}
                                        />
                                        <TextField
                                            label="Modul"
                                            required
                                            placeholder="Contoh: organization / student-services"
                                            value={form.data.module}
                                            onChange={(event) => form.setData('module', event.target.value)}
                                            error={form.errors.module}
                                            hint="Kelompok modul pemilik alur persetujuan."
                                        />
                                        <SwitchField
                                            label="Status template"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Template nonaktif tidak dapat dipakai untuk pengajuan baru."
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Deskripsi template"
                                                rows={3}
                                                placeholder="Jelaskan tujuan dan cakupan alur persetujuan ini…"
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
                                    <h2 className="db-card-title"><ShieldCheck size={16} /> Langkah Persetujuan ({form.data.steps.length})</h2>
                                    <button className="db-btn primary sm" type="button" onClick={addStep}>
                                        <Plus size={14} /> Tambah Langkah
                                    </button>
                                </div>
                                <div className="db-card-body">
                                    <div className="db-stack">
                                        {form.data.steps.map((step, index) => (
                                            <div
                                                key={index}
                                                style={{
                                                    border: '1px solid var(--db-line)',
                                                    borderRadius: 14,
                                                    padding: 16,
                                                    background: 'var(--db-card)',
                                                }}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, marginBottom: 12 }}>
                                                    <b style={{ color: 'var(--db-heading)', fontSize: 13 }}>Langkah {index + 1}</b>
                                                    <button
                                                        className="db-btn danger sm"
                                                        type="button"
                                                        onClick={() => removeStep(index)}
                                                        disabled={form.data.steps.length <= 1}
                                                        title="Hapus langkah"
                                                    >
                                                        <Trash2 size={13} /> Hapus
                                                    </button>
                                                </div>
                                                <div className="crud-grid">
                                                    <TextField
                                                        label="Nama langkah"
                                                        required
                                                        placeholder="Contoh: Verifikasi Atasan Langsung"
                                                        value={step.name}
                                                        onChange={(event) => updateStep(index, { name: event.target.value })}
                                                        error={form.errors[`steps.${index}.name`]}
                                                    />
                                                    <SelectField
                                                        label="Tipe approver"
                                                        required
                                                        value={step.approver_type}
                                                        onChange={(event) => changeApproverType(index, event.target.value)}
                                                        error={form.errors[`steps.${index}.approver_type`]}
                                                    >
                                                        {options.approverTypes.map((option) => (
                                                            <option key={option.id} value={option.id}>{option.label}</option>
                                                        ))}
                                                    </SelectField>
                                                    {step.approver_type === 'role' && (
                                                        <SelectField
                                                            label="Role approver"
                                                            required
                                                            value={step.approver_role}
                                                            onChange={(event) => updateStep(index, { approver_role: event.target.value })}
                                                            error={form.errors[`steps.${index}.approver_role`]}
                                                        >
                                                            <option value="">-- Pilih role --</option>
                                                            {options.roles.map((option) => (
                                                                <option key={option.id} value={option.id}>{option.label}</option>
                                                            ))}
                                                        </SelectField>
                                                    )}
                                                    {step.approver_type === 'permission' && (
                                                        <SelectField
                                                            label="Permission approver"
                                                            required
                                                            value={step.approver_permission}
                                                            onChange={(event) => updateStep(index, { approver_permission: event.target.value })}
                                                            error={form.errors[`steps.${index}.approver_permission`]}
                                                        >
                                                            <option value="">-- Pilih permission --</option>
                                                            {options.permissions.map((option) => (
                                                                <option key={option.id} value={option.id}>{option.label}</option>
                                                            ))}
                                                        </SelectField>
                                                    )}
                                                    {step.approver_type === 'position' && (
                                                        <SelectField
                                                            label="Jabatan approver"
                                                            required
                                                            value={step.organizational_position_id}
                                                            onChange={(event) => updateStep(index, { organizational_position_id: event.target.value })}
                                                            error={form.errors[`steps.${index}.organizational_position_id`]}
                                                        >
                                                            <option value="">-- Pilih jabatan --</option>
                                                            {options.positions.map((option) => (
                                                                <option key={option.id} value={option.id}>{option.label}</option>
                                                            ))}
                                                        </SelectField>
                                                    )}
                                                    {step.approver_type === 'work_unit' && (
                                                        <SelectField
                                                            label="Unit kerja approver"
                                                            required
                                                            value={step.work_unit_id}
                                                            onChange={(event) => updateStep(index, { work_unit_id: event.target.value })}
                                                            error={form.errors[`steps.${index}.work_unit_id`]}
                                                        >
                                                            <option value="">-- Pilih unit kerja --</option>
                                                            {options.workUnits.map((option) => (
                                                                <option key={option.id} value={option.id}>{option.label}</option>
                                                            ))}
                                                        </SelectField>
                                                    )}
                                                    <TextField
                                                        label="SLA (jam)"
                                                        type="number"
                                                        min={1}
                                                        max={8760}
                                                        placeholder="Contoh: 24"
                                                        value={step.sla_hours}
                                                        onChange={(event) => updateStep(index, { sla_hours: event.target.value })}
                                                        error={form.errors[`steps.${index}.sla_hours`]}
                                                        hint="Batas waktu penyelesaian langkah dalam jam."
                                                    />
                                                    <SwitchField
                                                        label="Wajib dilalui"
                                                        checked={step.is_required}
                                                        onChange={(value) => updateStep(index, { is_required: value })}
                                                        error={form.errors[`steps.${index}.is_required`]}
                                                        onLabel="Wajib"
                                                        offLabel="Opsional"
                                                    />
                                                    <SwitchField
                                                        label="Dapat menolak"
                                                        checked={step.can_reject}
                                                        onChange={(value) => updateStep(index, { can_reject: value })}
                                                        error={form.errors[`steps.${index}.can_reject`]}
                                                        onLabel="Boleh menolak"
                                                        offLabel="Hanya menyetujui"
                                                    />
                                                    <div style={{ gridColumn: '1 / -1' }}>
                                                        <TextareaField
                                                            label="Deskripsi langkah"
                                                            rows={2}
                                                            placeholder="Instruksi atau catatan untuk approver pada langkah ini…"
                                                            value={step.description}
                                                            onChange={(event) => updateStep(index, { description: event.target.value })}
                                                            error={form.errors[`steps.${index}.description`]}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    {form.errors.steps && <div className="db-error">{form.errors.steps}</div>}
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel={isCreate ? 'Simpan Template' : 'Simpan Perubahan'}
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
