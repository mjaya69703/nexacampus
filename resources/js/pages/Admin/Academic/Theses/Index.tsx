// Daftar skripsi/tugas akhir — kit CRUD + filter status/prodi/tahun.
import { Head, router } from '@inertiajs/react';
import { BookOpen, CheckCircle2, Clock, Eye, FileCheck2, GraduationCap, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    title: string; topic: string | null; type: string; typeLabel: string;
    status: string; statusLabel: string; statusTone: string; supervisors: string;
    year: string | null; defenseAt: string | null; finalGrade: string | null; createdAt: string | null;
    showUrl: string; editUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean; approve: boolean; defense: boolean; grade: boolean };
    stats: { total: number; submitted: number; inProgress: number; defenseReady: number; completed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string; status: string; year: string; program: string; supervisor: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    options: {
        statuses: Record<string, string>;
        years: { id: number; name: string }[];
        programs: { id: number; name: string }[];
    };
    urls: { index: string; create: string; bulkDestroy: string; searchLecturers: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== '' && value !== undefined));
}

export default function ThesesIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Row | null>(null);
    const [bulkOpen, setBulkOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const activeFilterCount = [filters.status, filters.year, filters.program, filters.supervisor]
        .filter((value) => value !== '' && value !== undefined && value !== null).length;

    const resetFilters = () => visit({ status: '', year: '', program: '', supervisor: '', page: 1 }, true);

    const toggleSort = (key: string) => visit({
        sort: key,
        direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
        page: 1,
    });

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirmDelete = () => {
        if (!pending) return;
        setProcessing(true);
        router.delete(pending.deleteUrl, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setPending(null); setSelected([]); },
        });
    };

    const confirmBulk = () => {
        setProcessing(true);
        router.post(urls.bulkDestroy, { ids: selected }, {
            preserveScroll: true,
            onFinish: () => { setProcessing(false); setBulkOpen(false); setSelected([]); },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Skripsi / Tugas Akhir · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Akademik"
                        title="Skripsi / Tugas Akhir"
                        description="Kelola pengajuan topik, penetapan pembimbing, milestone bimbingan, jadwal sidang, penilaian, dan arsip karya."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Pengajuan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik skripsi">
                        <div className="db-stat">
                            <span className="db-stat-icon"><BookOpen size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total pengajuan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock size={20} /></span>
                            <div><span className="db-stat-num">{stats.submitted}</span><span className="db-stat-label">Menunggu persetujuan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.inProgress}</span><span className="db-stat-label">Bimbingan berjalan</span></div>
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
                            <h2 className="db-card-title"><FileCheck2 size={16} /> Daftar Skripsi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'student', label: 'Mahasiswa', sortable: false,
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
                                                <small className="db-hint">{row.typeLabel}{row.topic ? ` · ${row.topic}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'supervisors', label: 'Pembimbing', render: (row) => row.supervisors || <span className="db-hint">Belum ditetapkan</span> },
                                    {
                                        key: 'status', label: 'Status', sortable: true,
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusLabel}</span>,
                                    },
                                    { key: 'year', label: 'Tahun', render: (row) => row.year ?? '-' },
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
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari judul, topik, NIM, atau nama mahasiswa…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select className="db-input" value={filters.status} onChange={(event) => visit({ status: event.target.value, page: 1 }, true)} aria-label="Filter status">
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.year} onChange={(event) => visit({ year: event.target.value, page: 1 }, true)} aria-label="Filter tahun akademik">
                                            <option value="">Semua tahun</option>
                                            {options.years.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}
                                        </select>
                                        <select className="db-input" value={filters.program} onChange={(event) => visit({ program: event.target.value, page: 1 }, true)} aria-label="Filter program studi">
                                            <option value="">Semua prodi</option>
                                            {options.programs.map((program) => <option key={program.id} value={program.id}>{program.name}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setBulkOpen(true)}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete ? (row) => setPending(row) : undefined}
                                showActions={can.view || can.update || can.delete}
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                emptyText="Belum ada pengajuan skripsi yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title="Hapus pengajuan skripsi?"
                    message={pending ? `"${pending.title}" milik ${pending.student} akan dipindah ke sampah.` : ''}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmDelete}
                    onCancel={() => setPending(null)}
                />

                <ConfirmModal
                    open={bulkOpen}
                    title="Hapus pengajuan terpilih?"
                    message={`${selected.length} pengajuan akan dipindah ke sampah. Pengajuan yang sudah selesai dilewati.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={confirmBulk}
                    onCancel={() => setBulkOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
