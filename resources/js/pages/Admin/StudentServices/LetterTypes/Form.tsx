// Form tambah/ubah jenis surat layanan mahasiswa.
import { Head, useForm } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LetterTypePayload = {
    id?: number;
    name: string;
    code: string;
    description: string;
    fulfillment_mode: string;
    template_key: string;
    requires_financial_clearance: boolean;
    clearance_hold_type: string;
    required_fields: string;
    requires_attachment: boolean;
    allowed_extensions: string;
    max_file_size_kb: number;
    signer_name: string;
    signer_position: string;
    is_active: boolean;
};

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    letterType: LetterTypePayload;
    fulfillmentModes: { id: string; name: string }[];
    clearanceTypes: { id: string; name: string }[];
    urls: { index: string; submit: string };
};

export default function LetterTypeForm({ shell, mode, letterType, fulfillmentModes, clearanceTypes, urls }: Props) {
    const isCreate = mode === 'create';

    const form = useForm({
        name: letterType.name,
        code: letterType.code,
        description: letterType.description,
        fulfillment_mode: letterType.fulfillment_mode,
        template_key: letterType.template_key,
        requires_financial_clearance: letterType.requires_financial_clearance,
        clearance_hold_type: letterType.clearance_hold_type,
        required_fields: letterType.required_fields,
        requires_attachment: letterType.requires_attachment,
        allowed_extensions: letterType.allowed_extensions,
        max_file_size_kb: letterType.max_file_size_kb,
        signer_name: letterType.signer_name,
        signer_position: letterType.signer_position,
        is_active: letterType.is_active,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (isCreate) {
            form.post(urls.submit);
            return;
        }

        form.put(urls.submit);
    };

    const toggleClearance = (value: boolean) => {
        form.setData((data) => ({
            ...data,
            requires_financial_clearance: value,
            clearance_hold_type: value ? data.clearance_hold_type : '',
        }));
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Jenis Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ScrollText}
                        eyebrow="Layanan Mahasiswa"
                        title={isCreate ? 'Tambah Jenis Surat' : `Edit ${letterType.name}`}
                        description="Tentukan bagaimana surat dipenuhi, syarat keuangan, lampiran wajib, dan penandatangan."
                        badges={isCreate ? ['Jenis surat baru'] : [letterType.code]}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><ScrollText size={16} /> Identitas Surat</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <TextField
                                        label="Nama jenis surat"
                                        required
                                        placeholder="Contoh: Surat Keterangan Aktif Kuliah"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        error={form.errors.name}
                                    />
                                    <TextField
                                        label="Kode"
                                        required
                                        placeholder="Contoh: AKTIF_KULIAH"
                                        value={form.data.code}
                                        onChange={(event) => form.setData('code', event.target.value)}
                                        error={form.errors.code}
                                        hint="Huruf kapital; spasi otomatis diubah menjadi garis bawah."
                                    />
                                    <SelectField
                                        label="Metode pemenuhan"
                                        required
                                        value={form.data.fulfillment_mode}
                                        onChange={(event) => form.setData('fulfillment_mode', event.target.value)}
                                        error={form.errors.fulfillment_mode}
                                    >
                                        {fulfillmentModes.map((option) => (
                                            <option key={option.id} value={option.id}>{option.name}</option>
                                        ))}
                                    </SelectField>
                                    <TextField
                                        label="Kunci template"
                                        placeholder="template_surat_aktif"
                                        value={form.data.template_key}
                                        onChange={(event) => form.setData('template_key', event.target.value)}
                                        error={form.errors.template_key}
                                        hint="Opsional. Dipakai sistem saat mencetak PDF otomatis."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Deskripsi"
                                            rows={3}
                                            placeholder="Jelaskan kegunaan surat ini untuk mahasiswa…"
                                            value={form.data.description}
                                            onChange={(event) => form.setData('description', event.target.value)}
                                            error={form.errors.description}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Syarat & Berkas</h3>
                                    <div className="crud-grid">
                                        <SwitchField
                                            label="Wajib bebas keuangan"
                                            checked={form.data.requires_financial_clearance}
                                            onChange={toggleClearance}
                                            error={form.errors.requires_financial_clearance}
                                            hint="Mahasiswa dengan hold keuangan tidak bisa mengajukan surat ini."
                                        />
                                        <SelectField
                                            label="Jenis clearance"
                                            value={form.data.clearance_hold_type}
                                            onChange={(event) => form.setData('clearance_hold_type', event.target.value)}
                                            error={form.errors.clearance_hold_type}
                                            disabled={!form.data.requires_financial_clearance}
                                            hint="Kosongkan untuk memakai pemeriksaan hold umum."
                                        >
                                            <option value="">Hold umum</option>
                                            {clearanceTypes.map((option) => (
                                                <option key={option.id} value={option.id}>{option.name}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Field wajib"
                                            placeholder="purpose, destination"
                                            value={form.data.required_fields}
                                            onChange={(event) => form.setData('required_fields', event.target.value)}
                                            error={form.errors.required_fields}
                                            hint="Pisahkan dengan koma. Contoh: purpose, destination."
                                        />
                                        <TextField
                                            label="Ukuran maksimal berkas (KB)"
                                            type="number"
                                            min={1}
                                            max={20480}
                                            value={form.data.max_file_size_kb}
                                            onChange={(event) => form.setData('max_file_size_kb', Number(event.target.value))}
                                            error={form.errors.max_file_size_kb}
                                        />
                                        <SwitchField
                                            label="Wajib unggah lampiran"
                                            checked={form.data.requires_attachment}
                                            onChange={(value) => form.setData('requires_attachment', value)}
                                            error={form.errors.requires_attachment}
                                        />
                                        <TextField
                                            label="Ekstensi diizinkan"
                                            placeholder="pdf,jpg,jpeg,png"
                                            value={form.data.allowed_extensions}
                                            onChange={(event) => form.setData('allowed_extensions', event.target.value)}
                                            error={form.errors.allowed_extensions}
                                        />
                                    </div>
                                </div>

                                <div style={{ marginTop: 24, paddingTop: 20, borderTop: '1px solid var(--db-line)' }}>
                                    <h3 className="db-card-title" style={{ marginBottom: 12 }}>Penandatangan & Status</h3>
                                    <div className="crud-grid">
                                        <TextField
                                            label="Nama penandatangan"
                                            placeholder="Dr. Nama Pejabat, M.Si."
                                            value={form.data.signer_name}
                                            onChange={(event) => form.setData('signer_name', event.target.value)}
                                            error={form.errors.signer_name}
                                        />
                                        <TextField
                                            label="Jabatan penandatangan"
                                            placeholder="Wakil Dekan Bidang Akademik"
                                            value={form.data.signer_position}
                                            onChange={(event) => form.setData('signer_position', event.target.value)}
                                            error={form.errors.signer_position}
                                        />
                                        <SwitchField
                                            label="Aktifkan jenis surat"
                                            checked={form.data.is_active}
                                            onChange={(value) => form.setData('is_active', value)}
                                            error={form.errors.is_active}
                                            hint="Jenis surat nonaktif tidak muncul di portal mahasiswa."
                                        />
                                    </div>
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Jenis Surat' : 'Simpan Perubahan'}
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
