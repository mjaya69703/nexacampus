// Detail materi — metadata, berkas unduhan, dan riwayat versi.
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Clock, Download, FileText, History, Link2, User } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type MaterialFile = { id: number; name: string; type: string; size: string; isLink: boolean; url: string };
type Version = { id: number; number: number | null; changeType: string | null; summary: string | null; createdAt: string };

type Material = {
    id: number; title: string; description: string | null; category: string; categoryLabel: string;
    meetingNumber: number | null; isPublished: boolean; uploadedBy: string; uploadedAt: string;
    course: string; class: string; academicYear: string; downloadCount: number;
    files: MaterialFile[]; versions: Version[];
};

type Props = { shell: ShellProps; material: Material; urls: { list: string; offering: string } };

export default function CourseMaterialsShow({ shell, material, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`${material.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Akademik · Materi"
                        title={material.title}
                        description={`${material.course} · ${material.class} · ${material.academicYear}`}
                        badges={[
                            material.isPublished ? 'Published' : 'Draft',
                            `Pertemuan ${material.meetingNumber ?? '-'}`,
                            material.categoryLabel,
                        ]}
                        actions={
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <Link className="db-btn light" href={urls.list}><ArrowLeft size={15} /> Daftar kelas</Link>
                                <Link className="db-btn light" href={urls.offering}><BookOpen size={15} /> Materi kelas</Link>
                            </div>
                        }
                    />

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Informasi materi</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10, fontSize: 13 }}>
                                <div><b>Kategori:</b> {material.categoryLabel}</div>
                                <div><b>Pertemuan:</b> {material.meetingNumber ?? '-'}</div>
                                <div><b>Status:</b> {material.isPublished ? 'Published' : 'Draft'}</div>
                                <div><b>Diunggah oleh:</b> {material.uploadedBy}</div>
                                <div><b>Waktu unggah:</b> {material.uploadedAt}</div>
                                <div><b>Total unduhan:</b> {material.downloadCount}</div>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Ringkasan</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                <div className="db-summary">
                                    <div><b>{material.files.length} berkas</b><small>{material.files.filter((file) => file.isLink).length} tautan · {material.files.filter((file) => !file.isLink).length} file</small></div>
                                    <FileText size={18} />
                                </div>
                                <div className="db-summary">
                                    <div><b>{material.versions.length} versi</b><small>Perubahan tercatat otomatis</small></div>
                                    <History size={18} />
                                </div>
                                <div className="db-summary">
                                    <div><b>{material.downloadCount} unduhan</b><small>Diakses mahasiswa kelas</small></div>
                                    <Download size={18} />
                                </div>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Deskripsi</h2>
                        </div>
                        <div className="db-card-body">
                            {material.description ? (
                                <p style={{ margin: 0, whiteSpace: 'pre-wrap', lineHeight: 1.6 }}>{material.description}</p>
                            ) : (
                                <p className="db-hint" style={{ margin: 0 }}>Belum ada deskripsi untuk materi ini.</p>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Download size={16} /> Berkas ({material.files.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {material.files.length === 0 ? (
                                <EmptyState icon={FileText} title="Belum ada berkas" description="Materi ini belum memiliki lampiran." />
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Nama</th><th>Tipe</th><th>Ukuran</th><th>Aksi</th></tr>
                                        </thead>
                                        <tbody>
                                            {material.files.map((file) => (
                                                <tr key={file.id}>
                                                    <td>
                                                        <b style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                                            {file.isLink ? <Link2 size={13} /> : <FileText size={13} />} {file.name}
                                                        </b>
                                                    </td>
                                                    <td>{file.isLink ? 'Tautan' : file.type}</td>
                                                    <td>{file.size}</td>
                                                    <td>
                                                        <a className="db-btn ghost sm" href={file.url} target="_blank" rel="noreferrer">
                                                            {file.isLink ? <Link2 size={12} /> : <Download size={12} />} {file.isLink ? 'Buka tautan' : 'Unduh'}
                                                        </a>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><History size={16} /> Riwayat versi</h2>
                        </div>
                        <div className="db-card-body">
                            {material.versions.length === 0 ? (
                                <p className="db-hint" style={{ margin: 0 }}>Belum ada perubahan versi yang tercatat.</p>
                            ) : (
                                <div className="db-timeline">
                                    {material.versions.map((version) => (
                                        <div className="db-timeline-item" key={version.id}>
                                            <span className="db-timeline-dot" />
                                            <div className="db-timeline-main">
                                                <b>Versi {version.number ?? '-'} · {version.changeType ?? 'update'}</b>
                                                <small>{version.summary ?? 'Tanpa catatan perubahan.'}</small>
                                            </div>
                                            <div className="db-timeline-when">
                                                <small><Clock size={12} /> {version.createdAt}</small>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                            <Link className="db-btn ghost" href={urls.list}><ArrowLeft size={14} /> Kembali ke daftar kelas</Link>
                            <Link className="db-btn primary" href={urls.offering}><BookOpen size={14} /> Kelola materi kelas</Link>
                            <span className="db-hint" style={{ display: 'inline-flex', gap: 6, alignItems: 'center', marginLeft: 'auto' }}>
                                <User size={13} /> {material.uploadedBy}
                            </span>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
