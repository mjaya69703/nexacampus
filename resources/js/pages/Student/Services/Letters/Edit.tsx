import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Info, Paperclip, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { humanizeField } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RequestData = {
    id: number;
    number: string;
    type: string;
    typeDescription: string | null;
    fulfillmentMode: string | null;
    purpose: string;
    status: string;
    requestData: Record<string, string> | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
};

type Props = {
    shell: ShellProps;
    request: RequestData;
    urls: { index: string; show: string; update: string };
};

export default function LettersEdit({ shell, request, urls }: Props) {
    const form = useForm<{
        form: { purpose: string; student_notes: string; request_data: Record<string, string> };
        attachment: File | null;
    }>({
        form: {
            purpose: request.purpose ?? '',
            student_notes: request.studentNotes ?? '',
            request_data: request.requestData ?? {},
        },
        attachment: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Student Services · Surat"
                        title="Perbaiki Pengajuan Surat"
                        description={`${request.number} · ${request.type}`}
                        actions={<Link href={urls.show} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {request.adminNotes && (
                        <div className="db-note warning">
                            <Info size={15} />
                            <span><b>Catatan admin:</b> {request.adminNotes}</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Formulir Perbaikan</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                {request.typeDescription && (
                                    <div className="db-note muted">
                                        <Info size={15} />
                                        <span>{request.typeDescription}</span>
                                    </div>
                                )}

                                <div>
                                    <label className="db-label">Tujuan / Keperluan <span>*</span></label>
                                    <textarea
                                        className="db-input"
                                        rows={3}
                                        value={form.data.form.purpose}
                                        onChange={(event) => form.setData('form', { ...form.data.form, purpose: event.target.value })}
                                    />
                                    {form.errors['form.purpose'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.purpose']}</div>}
                                </div>

                                {Object.keys(form.data.form.request_data).length > 0 && (
                                    <div className="db-form">
                                        <span className="db-label">Data Permohonan</span>
                                        {Object.keys(form.data.form.request_data).map((field) => (
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
                                    />
                                    {form.errors['form.student_notes'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.student_notes']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Lampiran Pengganti</label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        <Paperclip size={12} /> Kosongkan jika lampiran sebelumnya masih valid. Format PDF/JPG/PNG, maksimal 2 MB.
                                    </p>
                                    {request.attachmentUrl && (
                                        <a href={request.attachmentUrl} target="_blank" rel="noreferrer" className="db-btn ghost sm" style={{ marginTop: 8 }}>
                                            <Paperclip size={13} /> Lihat lampiran saat ini
                                        </a>
                                    )}
                                    {form.errors.attachment && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachment}</div>}
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing}>
                                        <Send size={14} /> {form.processing ? 'Mengirim…' : 'Kirim Ulang'}
                                    </button>
                                    <Link href={urls.show} className="db-btn ghost">Batal</Link>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
