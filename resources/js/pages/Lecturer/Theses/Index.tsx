// Portal dosen — daftar bimbingan skripsi/tugas akhir yang diampu atau diuji.
import { Head, router } from '@inertiajs/react';
import { BookOpen, CheckCircle2, Clock, Eye, GraduationCap, ShieldCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../components/Shared/Crud/FilterPanel';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    title: string; typeLabel: string; status: string; statusLabel: string; statusTone: string;
    supervisors: string; year: string | null; defenseAt: string | null; finalGrade: string | null;
    createdAt: string | null; showUrl: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    stats: { total: number; active: number; defenseReady: number; completed: number; awaitingReview: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; status: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    options: { statuses: Record<string, string> };
    urls: { index: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== '' && value !== undefined));
}

export default function LecturerThesesIndex({ shell, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);

    const visit = (overrides: Record<string, string | number | undefined>) => {
        router.get(urls.index, buildQuery(filters, search, overrides), { preserveState: true, replace: true });
    };

    const activeFilterCount = filters.status !== '' ? 1 : 0;

    return (
        <AdminShell shell={shell}>
            <Head title={`Bimbingan Skripsi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Bimbingan"
                        title="Bimbingan Skripsi / Tugas Akhir"
                        description="Review milestone mahasiswa, pantau progres bimbingan, dan beri nilai sidang tempat Anda bertugas sebagai penguji."
                    />

                    <section className="db-stats">
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total karya</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Users size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Bimbingan aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock size={20} /></span>
                            <div><span className="db-stat-num">{stats.awaitingReview}</span><span className="db-stat-label">Menunggu review</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon purple"><ShieldCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.defenseReady}</span><span className="db-stat-label">Siap sidang</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CheckCircle2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.completed}</span><span className="db-stat-label">Selesai</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Daftar Bimbingan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'student', label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim ?? '-'}{row.program ? ` · ${row.program}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'title', label: 'Judul', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">{row.typeLabel}{row.year ? ` · ${row.year}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'status', label: 'Status', sortable: true, render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusLabel}</span> },
                                    { key: 'defense', label: 'Sidang', render: (row) => row.defenseAt ?? <span className="db-hint">-</span> },
                                    {
                                        key: 'final_grade', label: 'Nilai',
                                        render: (row) => row.finalGrade
                                            ? <span className="db-badge green">{row.finalGrade}</span>
                                            : <span className="db-hint">-</span>,
                                    },
                                    { key: 'created_at', label: 'Dibuat', sortable: true, render: (row) => row.createdAt ?? '-' },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({ sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 })}
                                searchPlaceholder="Cari judul, topik, NIM, atau nama mahasiswa…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={() => visit({ status: '', page: 1 })}>
                                        <select className="db-input" value={filters.status} onChange={(event) => visit({ status: event.target.value, page: 1 })} aria-label="Filter status">
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={[]}
                                onToggle={() => undefined}
                                onToggleAll={() => undefined}
                                selectable={false}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Detail bimbingan"><Eye size={13} /></a>
                                )}
                                canCreate={false}
                                emptyText="Belum ada mahasiswa bimbingan yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
