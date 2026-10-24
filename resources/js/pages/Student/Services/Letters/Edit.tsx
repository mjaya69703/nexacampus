import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileText, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RequestData = {
    id: number;
    number: string;
    type: string;
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

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(urls.update, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Perbaiki Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Student Services" title="Perbaiki Pengajuan Surat" description={request.number} />
                    <Link href={urls.show} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    {request.adminNotes && <div className="db-note warning">{request.adminNotes}</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Tujuan / Keperluan</label>
                                <textarea className="db-input" rows={3} value={form.data.form.purpose}
                                    onChange={(e) => form.setData('form', { ...form.data.form, purpose: e.target.value })} />
                                {form.errors['form.purpose'] && <div className="db-note danger">{form.errors['form.purpose']}</div>}

                                {Object.keys(form.data.form.request_data).map((field) => (
                                    <div key={field}>
                                        <label className="db-label">{field}</label>
                                        <input className="db-input" value={form.data.form.request_data[field] ?? ''}
                                            onChange={(e) => form.setData('form', {
                                                ...form.data.form,
                                                request_data: { ...form.data.form.request_data, [field]: e.target.value },
                                            })} />
                                    </div>
                                ))}

                                <label className="db-label">Catatan Mahasiswa</label>
                                <textarea className="db-input" rows={2} value={form.data.form.student_notes}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_notes: e.target.value })} />

                                <label className="db-label">Lampiran (opsional)</label>
                                <input type="file" className="db-input" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />

                                <button type="submit" className="db-btn primary" disabled={form.processing}>
                                    <Send size={14} /> Kirim Ulang
                                </button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
