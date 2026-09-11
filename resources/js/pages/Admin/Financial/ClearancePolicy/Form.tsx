// Form kebijakan bebas tanggungan — create + edit (paritas _form Blade).
import { Head, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Policy = {
    invoice_type: string; hold_type: string; mode: string;
    grace_days: number | string; is_active: boolean; description: string | null;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    policy: Policy;
    invoiceTypes: { id: string; name: string }[];
    holdTypes: { id: string; name: string }[];
    modes: string[];
    urls: { index: string; submit: string };
};

export default function ClearancePolicyForm({ shell, mode, policy, invoiceTypes, holdTypes, modes, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        invoice_type: policy.invoice_type ?? '',
        hold_type: policy.hold_type ?? '',
        mode: policy.mode ?? 'warning',
        grace_days: policy.grace_days === '' ? '' : String(policy.grace_days),
        is_active: policy.is_active,
        description: policy.description ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Ubah'} Kebijakan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ShieldCheck}
                        eyebrow="Keuangan"
                        title={isCreate ? 'Tambah Kebijakan' : 'Ubah Kebijakan'}
                        description="Perubahan diterapkan pada evaluasi clearance berikutnya."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ShieldCheck size={16} /> Data Kebijakan</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Jenis Tagihan Pemicu"
                                        required
                                        value={form.data.invoice_type}
                                        onChange={(e) => form.setData('invoice_type', e.target.value)}
                                        error={errors.invoice_type}
                                    >
                                        <option value="">Pilih jenis…</option>
                                        {invoiceTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Target Layanan Akademik"
                                        required
                                        value={form.data.hold_type}
                                        onChange={(e) => form.setData('hold_type', e.target.value)}
                                        error={errors.hold_type}
                                    >
                                        <option value="">Pilih target…</option>
                                        {holdTypes.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Mode Penegakan"
                                        required
                                        value={form.data.mode}
                                        onChange={(e) => form.setData('mode', e.target.value)}
                                        error={errors.mode}
                                    >
                                        {modes.map((m) => (
                                            <option key={m} value={m}>
                                                {m === 'blocking' ? 'Pemblokiran Layanan (Blocking)' : 'Hanya Peringatan (Warning Only)'}
                                            </option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Masa Tenggang (Hari)"
                                        required
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={form.data.grace_days}
                                        onChange={(e) => form.setData('grace_days', e.target.value)}
                                        error={errors.grace_days}
                                    />
                                    <SwitchField
                                        label="Status Kebijakan"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi & Instruksi"
                                            value={form.data.description}
                                            placeholder="Contoh: Pembatasan pengisian KRS bagi mahasiswa yang belum melunasi SPP setelah 7 hari jatuh tempo…"
                                            onChange={(e) => form.setData('description', e.target.value)}
                                            error={errors.description}
                                        />
                                    </div>
                                </div>
                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel="Simpan Kebijakan"
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
