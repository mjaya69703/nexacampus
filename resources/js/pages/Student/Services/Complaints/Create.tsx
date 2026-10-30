import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Headset, Info, Paperclip, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { priorityLabel } from '../labels';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Cat = { id: number; name: string; code: string };

type Props = { shell: ShellProps; hasProfile: boolean; categories: Cat[]; urls: { index: string; store: string } };

const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

export default function ComplaintsCreate({ shell, hasProfile, categories, urls }: Props) {
    const form = useForm<{
        form: { student_complaint_category_id: string; subject: string; description: string; priority: string };
        attachments: File[];
    }>({
        form: { student_complaint_category_id: '', subject: '', description: '', priority: 'normal' },
        attachments: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, { forceFormData: true, preserveScroll: true });
    };

    const attachments = form.data.attachments;

    return (
        <AdminShell shell={shell}>
            <Head title={`Buat Pengaduan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Headset}
                        eyebrow="Student Services · Pengaduan"
                        title="Buat Pengaduan"
                        description="Jelaskan kendala layanan kampus secara detail agar petugas bisa menindaklanjuti dengan tepat."
                        actions={<Link href={urls.index} className="db-btn light"><ArrowLeft size={15} /> Kembali</Link>}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <Info size={15} />
                            <span>Profil mahasiswa belum ditemukan. Hubungi admin akademik sebelum membuat pengaduan.</span>
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Headset size={16} /> Formulir Pengaduan</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={submit} className="db-form">
                                <div>
                                    <label className="db-label">Kategori <span>*</span></label>
                                    <select
                                        className="db-input"
                                        value={form.data.form.student_complaint_category_id}
                                        onChange={(event) => form.setData('form', { ...form.data.form, student_complaint_category_id: event.target.value })}
                                    >
                                        <option value="">Pilih kategori pengaduan</option>
                                        {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                                    </select>
                                    {form.errors['form.student_complaint_category_id'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.student_complaint_category_id']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Judul Pengaduan <span>*</span></label>
                                    <input
                                        className="db-input"
                                        value={form.data.form.subject}
                                        onChange={(event) => form.setData('form', { ...form.data.form, subject: event.target.value })}
                                        placeholder="Ringkasan singkat kendala yang kamu alami…"
                                    />
                                    {form.errors['form.subject'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.subject']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Prioritas <span>*</span></label>
                                    <select
                                        className="db-input"
                                        value={form.data.form.priority}
                                        onChange={(event) => form.setData('form', { ...form.data.form, priority: event.target.value })}
                                    >
                                        {PRIORITIES.map((priority) => <option key={priority} value={priority}>{priorityLabel(priority)}</option>)}
                                    </select>
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        Pilih <b>Mendesak</b> hanya untuk kendala yang berdampak langsung pada studi atau keselamatan.
                                    </p>
                                    {form.errors['form.priority'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.priority']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Deskripsi <span>*</span></label>
                                    <textarea
                                        className="db-input"
                                        rows={6}
                                        value={form.data.form.description}
                                        onChange={(event) => form.setData('form', { ...form.data.form, description: event.target.value })}
                                        placeholder="Jelaskan kronologi, lokasi, waktu, dan pihak terkait agar pengaduan mudah ditindaklanjuti…"
                                    />
                                    {form.errors['form.description'] && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors['form.description']}</div>}
                                </div>

                                <div>
                                    <label className="db-label">Lampiran <small>(opsional, maks. 6 berkas)</small></label>
                                    <input
                                        type="file"
                                        className="db-input"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                        onChange={(event) => form.setData('attachments', Array.from(event.target.files ?? []))}
                                    />
                                    <p className="db-hint" style={{ marginTop: 6 }}>
                                        <Paperclip size={12} /> Format: JPG, PNG, WEBP, PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT.
                                    </p>
                                    {attachments.length > 0 && (
                                        <div className="db-file-list" style={{ marginTop: 8 }}>
                                            {attachments.map((file, index) => (
                                                <div className="db-file" key={`${file.name}-${index}`}>
                                                    <div>
                                                        <b>{file.name}</b>
                                                        <small>{Math.max(1, Math.round(file.size / 1024))} KB</small>
                                                    </div>
                                                    <span className="db-badge gray">{index + 1}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                    {form.errors.attachments && <div className="db-note danger" style={{ marginTop: 8 }}>{form.errors.attachments}</div>}
                                    {Object.entries(form.errors).map(([key, message]) => (
                                        key.startsWith('attachments.') && message
                                            ? <div key={key} className="db-note danger" style={{ marginTop: 8 }}>{message}</div>
                                            : null
                                    ))}
                                </div>

                                <div className="db-note info">
                                    <Info size={15} />
                                    <span>Pengaduan akan diteruskan ke unit terkait. Kamu bisa memantau balasan dan menutup tiket setelah kendala selesai.</span>
                                </div>

                                <div className="db-form-actions">
                                    <button type="submit" className="db-btn primary" disabled={form.processing || !hasProfile}>
                                        <Send size={14} /> {form.processing ? 'Mengirim…' : 'Kirim Pengaduan'}
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
