// Log penyesuaian tagihan — read-only dengan tautan ke invoice (paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Eye, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; invoice: string; student: string;
    type: string; typeText: string; amount: string;
    by: string; at: string | null; invoiceUrl: string | null;
};

type Props = {
    shell: ShellProps;
    can: { viewInvoice: boolean };
    stats: { total: number; discount: number; penalty: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        number: string; q: string; type: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    types: string[];
    urls: { index: string; export: string };
};

const money = (n: number) => `${n < 0 ? '-' : ''}Rp ${Math.abs(n).toLocaleString('id-ID')}`;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function InvoiceAdjustmentIndex({ shell, can, stats, data, filters, types, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [number, setNumber] = useState(filters.number);
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

    const activeFilterCount = [number, filters.type]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNumber('');
        visit({ number: '', type: '', page: 1 }, true);
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
            <Head title={`Penyesuaian Tagihan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={SlidersHorizontal}
                        eyebrow="Keuangan"
                        title="Penyesuaian Tagihan"
                        description="Riwayat potongan, denda, koreksi, dan write-off. Dibuat dari halaman detail tagihan."
                    />

                    <section className="db-stats" aria-label="Statistik penyesuaian">
                        <div className="db-stat">
                            <span className="db-stat-icon"><SlidersHorizontal size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total baris</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><SlidersHorizontal size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.discount)}</span><span className="db-stat-label">Total potongan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><SlidersHorizontal size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.penalty)}</span><span className="db-stat-label">Total penambah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Penyesuaian</h2>
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
                                                <small className="db-hint">{row.student}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'adjustment_type', label: 'Jenis / Nominal', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.typeText}</b>
                                                <small className="db-hint">{row.amount} · {row.at ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'created_by', label: 'Oleh', sortable: false, render: (row) => row.by },
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
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(e) => visit({ type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis"
                                        >
                                            <option value="">Semua jenis</option>
                                            {types.map((t) => <option key={t} value={t}>{t}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions={can.viewInvoice}
                                customActions={(row) => (
                                    <>
                                        {can.viewInvoice && row.invoiceUrl && (
                                            <a className="db-btn ghost sm" href={row.invoiceUrl} title="Lihat tagihan">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Penyesuaian"
                                exportHref={exportHref}
                                emptyText="Belum ada penyesuaian yang cocok dengan filter."
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
