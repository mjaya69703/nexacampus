// Daftar pembayaran — read-only + verifikasi dari detail (paritas Blade).
import { Head, router } from '@inertiajs/react';
import { BadgeCheck, Eye, Receipt } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; number: string; invoice: string;
    student: string; nim: string | null; target: string;
    amount: string; method: string; methodText: string;
    status: string; statusTone: string; statusText: string;
    paidAt: string | null; showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { view: boolean; verify: boolean };
    stats: { total: number; pending: number; verified: number; verifiedAmount: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        number: string; invoice: string; q: string; nim: string; method: string; status: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    methods: string[];
    statuses: string[];
    urls: { index: string; export: string; exportPdf: string };
};

const money = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function PaymentIndex({ shell, can, stats, data, filters, methods, statuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [number, setNumber] = useState(filters.number);
    const [invoice, setInvoice] = useState(filters.invoice);
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

    const activeFilterCount = [number, invoice, nim, filters.method, filters.status]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNumber('');
        setInvoice('');
        setNim('');
        visit({ number: '', invoice: '', nim: '', method: '', status: '', page: 1 }, true);
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
            <Head title={`Pembayaran · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Receipt}
                        eyebrow="Keuangan"
                        title="Pembayaran Mahasiswa"
                        description="Bukti bayar masuk dari portal mahasiswa — verifikasi atau tolak dari halaman detail."
                    />

                    <section className="db-stats" aria-label="Statistik pembayaran">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Receipt size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total bayar</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Receipt size={20} /></span>
                            <div><span className="db-stat-num">{stats.pending}</span><span className="db-stat-label">Menunggu review</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><BadgeCheck size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.verifiedAmount)}</span><span className="db-stat-label">Terverifikasi ({stats.verified})</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Pembayaran</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'payment_number', label: 'Pembayaran', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.number}</b>
                                                <small className="db-hint">Invoice {row.invoice} · {row.target}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student', label: 'Mahasiswa', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'amount', label: 'Nominal', sortable: true, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.amount}</b>
                                                <small className="db-hint">{row.methodText}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusText}</span>,
                                    },
                                    { key: 'paid_at', label: 'Waktu Bayar', sortable: true, render: (row) => row.paidAt ?? '-' },
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
                                            placeholder="Nomor bayar…"
                                            onChange={(e) => setNumber(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ number, page: 1 }, true); } }}
                                            aria-label="Filter nomor bayar"
                                        />
                                        <input
                                            className="db-input"
                                            value={invoice}
                                            placeholder="Nomor tagihan…"
                                            onChange={(e) => setInvoice(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ invoice, page: 1 }, true); } }}
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
                                            value={filters.method}
                                            onChange={(e) => visit({ method: e.target.value, page: 1 }, true)}
                                            aria-label="Filter metode"
                                        >
                                            <option value="">Semua metode</option>
                                            {methods.map((m) => <option key={m} value={m}>{m}</option>)}
                                        </select>
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
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail & verifikasi">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Pembayaran"
                                exportHref={exportHref}
                                exportExtra={[{ label: 'PDF laporan', href: urls.exportPdf }]}
                                emptyText="Belum ada pembayaran yang cocok dengan filter."
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
