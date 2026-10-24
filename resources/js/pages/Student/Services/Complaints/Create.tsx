import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Headset, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Cat = { id: number; name: string; code: string };

type Props = { shell: ShellProps; hasProfile: boolean; categories: Cat[]; urls: { index: string; store: string } };

export default function ComplaintsCreate({ shell, categories, urls }: Props) {
    const form = useForm<{
        form: { student_complaint_category_id: string; subject: string; description: string; priority: string };
        attachments: File[];
    }>({
        form: { student_complaint_category_id: '', subject: '', description: '', priority: 'normal' },
        attachments: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Buat Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Headset} eyebrow="Student Services" title="Buat Pengaduan" description="Jelaskan kendala dengan detail." />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="db-label">Kategori</label>
                                <select className="db-input" value={form.data.form.student_complaint_category_id}
                                    onChange={(e) => form.setData('form', { ...form.data.form, student_complaint_category_id: e.target.value })}>
                                    <option value="">Pilih kategori</option>
                                    {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                {form.errors['form.student_complaint_category_id'] && <div className="db-note danger">{form.errors['form.student_complaint_category_id']}</div>}

                                <label className="db-label">Judul</label>
                                <input className="db-input" value={form.data.form.subject}
                                    onChange={(e) => form.setData('form', { ...form.data.form, subject: e.target.value })} />
                                {form.errors['form.subject'] && <div className="db-note danger">{form.errors['form.subject']}</div>}

                                <label className="db-label">Prioritas</label>
                                <select className="db-input" value={form.data.form.priority}
                                    onChange={(e) => form.setData('form', { ...form.data.form, priority: e.target.value })}>
                                    <option value="low">Rendah</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Tinggi</option>
                                    <option value="urgent">Urgent</option>
                                </select>

                                <label className="db-label">Deskripsi</label>
                                <textarea className="db-input" rows={5} value={form.data.form.description}
                                    onChange={(e) => form.setData('form', { ...form.data.form, description: e.target.value })} />
                                {form.errors['form.description'] && <div className="db-note danger">{form.errors['form.description']}</div>}

                                <label className="db-label">Lampiran (maks 6)</label>
                                <input type="file" className="db-input" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                    onChange={(e) => form.setData('attachments', Array.from(e.target.files ?? []))} />

                                <button type="submit" className="db-btn primary" disabled={form.processing}><Send size={14} /> Kirim</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
