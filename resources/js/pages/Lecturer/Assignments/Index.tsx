// Daftar tugas dosen — filter kelas/status dan pintasan pembuatan tugas per kelas.
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ClipboardList, Filter, Plus, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Offering = {
    id: number; courseCode: string; courseName: string; label: string; academicYear: string;
    studyProgram: string; enrolledCount: number; createUrl: string;
};

type Row = {
    id: number; offeringId: number; title: string; course: string; label: string; academicYear: string;
    dueAt: string; isPublished: boolean; isPastDue: boolean; enrolled: number; submitted: number;
    graded: number; returned: number; reviewQueue: number; progress: number; url: string; editUrl: string;
};

type Props = {
    shell: ShellProps;
    offerings: Offering[];
    rows: Row[];
    filters: { offeringId: string; status: string };
    urls: { index: string; create: string };
};

export default function AssignmentsIndex({ shell, offerings, rows, filters, urls }: Props) {
    const [offeringId, setOfferingId] = useState(filters.offeringId);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(urls.index, { offering_id: offeringId, status }, { preserveState: true, preserveScroll: true });
    };

    const selectedOffering = offerings.find((offering) => String(offering.id) === offeringId);
    const createUrl = selectedOffering?.createUrl ?? urls.create;
    const totalQueue = rows.reduce((total, row) => total + row.reviewQueue, 0);

    return (
        <AdminShell shell={shell}>
            <Head title={`Tugas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ClipboardList}
                        eyebrow="Akademik · Tugas"
                        title="Tugas"
                        description="Daftar tugas seluruh kelas yang Anda ampu beserta progres pengumpulan."
                        badges={[`${rows.length} tugas`, `${totalQueue} perlu review`, `${offerings.length} kelas`]}
                        actions={<Link href={createUrl} className="db-btn primary"><Plus size={14} /> Buat Tugas</Link>}
                    />

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Filter size={16} /> Filter</h2></div>
                        <div className="db-card-body">
                            <form onSubmit={applyFilters} className="crud-grid">
                                <div>
                                    <label className="crud-label">Kelas</label>
                                    <select className="db-input" value={offeringId} onChange={(event) => setOfferingId(event.target.value)}>
                                        <option value="">Semua kelas</option>
                                        {offerings.map((offering) => (
                                            <option key={offering.id} value={offering.id}>
                                                {offering.courseCode} - {offering.courseName} / {offering.label} · {offering.academicYear}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="crud-label">Status</label>
                                    <select className="db-input" value={status} onChange={(event) => setStatus(event.target.value)}>
                                        <option value="all">Semua status</option>
                                        <option value="published">Published</option>
                                        <option value="draft">Draft</option>
                                        <option value="overdue">Melewati deadline</option>
                                        <option value="review">Perlu review</option>
                                    </select>
                                </div>
                                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-end' }}>
                                    <button className="db-btn primary sm" type="submit">Terapkan</button>
                                    <button className="db-btn ghost sm" type="button" onClick={() => { setOfferingId(''); setStatus('all'); router.get(urls.index, {}, { preserveState: true }); }}>Reset</button>
                                </div>
                            </form>
                        </div>
                    </section>

                    {offerings.length > 0 && (
                        <section className="db-card">
                            <div className="db-card-head"><h2 className="db-card-title"><Users size={16} /> Kelas Anda ({offerings.length})</h2></div>
                            <div className="db-card-body">
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead><tr><th>Mata kuliah</th><th>Kelas</th><th>Tahun</th><th>Mahasiswa</th><th>Aksi</th></tr></thead>
                                        <tbody>
                                            {offerings.map((offering) => (
                                                <tr key={offering.id}>
                                                    <td><b>{offering.courseCode} - {offering.courseName}</b><br /><small>{offering.studyProgram}</small></td>
                                                    <td>{offering.label}</td>
                                                    <td>{offering.academicYear}</td>
                                                    <td>{offering.enrolledCount}</td>
                                                    <td><Link href={offering.createUrl} className="db-btn ghost sm"><Plus size={12} /> Buat tugas</Link></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><ClipboardList size={16} /> Daftar tugas ({rows.length})</h2></div>
                        <div className="db-card-body">
                            {rows.length === 0 ? (
                                <EmptyState icon={ClipboardList} title="Belum ada tugas" description="Buat tugas baru atau ubah filter yang dipilih." />
                            ) : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {rows.map((row) => (
                                        <article key={row.id} className="db-class">
                                            <div className="db-class-top">
                                                <div style={{ minWidth: 0 }}>
                                                    <b>{row.title}</b>
                                                    <small>{row.course} · {row.label} · {row.academicYear} · deadline {row.dueAt}</small>
                                                </div>
                                                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                    <span className={`db-badge ${row.isPublished ? 'green' : 'gray'}`}>{row.isPublished ? 'Published' : 'Draft'}</span>
                                                    {row.isPastDue && <span className="db-badge red"><AlertTriangle size={11} /> Lewat deadline</span>}
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', fontSize: 13, margin: '8px 0' }}>
                                                <span>{row.submitted}/{row.enrolled} dikumpulkan</span>
                                                <span>{row.graded} dinilai</span>
                                                <span>{row.reviewQueue} perlu review</span>
                                                <span>{row.returned} dikembalikan</span>
                                            </div>
                                            <div className="db-progress"><i style={{ width: `${row.progress}%` }} /></div>
                                            <div className="db-class-actions">
                                                <Link href={row.url} className="db-btn ghost sm">Review</Link>
                                                <Link href={row.editUrl} className="db-btn ghost sm">Edit</Link>
                                            </div>
                                        </article>
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
