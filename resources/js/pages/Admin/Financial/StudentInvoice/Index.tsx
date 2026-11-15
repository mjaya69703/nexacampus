// Daftar tagihan mahasiswa — kit CRUD (terbit, batal, PDF, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Ban, Eye, FileDown, FileText, Megaphone, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; number: string; type: string; typeText: string;
    student: string; nim: string | null; program: string | null;
    year: string | null; semester: number | null;
    total: string; outstanding: string; status: string; statusTone: string; statusText: string;
    dueDate: string | null; isEditable: boolean;
    showUrl: string; editUrl: string; issueUrl: string; cancelUrl: string; pdfUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; draft: number; issued: number; overdue: number; outstanding: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        number: string; q: string; nim: string; type: string; year: string; status: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    types: string[];
    statuses: string[];
    yearOptions: { id: number; name: string }[];
    urls: {
        index: string; create: string; store: string; export: string; exportPdf: string; bulkCancel: string;
    };
};

type Pending = { kind: 'issue' | 'cancel'; row: Row } | { kind: 'bulk-cancel' } | null;

const money = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudentInvoiceIndex({ shell, can, stats, data, filters, types, statuses, yearOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [number, setNumber] = useState(filters.number);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
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

    const activeFilterCount = [number, nim, filters.type, filters.year, filters.status]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNumber('');
        setNim('');
        visit({ number: '', nim: '', type: '', year: '', status: '', page: 1 }, true);
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

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        if (pending.kind === 'issue') {
            router.post(pending.row.issueUrl, {}, { preserveScroll: true, onFinish: done });
        } else if (pending.kind === 'cancel') {
            router.post(pending.row.cancelUrl, {}, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkCancel, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const modal = pending?.kind === 'issue'
        ? { title: 'Terbitkan tagihan?', message: 'Tagihan draft diterbitkan resmi ke mahasiswa dan notifikasi dikirim.', confirm: 'Ya, terbitkan' }
        : pending?.kind === 'bulk-cancel'
            ? { title: `Batalkan ${selected.length} tagihan?`, message: 'Tagihan terpilih dibatalkan dan tidak dihitung sebagai tunggakan.', confirm: 'Ya, batalkan' }
            : { title: 'Batalkan tagihan?', message: 'Tagihan dibatalkan dan tidak dihitung sebagai tunggakan mahasiswa.', confirm: 'Ya, batalkan' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Tagihan Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileText}
                        eyebrow="Keuangan"
                        title="Tagihan Mahasiswa"
                        description="Terbitkan, pantau tunggakan, terapkan penyesuaian, dan cetak invoice."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Terbitkan Tagihan</a>
                        ) : undefined}
                    />

                    <section className="db-stats" aria-label="Statistik tagihan">
                        <div className="db-stat">
                            <span className="db-stat-icon"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total tagihan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{stats.issued + stats.overdue}</span><span className="db-stat-label">Aktif + menunggak</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.outstanding)}</span><span className="db-stat-label">Total tunggakan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><FileText size={20} /></span>
                            <div><span className="db-stat-num">{stats.draft}</span><span className="db-stat-label">Draft</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Tagihan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', sortable: true, render: (row) => row.no },
                                    {
                                        key: 'invoice_number', label: 'Tagihan', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.number}</b>
                                                <small className="db-hint">{row.typeText}{row.year ? ` · ${row.year}` : ''}{row.semester ? ` · Smt ${row.semester}` : ''}</small>
                                            </span>
                                        ),
                                    },
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
                                        key: 'total_amount', label: 'Total / Sisa', sortable: true, align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.total}</b>
                                                <small className="db-hint">Sisa {row.outstanding}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusText}</span>,
                                    },
                                    { key: 'due_date', label: 'Jatuh Tempo', sortable: true, render: (row) => row.dueDate ?? '-' },
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
                                            aria-label="Filter nomor"
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
                                            value={filters.type}
                                            onChange={(e) => visit({ type: e.target.value, page: 1 }, true)}
                                            aria-label="Filter jenis"
                                        >
                                            <option value="">Semua jenis</option>
                                            {types.map((t) => <option key={t} value={t}>{t}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.year}
                                            onChange={(e) => visit({ year: e.target.value, page: 1 }, true)}
                                            aria-label="Filter tahun"
                                        >
                                            <option value="">Semua tahun</option>
                                            {yearOptions.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
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
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk-cancel' })}
                                bulkLabel="Batalkan terpilih"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'cancel', row }) : undefined}
                                showActions
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {can.update && row.status === 'draft' && (
                                            <button type="button" className="db-btn ghost sm" title="Terbitkan" onClick={() => setPending({ kind: 'issue', row })}>
                                                <Megaphone size={13} />
                                            </button>
                                        )}
                                        {can.update && row.isEditable && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                        <a className="db-btn ghost sm" href={row.pdfUrl} target="_blank" rel="noreferrer" title="Cetak PDF">
                                            <FileDown size={13} />
                                        </a>
                                        {can.delete && row.status === 'cancelled' && (
                                            <span className="db-btn ghost sm" title="Dibatalkan" style={{ cursor: 'default', opacity: 0.55 }}>
                                                <Ban size={13} />
                                            </span>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Terbitkan Tagihan"
                                exportHref={exportHref}
                                exportExtra={[{ label: 'PDF laporan', href: urls.exportPdf }]}
                                emptyText="Belum ada tagihan yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={modal.title}
                    message={modal.message}
                    confirmLabel={modal.confirm}
                    danger={pending?.kind !== 'issue'}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
