// Materi kuliah — unggah, ubah, publikasi, dan hapus materi kelas.
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft, CheckCircle2, Download, Eye, FileText, Link2, Pencil, Plus, Send, Trash2, Upload, X,
} from 'lucide-react';
import { ChangeEvent, FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type MaterialFile = { id: number; name: string; type: string; size: string; isLink: boolean; url: string };
type Material = {
    id: number; title: string; description: string | null; category: string; meetingNumber: number | null;
    isPublished: boolean; downloadCount: number; uploadedAt: string; uploadedBy: string;
    files: MaterialFile[]; showUrl: string; updateUrl: string; deleteUrl: string; toggleUrl: string;
};

type OfferingInfo = {
    id: number; courseCode: string; courseName: string; label: string; academicYear: string;
    studyProgram: string; semesterNo: number | null; room: string; building: string; deliveryMode: string;
};

type Props = {
    shell: ShellProps;
    offeringId: number;
    offeringInfo: OfferingInfo;
    materials: Material[];
    categories: Record<string, string>;
    stats: { total: number; published: number; draft: number; files: number };
    urls: { list: string; show: string; store: string };
};

const tone = (published: boolean) => (published ? 'green' : 'gray');

function MaterialRow({ material, categories }: { material: Material; categories: Record<string, string> }) {
    const [editing, setEditing] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const form = useForm<{
        title: string; description: string; category: string; meetingNumber: string; isPublished: boolean; files: File[];
    }>({
        title: material.title,
        description: material.description ?? '',
        category: material.category,
        meetingNumber: material.meetingNumber === null ? '' : String(material.meetingNumber),
        isPublished: material.isPublished,
        files: [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(material.updateUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    const toggle = () => router.post(material.toggleUrl, {}, { preserveScroll: true });
    const remove = () => {
        router.delete(material.deleteUrl, { preserveScroll: true, onFinish: () => setConfirming(false) });
    };

    return (
        <article className="db-class">
            <div className="db-class-top">
                <div style={{ minWidth: 0 }}>
                    <b>{material.title}</b>
                    <small>
                        Pertemuan {material.meetingNumber ?? '-'} · {categories[material.category] ?? material.category} ·{' '}
                        {material.files.length} file · {material.downloadCount} unduhan
                    </small>
                </div>
                <span className={`db-badge ${tone(material.isPublished)}`}>{material.isPublished ? 'Published' : 'Draft'}</span>
            </div>

            {material.description && <div style={{ fontSize: 13, color: 'var(--db-muted)', marginTop: 4 }}>{material.description}</div>}

            {material.files.length > 0 && (
                <div style={{ display: 'grid', gap: 6, marginTop: 8 }}>
                    {material.files.map((file) => (
                        <a key={file.id} href={file.url} target="_blank" rel="noreferrer" className="db-hint" style={{ display: 'flex', gap: 8, alignItems: 'center', textDecoration: 'none' }}>
                            {file.isLink ? <Link2 size={13} /> : <Download size={13} />}
                            <span>{file.name}</span>
                            <span>· {file.type} · {file.size}</span>
                        </a>
                    ))}
                </div>
            )}

            <div className="db-class-actions">
                <a className="db-btn ghost sm" href={material.showUrl}><Eye size={12} /> Detail</a>
                <button className="db-btn ghost sm" type="button" onClick={() => setEditing((value) => !value)}>
                    <Pencil size={12} /> {editing ? 'Tutup' : 'Edit'}
                </button>
                <button className="db-btn ghost sm" type="button" onClick={toggle}>
                    <CheckCircle2 size={12} /> {material.isPublished ? 'Jadikan draft' : 'Publikasikan'}
                </button>
                {!confirming ? (
                    <button className="db-btn danger sm" type="button" onClick={() => setConfirming(true)}><Trash2 size={12} /> Hapus</button>
                ) : (
                    <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
                        <span className="db-hint">Yakin?</span>
                        <button className="db-btn danger sm" type="button" onClick={remove}>Ya, hapus</button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setConfirming(false)}>Batal</button>
                    </span>
                )}
            </div>

            {editing && (
                <form onSubmit={submit} style={{ display: 'grid', gap: 12, marginTop: 12, borderTop: '1px solid var(--db-line)', paddingTop: 12 }}>
                    <div className="crud-grid">
                        <div>
                            <label className="crud-label">Judul <span>*</span></label>
                            <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                            {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Kategori <span>*</span></label>
                            <select className="db-input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)}>
                                {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                            {form.errors.category && <div className="db-error">{form.errors.category}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Pertemuan</label>
                            <input className="db-input" type="number" min={1} max={20} value={form.data.meetingNumber} onChange={(event) => form.setData('meetingNumber', event.target.value)} />
                            {form.errors.meetingNumber && <div className="db-error">{form.errors.meetingNumber}</div>}
                        </div>
                        <div>
                            <label className="crud-label">Status</label>
                            <select className="db-input" value={form.data.isPublished ? '1' : '0'} onChange={(event) => form.setData('isPublished', event.target.value === '1')}>
                                <option value="1">Published</option>
                                <option value="0">Draft</option>
                            </select>
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Deskripsi</label>
                            <textarea className="db-input" rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                            {form.errors.description && <div className="db-error">{form.errors.description}</div>}
                        </div>
                        <div style={{ gridColumn: '1 / -1' }}>
                            <label className="crud-label">Tambah file baru</label>
                            <input className="db-input" type="file" multiple onChange={(event: ChangeEvent<HTMLInputElement>) => form.setData('files', Array.from(event.target.files ?? []))} />
                            {form.errors.files && <div className="db-error">{form.errors.files}</div>}
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: 8 }}>
                        <button className="db-btn primary sm" type="submit" disabled={form.processing}><Send size={13} /> {form.processing ? 'Menyimpan…' : 'Simpan perubahan'}</button>
                        <button className="db-btn ghost sm" type="button" onClick={() => setEditing(false)}><X size={13} /> Batal</button>
                    </div>
                </form>
            )}
        </article>
    );
}

export default function CourseMaterialsIndex({ shell, offeringInfo, materials, categories, stats, urls }: Props) {
    const [uploading, setUploading] = useState(false);
    const form = useForm<{
        title: string; description: string; category: string; meetingNumber: string;
        isPublished: boolean; files: File[]; videoUrl: string; videoTitle: string;
    }>({
        title: '', description: '', category: Object.keys(categories)[0] ?? 'lecture_notes',
        meetingNumber: '', isPublished: false, files: [], videoUrl: '', videoTitle: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setUploading(false);
            },
        });
    };

    const cards = [
        { label: 'Materi', value: stats.total, icon: <FileText size={20} />, tone: 'db-stat-icon' },
        { label: 'Published', value: stats.published, icon: <CheckCircle2 size={20} />, tone: 'db-stat-icon green' },
        { label: 'Draft', value: stats.draft, icon: <FileText size={20} />, tone: 'db-stat-icon gold' },
        { label: 'File', value: stats.files, icon: <Download size={20} />, tone: 'db-stat-icon' },
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Materi ${offeringInfo.courseCode} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Akademik · Kelas"
                        title="Materi Kuliah"
                        description={`${offeringInfo.courseCode} — ${offeringInfo.courseName} · ${offeringInfo.label} · ${offeringInfo.academicYear}`}
                        badges={[`${stats.total} materi`, `${stats.files} file`]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.list}><ArrowLeft size={15} /> Daftar kelas</Link>
                                <Link className="db-btn light" href={urls.show}><Eye size={15} /> Detail kelas</Link>
                            </div>
                        }
                    />

                    <section className="db-stats" aria-label="Ringkasan materi">
                        {cards.map((card) => (
                            <div className="db-stat" key={card.label}>
                                <span className={card.tone}>{card.icon}</span>
                                <div><span className="db-stat-num">{card.value}</span><span className="db-stat-label">{card.label}</span></div>
                            </div>
                        ))}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <div>
                                <h2 className="db-card-title">Unggah materi</h2>
                                <p className="db-hint" style={{ margin: '4px 0 0' }}>
                                    {offeringInfo.room} · {offeringInfo.building} · {offeringInfo.deliveryMode}
                                </p>
                            </div>
                            <button className="db-btn primary sm" type="button" onClick={() => setUploading((value) => !value)}>
                                {uploading ? <X size={13} /> : <Plus size={13} />} {uploading ? 'Tutup' : 'Tambah materi'}
                            </button>
                        </div>
                        {uploading && (
                            <div className="db-card-body">
                                <form onSubmit={submit} style={{ display: 'grid', gap: 12 }}>
                                    <div className="crud-grid">
                                        <div>
                                            <label className="crud-label">Judul <span>*</span></label>
                                            <input className="db-input" value={form.data.title} onChange={(event) => form.setData('title', event.target.value)} />
                                            {form.errors.title && <div className="db-error">{form.errors.title}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">Kategori <span>*</span></label>
                                            <select className="db-input" value={form.data.category} onChange={(event) => form.setData('category', event.target.value)}>
                                                {Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                            </select>
                                            {form.errors.category && <div className="db-error">{form.errors.category}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">Pertemuan</label>
                                            <input className="db-input" type="number" min={1} max={20} value={form.data.meetingNumber} onChange={(event) => form.setData('meetingNumber', event.target.value)} />
                                            {form.errors.meetingNumber && <div className="db-error">{form.errors.meetingNumber}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">Status</label>
                                            <select className="db-input" value={form.data.isPublished ? '1' : '0'} onChange={(event) => form.setData('isPublished', event.target.value === '1')}>
                                                <option value="0">Draft</option>
                                                <option value="1">Published</option>
                                            </select>
                                        </div>
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <label className="crud-label">Deskripsi</label>
                                            <textarea className="db-input" rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} />
                                            {form.errors.description && <div className="db-error">{form.errors.description}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">File materi</label>
                                            <input className="db-input" type="file" multiple onChange={(event: ChangeEvent<HTMLInputElement>) => form.setData('files', Array.from(event.target.files ?? []))} />
                                            {form.errors.files && <div className="db-error">{form.errors.files}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">Tautan video (opsional)</label>
                                            <input className="db-input" placeholder="https://…" value={form.data.videoUrl} onChange={(event) => form.setData('videoUrl', event.target.value)} />
                                            {form.errors.videoUrl && <div className="db-error">{form.errors.videoUrl}</div>}
                                        </div>
                                        <div>
                                            <label className="crud-label">Judul video</label>
                                            <input className="db-input" value={form.data.videoTitle} onChange={(event) => form.setData('videoTitle', event.target.value)} />
                                            {form.errors.videoTitle && <div className="db-error">{form.errors.videoTitle}</div>}
                                        </div>
                                    </div>
                                    <div>
                                        <button className="db-btn primary" type="submit" disabled={form.processing}>
                                            <Upload size={14} /> {form.processing ? 'Mengunggah…' : 'Unggah materi'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Daftar materi ({materials.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {materials.length === 0 ? (
                                <EmptyState icon={FileText} title="Belum ada materi" description="Unggah materi pertama untuk kelas ini." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {materials.map((material) => (
                                        <MaterialRow key={material.id} material={material} categories={categories} />
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
