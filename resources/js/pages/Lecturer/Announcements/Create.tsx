import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Megaphone, Send } from 'lucide-react';
import { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type CO = { id: number; label: string };
type Pri = { value: string; label: string };

type Props = { shell: ShellProps; courseOfferings: CO[]; priorities: Pri[]; urls: { index: string; store: string } };

export default function AnnouncementsCreate({ shell, courseOfferings, priorities, urls }: Props) {
    const form = useForm<{
        title: string; content: string; targetId: string; priority: string; isPinned: boolean; isPublished: boolean;
        publishedAt: string; scheduledAt: string; attachment: File | null;
    }>({
        title: '', content: '', targetId: '', priority: 'normal', isPinned: false, isPublished: true,
        publishedAt: new Date().toISOString().slice(0, 16), scheduledAt: '', attachment: null,
    });

    const submit = (e: FormEvent) => { e.preventDefault(); form.post(urls.store, { forceFormData: true, preserveScroll: true }); };

    return (
        <AdminShell shell={shell}>
            <Head title={`Buat Pengumuman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Megaphone} eyebrow="Publikasi" title="Buat Pengumuman" description="Buat pengumuman untuk kelas." />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                <label className="crud-label">Judul</label>
                                <input className="db-input" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                                {form.errors.title && <div className="db-note danger">{form.errors.title}</div>}

                                <label className="crud-label">Konten</label>
                                <textarea className="db-input" rows={6} value={form.data.content} onChange={(e) => form.setData('content', e.target.value)} />
                                {form.errors.content && <div className="db-note danger">{form.errors.content}</div>}

                                <label className="crud-label">Kelas Tujuan</label>
                                <select className="db-input" value={form.data.targetId} onChange={(e) => form.setData('targetId', e.target.value)}>
                                    <option value="">Pilih kelas</option>
                                    {courseOfferings.map((c) => <option key={c.id} value={c.id}>{c.label}</option>)}
                                </select>
                                {form.errors.targetId && <div className="db-note danger">{form.errors.targetId}</div>}

                                <label className="crud-label">Prioritas</label>
                                <select className="db-input" value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                                    {priorities.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                                </select>

                                <label style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                    <input type="checkbox" checked={form.data.isPinned} onChange={(e) => form.setData('isPinned', e.target.checked)} /> Sematkan
                                </label>
                                <label style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                                    <input type="checkbox" checked={form.data.isPublished} onChange={(e) => form.setData('isPublished', e.target.checked)} /> Publikasikan
                                </label>

                                <label className="crud-label">Waktu Publikasi</label>
                                <input type="datetime-local" className="db-input" value={form.data.publishedAt} onChange={(e) => form.setData('publishedAt', e.target.value)} />

                                <label className="crud-label">Jadwal (opsional)</label>
                                <input type="datetime-local" className="db-input" value={form.data.scheduledAt} onChange={(e) => form.setData('scheduledAt', e.target.value)} />

                                <label className="crud-label">Lampiran (opsional)</label>
                                <input type="file" className="db-input" onChange={(e) => form.setData('attachment', e.target.files?.[0] ?? null)} />

                                <button type="submit" className="db-btn primary" disabled={form.processing}><Send size={14} /> Kirim</button>
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}