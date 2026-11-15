// Tridharma saya — employee self-service (Inertia React).
import { Head } from '@inertiajs/react';
import { Award, CircleCheck, FileText, FolderOpen, Hourglass, Paperclip, Plus, Search, Sprout, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { EmptyState } from '../../../../components/Shared/EmptyState';
import '../../../../../css/employee.css';

type RecordItem = {
    id: number; type: string; typeLabel: string; title: string; scheme: string | null;
    fundingSource: string | null; fundingAmount: number; fundingLabel: string;
    startLabel: string | null; status: string; progress: number;
    milestonesCount: number; outputsCount: number; attachmentsCount: number;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    records: RecordItem[];
    createUrl: string;
    stats: { total: number; approved: number; draft: number; outputs: number; funding: number; fundingLabel: string };
};

const statusTone = (status: string): string => {
    if (['approved', 'active', 'completed'].includes(status)) return 'green';
    if (status === 'rejected') return 'red';
    if (['submitted', 'in_approval'].includes(status)) return 'amber';
    if (status === 'draft') return 'gray';
    return 'gray';
};

const statusLabel = (status: string): string => {
    const map: Record<string, string> = {
        draft: 'Draft', submitted: 'Diajukan', in_approval: 'Approval', approved: 'Disetujui',
        active: 'Aktif', completed: 'Selesai', rejected: 'Ditolak', archived: 'Arsip',
    };
    return map[status] ?? status.replace(/_/g, ' ');
};

export default function TridharmaIndex({ shell, records, createUrl, stats }: Props) {
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');

    const visible = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return records.filter((record) => {
            if (filter !== 'all' && record.type !== filter) return false;
            if (!needle) return true;
            return [record.title, record.scheme, record.fundingSource].filter(Boolean).join(' ').toLowerCase().includes(needle);
        });
    }, [records, filter, search]);

    return (
        <AdminShell shell={shell}>
            <Head title={`Tridharma Saya · ${shell.appName}`} />
            <div className="em-root">
                <div className="em-stack">
                    <section className="em-hero">
                        <div className="em-hero-inner">
                            <div className="em-hero-copy">
                                <span className="em-hero-icon"><Sprout size={26} /></span>
                                <div>
                                    <small>Ruang kerja dosen</small>
                                    <h1>Tridharma Saya</h1>
                                    <p>Kelola proposal, approval, progress, luaran, dan dokumen pendukung.</p>
                                    <div className="em-hero-pills">
                                        <span className="em-pill"><FolderOpen size={12} /> {stats.total} record</span>
                                        <span className="em-pill"><CircleCheck size={12} /> {stats.approved} disetujui</span>
                                        <span className="em-pill"><Paperclip size={12} /> {stats.outputs} luaran</span>
                                    </div>
                                </div>
                            </div>
                            <div className="em-hero-actions">
                                <a className="em-btn light" href={createUrl}><Plus size={15} /> Ajukan</a>
                            </div>
                        </div>
                    </section>

                    <section className="em-stats" aria-label="Statistik tridharma">
                        <div className="em-stat">
                            <span className="em-stat-icon"><FileText size={20} /></span>
                            <div><span className="em-stat-num">{stats.draft}</span><span className="em-stat-label">Draft</span></div>
                        </div>
                        <div className="em-stat">
                            <span className="em-stat-icon gold"><Hourglass size={20} /></span>
                            <div><span className="em-stat-num">{records.filter((r) => ['submitted', 'in_approval'].includes(r.status)).length}</span><span className="em-stat-label">Approval</span></div>
                        </div>
                        <div className="em-stat">
                            <span className="em-stat-icon green"><TrendingUp size={20} /></span>
                            <div><span className="em-stat-num">{stats.outputs}</span><span className="em-stat-label">Luaran</span></div>
                        </div>
                        <div className="em-stat">
                            <span className="em-stat-icon"><Award size={20} /></span>
                            <div><span className="em-stat-num">{stats.fundingLabel}</span><span className="em-stat-label">Pendanaan</span></div>
                        </div>
                    </section>

                    <section className="em-card">
                        <div className="em-card-head">
                            <h2 className="em-card-title"><FolderOpen size={16} /> Daftar kegiatan</h2>
                            <span className="em-badge gray">{visible.length}</span>
                        </div>
                        <div className="em-card-body" style={{ display: 'grid', gap: 12 }}>
                            <div className="em-grid-2">
                                <div className="em-field">
                                    <label>Cari</label>
                                    <input className="em-input" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Judul, skema, sumber dana…" />
                                </div>
                                <div className="em-field">
                                    <label>Kategori</label>
                                    <select className="em-select" value={filter} onChange={(e) => setFilter(e.target.value)}>
                                        <option value="all">Semua</option>
                                        <option value="research">Penelitian</option>
                                        <option value="community_service">Pengabdian</option>
                                        <option value="publication">Publikasi</option>
                                    </select>
                                </div>
                            </div>
                            {visible.length === 0 && (
                                <EmptyState icon={Search} iconSize={26} title="Tidak ada kegiatan" description="Belum ada record yang cocok dengan filter. Ubah kata kunci atau ajukan kegiatan baru." />
                            )}
                            {visible.map((record) => (
                                <a key={record.id} href={record.showUrl} className="em-member" style={{ textDecoration: 'none' }}>
                                    <div style={{ minWidth: 0, flex: 1 }}>
                                        <div className="em-member-pills">
                                            <span className="em-badge">{record.typeLabel}</span>
                                            <span className={`em-badge ${statusTone(record.status)}`}>{statusLabel(record.status)}</span>
                                        </div>
                                        <b>{record.title}</b>
                                        <small>
                                            {[record.scheme, record.fundingSource, record.startLabel].filter(Boolean).join(' · ') || '-'}
                                            {` · ${record.milestonesCount} milestone · ${record.outputsCount} luaran · ${record.attachmentsCount} file`}
                                        </small>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 8 }}>
                                            <div className="em-progress" style={{ flex: 1 }}><i style={{ width: `${record.progress}%` }} /></div>
                                            <small className="em-hint" style={{ fontWeight: 800 }}>{record.progress}%</small>
                                        </div>
                                    </div>
                                    <span className="em-badge gray">{record.fundingLabel}</span>
                                </a>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
