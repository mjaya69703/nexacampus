// Daftar penerima beasiswa — kit CRUD (alokasi massal, paritas Blade).
import { Head, router } from '@inertiajs/react';
import { Award, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    scholarship: string; scope: string; status: string; statusTone: string; statusText: string;
    at: string | null; editUrl: string; deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; revoked: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; scholarship: string; year: string; status: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    scholarshipOptions: { id: number; name: string }[];
    yearOptions: { id: number; name: string }[];
    statuses: string[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; store: string; export: string; exportPdf: string;
        importTemplate: string; importSubmit: string;
        bulkDestroy: string; searchStudents: string;
    };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk-delete' } | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudentScholarshipIndex({ shell, can, stats, data, filters, scholarshipOptions, yearOptions, statuses, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [nim, filters.scholarship, filters.year, filters.status]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNim('');
        visit({ nim: '', scholarship: '', year: '', status: '', page: 1 }, true);
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
        } else {
            router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
        }
    };

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    const submitImport = (file: File) => {
        setProcessing(true);
        router.post(urls.importSubmit, { file }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setImportOpen(false);
            },
        });
    };

    const modal = pending?.kind === 'bulk-delete'
        ? { title: `Hapus ${selected.length} alokasi?`, message: 'Alokasi terpilih dihapus. Potongan yang sudah diterapkan ke invoice tidak ikut terhapus.', confirm: 'Ya, hapus' }
        : { title: 'Hapus alokasi?', message: 'Alokasi beasiswa dihapus. Potongan yang sudah diterapkan ke invoice tidak ikut terhapus.', confirm: 'Ya, hapus' };

    return (
        <AdminShell shell={shell}>
            <Head title={`Penerima Beasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Keuangan"
                        title="Penerima Beasiswa"
                        description="Alokasi program ke mahasiswa — bisa banyak sekaligus dalam sekali simpan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Alokasi Beasiswa</a>
                        ) : undefined}
                    />

                    <ImportResultBanner result={importResult} successText={(n) => `${n} data berhasil diimpor.`} failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`} />

                    <section className="db-stats" aria-label="Statistik penerima">
                        <div className="db-stat">
                            <span className="db-stat-icon"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total alokasi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Award size={20} /></span>
                            <div><span className="db-stat-num">{stats.revoked}</span><span className="db-stat-label">Dicabut</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Penerima</h2>
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
                                        key: 'scholarship', label: 'Beasiswa', sortable: false,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.scholarship}</b>
                                                <small className="db-hint">{row.scope}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status', label: 'Status',
                                        render: (row) => <span className={`db-badge ${row.statusTone}`}>{row.statusText}</span>,
                                    },
                                    { key: 'created_at', label: 'Dialokasikan', sortable: true, render: (row) => row.at ?? '-' },
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
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visit({ nim, page: 1 }, true); } }}
                                            aria-label="Filter NIM"
                                        />
                                        <select
                                            className="db-input"
                                            value={filters.scholarship}
                                            onChange={(e) => visit({ scholarship: e.target.value, page: 1 }, true)}
                                            aria-label="Filter beasiswa"
                                        >
                                            <option value="">Semua beasiswa</option>
                                            {scholarshipOptions.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
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
                                onBulkDelete={() => setPending({ kind: 'bulk-delete' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions={can.update || can.delete}
                                customActions={(row) => (
                                    <>
                                        {can.update && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Alokasi Beasiswa"
                                exportHref={exportHref}
                                exportExtra={[{ label: 'PDF laporan', href: urls.exportPdf }]}
                                extraActions={can.create ? (<button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}><Upload size={14} /> Import</button>) : undefined}
                                emptyText="Belum ada alokasi yang cocok dengan filter."
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
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                <ImportModal
                    open={importOpen}
                    title="Impor Penerima Beasiswa"
                    description="Unggah file sesuai template untuk mengalokasikan banyak penerima sekaligus. Satu baris gagal berarti file ditolak."
                    templateUrl={urls.importTemplate}
                    templateLabel="Unduh template"
                    processing={processing}
                    onSubmit={submitImport}
                    onClose={() => setImportOpen(false)}
                />
            </div>
        </AdminShell>
    );
}
