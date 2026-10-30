import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Info, Paperclip, Send } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { formatFileSize, humanizeField } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LetterType = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    fulfillmentMode: string | null;
    requiresAttachment: boolean;
    allowedExtensions: string | null;
    maxFileSizeKb: number | null;
    requiredFields: string[];
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    letterTypes: LetterType[];
    urls: { index: string; store: string };
};

const fulfillmentLabel = (mode: string | null): string => {
    if (mode === 'digital') return 'Surat digital (unduh mandiri)';
    if (mode === 'physical') return 'Surat fisik (ambil di layanan akademik)';
    if (mode === 'both') return 'Digital & fisik';
    return '-';
};

export default function LettersCreate({ shell, hasProfile, letterTypes, urls }: Props) {
    const [selectedTypeId, setSelectedTypeId] = useState<string>('');
    const selectedType = letterTypes.find((type) => String(type.id) === selectedTypeId) ?? null;

    const requiredFields = useMemo(
        () => (selectedType?.requiredFields ?? []).filter((field) => field !== 'purpose'),
        [selectedType],
    );

    const form = useForm<{
        form: {
            service_letter_type_id: string;
            purpose: string;
            student_notes: string;
            request_data: Record<string, string>;
        };
        attachment: File | null;
    }>({
        form: {
            service_letter_type_id: '',
            purpose: '',
            student_notes: '',
            request_data: {},
        },
        attachment: null,
    });

    const selectType = (id: string) => {
        setSelectedTypeId(id);
        const type = letterTypes.find((item) => String(item.id) === id);
        const requestData: Record<string, string> = {};
        (type?.requiredFields ?? []).forEach((field) => {
            if (field !== 'purpose') requestData[field] = '';
        });
        form.setData('form', {
            ...form.data.form,
            service_letter_type_id: id,
            request_data: requestData,
        });
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    const acceptExtensions = (selectedType?.allowedExtensions ?? 'pdf,jpg,jpeg,png')
        .split(',')
        .map((extension) => `.${extension.trim()}`)
        .join(',');

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Student Services · Surat"
                        title="Ajukan Surat"
                        description="Pilih jenis surat, isi data yang diminta, lalu lampirkan berkas pendukung bila diperlukan."
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <Info size={15} />
                            <span>Profil mahasiswa belum ditemukan. Hubungi admin akademik sebelum mengajukan surat.</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><FileText size={16} /> Formulir Pengajuan Surat</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                <div>
                                    <label className="db-label">Jenis Surat <span>*</span></label>
                                    <select className="db-input" value={selectedTypeId} onChange={(event) => selectType(event.target.value)}>
                                        <option value="">Pilih jenis surat</option>
                                        {letterTypes.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                                    </select>
                                    {form.errors['form.service_letter_type_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.service_letter_type_id']}</div>}
                                </div>

                                {selectedType && (
                                    <div className="db-note info">
                                        <Info size={15} />
                                        <span>
                                            <b>{selectedType.name}</b>
                                            {selectedType.description ? ` — ${selectedType.description}` : ''}
                                            {selectedType.fulfillmentMode ? ` Penyelesaian: ${fulfillmentLabel(selectedType.fulfillmentMode)}.` : ''}
                                        </span>
                                    </div>
                                )}

                                <div>
                                    <label className="db-label">Tujuan / Keperluan <span>*</span></label>
                                    <textarea
                                        className="db-input"
                                        rows={3}
                                        value={form.data.form.purpose}
                                        onChange={(event) => form.setData('form', { ...form.data.form, purpose: event.target.value })}
                                        placeholder="Contoh: keperluan pengajuan beasiswa, instansi tujuan, atau keterangan lain…"
                                    />
                                    {form.errors['form.purpose'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.purpose']}</div>}
                                </div>

                                {requiredFields.length > 0 && (
                                    <div className="db-form">
                                        <span className="db-label">Data Tambahan</span>
                                        {requiredFields.map((field) => (
                                            <div key={field}>
                                                <label className="db-label">{humanizeField(field)} <span>*</span></label>
                                                <input
                                                    className="db-input"
                                                    value={form.data.form.request_data[field] ?? ''}
                                                    onChange={(event) => form.setData('form', {
                                                        ...form.data.form,
                                                        request_data: { ...form.data.form.request_data, [field]: event.target.value },
                                                    })}
                                                />
                                                {form.errors[`form.request_data.${field}`] && (
                                                    <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors[`form.request_data.${field}`]}</div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div>
                                    <label className="db-label">Catatan Mahasiswa</label>
                                    <textarea
                                        className="db-input"
                                        rows={3}
                                        value={form.data.form.student_notes}
                                        onChange={(event) => form.setData('form', { ...form.data.form, student_notes: event.target.value })}
                                        placeholder="Informasi tambahan yang perlu diketahui petugas (opsional)…"
                                    />
                                    {form.errors['form.student_notes'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.student_notes']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">
                                        Lampiran {selectedType?.requiresAttachment ? <span>*</span> : <small>(opsional)</small>}
                                    </label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept={acceptExtensions}
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        <Paperclip size={12} /> Format: {selectedType?.allowedExtensions ?? 'PDF, JPG, PNG'} · Maksimal {formatFileSize(selectedType?.maxFileSizeKb ?? 2048)}.
                                        {selectedType?.requiresAttachment ? ' Lampiran wajib untuk jenis surat ini.' : ''}
                                    </p>
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing || !hasProfile}>
                                        <Send size={14} /> {form.processing ? 'Mengirim…' : 'Kirim Pengajuan'}
                                    </button>
                                    <Link href={urls.index} className="db-btn ghost">Batal</Link>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
