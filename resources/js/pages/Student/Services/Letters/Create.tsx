import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Plus, Send } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LetterType = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    fulfillmentMode: string;
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

export default function LettersCreate({ shell, hasProfile, letterTypes, urls }: Props) {
    const [selectedTypeId, setSelectedTypeId] = useState<string>('');
    const selectedType = letterTypes.find((t) => String(t.id) === selectedTypeId) ?? null;

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
        const t = letterTypes.find((x) => String(x.id) === id);
        const rd: Record<string, string> = {};
        (t?.requiredFields ?? []).forEach((f) => {
            if (f !== 'purpose') rd[f] = '';
        });
        form.setData('form', {
            service_letter_type_id: id,
            purpose: form.data.form.purpose,
            student_notes: form.data.form.student_notes,
            request_data: rd,
        });
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Ajukan Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Student Services" title="Ajukan Surat" description="Isi jenis surat, tujuan, dan lampiran pendukung." />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Jenis Surat</label>
                                <select className="db-input" value={selectedTypeId} onChange={(e) => selectType(e.target.value)}>
                                    <option value="">Pilih jenis surat</option>
                                    {letterTypes.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                                {form.errors['form.service_letter_type_id'] && <div className="db-note danger">{form.errors['form.service_letter_type_id']}</div>}

                                <label className="db-label">Tujuan / Keperluan</label>
                                <textarea className="db-input" rows={3} value={form.data.form.purpose}
                                    onChange={(e) => form.setData('form', { ...form.data.form, purpose: e.target.value })} />
                                {form.errors['form.purpose'] && <div className="db-note danger">{form.errors['form.purpose']}</div>}

                                {selectedType?.requiredFields?.filter((f) => f !== 'purpose').map((field) => (
                                    <div key={field}>
                                        <label className="db-label">{field}</label>
                                        <input className="db-input" value={form.data.form.request_data[field] ?? ''}
                                            onChange={(e) => form.setData('form', {
                                                ...form.data.form,
                                                request_data: { ...form.data.form.request_data, [field]: e.target.value },
                                            })} />
                                        {form.errors[`form.request_data.${field}`] && <div className="db-note danger">{form.errors[`form.request_data.${field}`]}</div>}
                                    </div>
                                ))}

                                <label className="db-label">Catatan Mahasiswa</label>
                                <textarea className="db-input" rows={2} value={form.data.form.student_notes}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_notes: e.target.value })} />

                                <label className="db-label">Lampiran {selectedType?.requiresAttachment ? '(wajib)' : '(opsional)'}</label>
                                <input type="file" className="db-input" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />

                                <button type="submit" className="db-btn primary" disabled={form.processing}>
                                    <Send size={14} /> Kirim
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
