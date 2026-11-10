// Form pembuatan permohonan approval manual.
import { Head, useForm } from '@inertiajs/react';
import { FileSignature, Info } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type TemplateOption = { id: string; label: string; module: string };

type Props = {
    shell: ShellProps;
    templates: TemplateOption[];
    urls: { index: string; submit: string };
};

export default function ApprovalRequestForm({ shell, templates, urls }: Props) {
    const form = useForm({
        approval_template_id: '',
        subject: '',
        reference: '',
        notes: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Buat Permohonan Approval · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Kepegawaian"
                        title="Buat Permohonan Approval"
                        description="Ajukan permohonan baru berdasarkan template alur persetujuan yang telah dikonfigurasi. Sistem akan mengarahkan permohonan ke approver langkah pertama."
                        badges={['Permohonan manual']}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <form onSubmit={submit}>
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><FileSignature size={16} /> Formulir Permohonan</h2>
                                </div>
                                <div className="db-card-body">
                                    <div className="crud-grid">
                                        <SelectField
                                            label="Template approval"
                                            required
                                            value={form.data.approval_template_id}
                                            onChange={(event) => form.setData('approval_template_id', event.target.value)}
                                            error={form.errors.approval_template_id}
                                            hint="Pilih alur verifikasi yang sesuai dengan jenis pengajuan."
                                        >
                                            <option value="">-- Pilih template --</option>
                                            {templates.map((template) => (
                                                <option key={template.id} value={template.id}>{template.label}</option>
                                            ))}
                                        </SelectField>
                                        <TextField
                                            label="Referensi / nomor dokumen"
                                            placeholder="Contoh: SURAT/2026/001"
                                            value={form.data.reference}
                                            onChange={(event) => form.setData('reference', event.target.value)}
                                            error={form.errors.reference}
                                            hint="Opsional, memudahkan verifikator menelusuri dokumen terkait."
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextField
                                                label="Judul permohonan"
                                                required
                                                placeholder="Tuliskan subjek permohonan dengan singkat dan jelas"
                                                value={form.data.subject}
                                                onChange={(event) => form.setData('subject', event.target.value)}
                                                error={form.errors.subject}
                                            />
                                        </div>
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <TextareaField
                                                label="Catatan tambahan / alasan"
                                                rows={4}
                                                placeholder="Tuliskan keterangan detail pendukung pengajuan permohonan ini…"
                                                value={form.data.notes}
                                                onChange={(event) => form.setData('notes', event.target.value)}
                                                error={form.errors.notes}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-body">
                                    <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                        <Info size={16} style={{ color: 'var(--db-brand)', marginTop: 2, flexShrink: 0 }} />
                                        <div className="db-hint" style={{ marginTop: 0 }}>
                                            Setelah dikirim, sistem akan membuat langkah persetujuan sesuai template dan mengirim notifikasi ke approver langkah pertama. Pantau progres pada halaman detail permohonan.
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div style={{ marginTop: 16 }}>
                            <FormActions
                                cancelHref={urls.index}
                                submitLabel="Kirim Permohonan"
                                processing={form.processing}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
