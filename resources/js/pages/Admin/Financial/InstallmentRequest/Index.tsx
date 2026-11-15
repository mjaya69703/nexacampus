// Daftar pengajuan cicilan — tinjau dari detail (paritas Blade).
import { Head, router } from '@inertiajs/react';
import { CalendarClock, Eye } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; invoice: string; student: string; nim: string | null;
    tenor: string; simulated: string; status: string; statusTone: string; statusText: string;
    at: string | null; showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; review: boolean };
    stats: { total: number; submitted: number; approved: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        number: string; q: string; nim: string; status: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    statuses: string[];
    urls: { index: string; export: string; exportPdf: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function InstallmentRequestIndex({ shell, can, stats, data, filters, statuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [number, setNumber] = useState(filters.number);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [number, nim, filters.status]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNumber('');
        setNim('');
        visit({ number: '', nim: '', status: '', page: 1 }, true);
    };

    const toggleSort = (key: string) => {
        visit({
            sort: key,
            direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
            page: 1,
        });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((v) => v !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    return (
        <AdminShell shell={shell}>
            <Head title={`Pengajuan Cicilan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Keuangan"
                        title="Pengajuan Cicilan"
                        description="Permohonan relaksasi pembayaran dari mahasiswa — putusan lewat approval berjenjang."
                    />

                    <section className="db-stats" aria-label="Statistik pengajuan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total pengajuan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.submitted}</span><span className="db-stat-label">Menunggu review</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><CalendarClock size={20} /></span>
                            <div><span className="db-stat-num">{stats.approved}</span><span className="db-stat-label">Disetujui</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pengajuan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'invoice', label: 'Tagihan', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.invoice}</b>
                                                <small className="db-hint">{row.tenor} · {row.simulated}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student', label: 'Mahasiswa', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim ?? '-'} · {row.at ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusText}</span>,
                                    },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={toggleSort}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama, email…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <input
                                            className="db-input"
                                            value={number}
                                            placeholder="Nomor tagihan…"
                                            onChange={(e) => setNumber(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ number, page: 1 }, true); } }}
                                            aria-label="Filter nomor tagihan"
                                        />
                                        <input
                                            className="db-input"
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ nim, page: 1 }, true); } }}
                                            aria-label="Filter NIM"
                                        />
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(e) => visit({ status: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions={can.view}
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Tinjau">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Pengajuan"
                                exportHref={exportHref}
                                exportExtra={[{ label: 'PDF laporan', href: urls.exportPdf }]}
                                emptyText="Belum ada pengajuan yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
