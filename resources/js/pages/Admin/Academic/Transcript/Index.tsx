// Daftar transkrip — read-only + sync (paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Eye, FileBadge, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    results: number; entries: number; ipk: string | null;
    showUrl: string; syncUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; sync: boolean };
    stats: { students: number; withEntries: number; entries: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; program: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    programOptions: { id: number; name: string }[];
    urls: { index: string; export: string; bulkSync: string };
};

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function TranscriptIndex({ shell, can, stats, data, filters, programOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
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

    const syncRow = (row: Row) => {
        setProcessing(true);
        router.post(row.syncUrl, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    const bulkSync = () => {
        setProcessing(true);
        router.post(urls.bulkSync, { ids: selected }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setSelected([]);
            },
        });
    };

    const exportHref = (format: string) => {
        const params = new URLSearchParams(
            Object.entries(buildQuery(filters, search, { format })).map(([k, v]) => [k, String(v)]),
        );
        if (selected.length > 0) {
            selected.forEach((id) => params.append('ids[]', String(id)));
        }
        return `${urls.export}?${params.toString()}`;
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Transkrip · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileBadge}
                        eyebrow="Akademik"
                        title="Transkrip Mahasiswa"
                        description="Agregat read-only dari nilai Finalized/Published. Tidak ada ubah/hapus manual — sinkron ulang bila angka terlihat basi."
                    />

                    <section className="db-stats" aria-label="Statistik transkrip">
                        <div className="db-stat">
                            <span className="db-stat-icon"><FileBadge size={20} /></span>
                            <div><span className="db-stat-num">{stats.students}</span><span className="db-stat-label">Mahasiswa</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><FileBadge size={20} /></span>
                            <div><span className="db-stat-num">{stats.withEntries}</span><span className="db-stat-label">Punya Entri</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><FileBadge size={20} /></span>
                            <div><span className="db-stat-num">{stats.entries}</span><span className="db-stat-label">Total Entri</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Transkrip</h2>
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
                                                <small className="db-hint">{row.nim ?? '-'} · {row.program ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'entries', label: 'Semester / Entri', align: 'right',
                                        render: (row) => <span className="db-badge green">{row.results} smt · {row.entries} MK</span>,
                                    },
                                    {
                                        key: 'ipk', label: 'IPK', align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-heading)' }}>{row.ipk ?? '-'}</b>,
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
                                    <>
                                        <input
                                            className="db-input"
                                            style={{ maxWidth: 140 }}
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ nim, page: 1 }, true); } }}
                                            aria-label="Filter NIM"
                                        />
                                        <select
                                            className="db-input"
                                            value={filters.program}
                                            onChange={(e) => visit({ program: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prodi"
                                        >
                                            <option value="">Semua prodi</option>
                                            {programOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                    </>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions={can.view || can.sync}
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {can.sync && (
                                            <button
                                                type="button"
                                                className="db-btn ghost sm"
                                                title="Sinkron ulang"
                                                disabled={processing}
                                                onClick={() => syncRow(row)}
                                            >
                                                <RefreshCw size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                extraActions={can.sync && selected.length > 0 ? (
                                    <button
                                        type="button"
                                        className="db-btn ghost sm"
                                        disabled={processing}
                                        onClick={bulkSync}
                                        title="Sinkron yang terpilih"
                                    >
                                        <RefreshCw size={13} /> Sync terpilih
                                    </button>
                                ) : undefined}
                                canCreate={false}
                                createLabel="Transkrip"
                                exportHref={exportHref('xlsx')}
                                exportExtra={[
                                    { label: 'CSV', href: exportHref('csv') },
                                ]}
                                emptyText="Belum ada mahasiswa yang cocok dengan filter."
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
