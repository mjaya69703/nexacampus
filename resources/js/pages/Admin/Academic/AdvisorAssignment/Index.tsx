// Daftar dosen wali — kit CRUD + transfer massal bimbingan.
import { Head, router } from '@inertiajs/react';
import { ArrowRightLeft, Eye, GraduationCap, Pencil, Plus, Trash2, Upload, UserCheck } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; advisor: string;
    advisorLabel: string; year: string; startDate: string | null; endDate: string | null;
    isActive: boolean; createdAt: string | null;
    showUrl: string; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean; restore: boolean; toggle: boolean; transfer: boolean };
    stats: { total: number; active: number; students: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; year: string; program: string; lecturer: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    yearOptions: { id: number; name: string }[];
    programOptions: { id: number; name: string }[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string; importSubmit: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string; transfer: string;
        searchStudents: string; searchLecturers: string; browseStudents: string;
    };
};

type Pending =
    | { kind: 'delete' | 'restore' | 'force'; row: Row }
    | { kind: 'bulk-delete' | 'bulk-restore' | 'bulk-force' }
    | null;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function AdvisorAssignmentIndex({ shell, can, stats, data, filters, yearOptions, programOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [transferOpen, setTransferOpen] = useState(false);
    const [transferTo, setTransferTo] = useState<AsyncOption | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const isTrash = filters.mode === 'trash';

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const visitNim = () => visit({ nim, page: 1 }, true);

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

    const toggleActive = (row: Row) => {
        router.post(row.toggleUrl, { is_active: !row.isActive }, { preserveScroll: true });
    };

    const confirmPending = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
            setSelected([]);
        };

        switch (pending.kind) {
            case 'delete':
                router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'restore':
                router.post(pending.row.restoreUrl, {}, { preserveScroll: true, onFinish: done });
                break;
            case 'force':
                router.delete(pending.row.forceUrl, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-delete':
                router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-restore':
                router.post(urls.bulkRestore, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
            case 'bulk-force':
                router.post(urls.bulkForceDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
                break;
        }
    };

    const confirmTransfer = () => {
        if (!transferTo) return;
        setProcessing(true);
        router.post(urls.transfer, { ids: selected, lecturer_profile_id: transferTo.id }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setTransferOpen(false);
                setTransferTo(null);
                setSelected([]);
            },
        });
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

    const modal = (() => {
        if (!pending) return { title: '', message: '', confirm: 'Ya', danger: true };
        if (pending.kind === 'restore') {
            return { title: 'Pulihkan penugasan?', message: `"${pending.row.student}" kembali dibimbing ${pending.row.advisor}.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'bulk-restore') {
            return { title: 'Pulihkan terpilih?', message: `${selected.length} penugasan kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'force') {
            return { title: 'Hapus permanen?', message: `Penugasan ${pending.row.student} dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-force') {
            return { title: 'Hapus permanen terpilih?', message: `${selected.length} penugasan dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-delete') {
            return { title: 'Hapus terpilih?', message: `${selected.length} penugasan dipindah ke sampah.`, confirm: 'Ya, hapus', danger: true };
        }
        return { title: 'Hapus penugasan?', message: `Penugasan ${pending.row.student} → ${pending.row.advisor} dipindah ke sampah.`, confirm: 'Ya, hapus', danger: true };
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Dosen Wali · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title="Dosen Wali"
                        description="Satu mahasiswa hanya boleh punya satu pembimbing aktif per tahun/rentang yang sama."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tugaskan</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} penugasan berhasil diimpor.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik dosen wali">
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total penugasan</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><UserCheck size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.students}</span><span className="db-stat-label">Mahasiswa terbimbing</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Dosen Wali</h2>
                            <div className="crud-tabs" role="tablist" aria-label="Mode data">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={!isTrash}
                                    className={`crud-tab${!isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'all', page: 1 }, true)}
                                >
                                    Semua <span className="db-badge gray">{stats.total}</span>
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={isTrash}
                                    className={`crud-tab${isTrash ? ' active' : ''}`}
                                    onClick={() => visit({ mode: 'trash', page: 1 }, true)}
                                >
                                    <Trash2 size={13} /> Sampah <span className="db-badge gray">{stats.trashed}</span>
                                </button>
                            </div>
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
                                                <small className="db-hint">{row.nim ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    { key: 'advisor', label: 'Dosen PA', render: (row) => row.advisor },
                                    { key: 'year', label: 'Tahun', render: (row) => row.year },
                                    {
                                        key: 'period', label: 'Rentang',
                                        render: (row) => `${row.startDate ?? '-'} → ${row.endDate ?? '-'}`,
                                    },
                                    {
                                        key: 'is_active', label: 'Status',
                                        render: (row) => (
                                            can.toggle && !isTrash ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title="Ubah status">
                                                    <input
                                                        type="checkbox"
                                                        checked={row.isActive}
                                                        onChange={() => toggleActive(row)}
                                                        style={{ width: 17, height: 17, accentColor: 'var(--db-brand)', cursor: 'pointer' }}
                                                    />
                                                    <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                                </label>
                                            ) : (
                                                <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>{row.isActive ? 'Aktif' : 'Nonaktif'}</span>
                                            )
                                        ),
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
                                searchPlaceholder="Cari nama mahasiswa…"
                                filterBar={(
                                    <>
                                        <input
                                            className="db-input"
                                            style={{ maxWidth: 150 }}
                                            value={nim}
                                            placeholder="NIM…"
                                            onChange={(e) => setNim(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); visitNim(); } }}
                                            aria-label="Filter NIM"
                                        />
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
                                            value={filters.program}
                                            onChange={(e) => visit({ program: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prodi"
                                        >
                                            <option value="">Semua prodi</option>
                                            {programOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(e) => visit({ is_active: e.target.value, page: 1 }, true)}
                                            aria-label="Filter status"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: isTrash ? 'bulk-force' : 'bulk-delete' })}
                                bulkLabel={isTrash ? 'Hapus permanen terpilih' : 'Hapus terpilih'}
                                canRestore={isTrash && can.restore}
                                onBulkRestore={isTrash ? () => setPending({ kind: 'bulk-restore' }) : undefined}
                                canUpdate={false}
                                onRestoreRow={isTrash && can.restore ? (row) => setPending({ kind: 'restore', row }) : undefined}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: isTrash ? 'force' : 'delete', row }) : undefined}
                                showActions={(!isTrash && (can.update || can.view || can.delete)) || can.restore || can.delete}
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {!isTrash && can.update && (
                                            <a className="db-btn ghost sm" href={row.editUrl ?? urls.index} title="Ubah">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Tugaskan"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={!isTrash ? (
                                    <>
                                        {can.create && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                                <Upload size={14} /> Import
                                            </button>
                                        )}
                                        {can.transfer && selected.length > 0 && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => setTransferOpen(true)}>
                                                <ArrowRightLeft size={14} /> Pindahkan ({selected.length})
                                            </button>
                                        )}
                                    </>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada penugasan yang cocok dengan filter.'}
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
                    danger={modal.danger}
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />

                {transferOpen && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setTransferOpen(false)} role="dialog" aria-modal="true" aria-label="Pindahkan bimbingan">
                            <div className="db-modal" style={{ width: 'min(30rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Pindahkan {selected.length} bimbingan</h3>
                                <p>Bentrok dengan penugasan aktif dilewati otomatis dan dilaporkan.</p>
                                <div style={{ marginTop: 12 }}>
                                    <AsyncSelect
                                        label="Dosen PA tujuan"
                                        required
                                        fetchUrl={urls.searchLecturers}
                                        value={transferTo}
                                        onChange={setTransferTo}
                                        placeholder="Ketik NIDN/nama dosen…"
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setTransferOpen(false)} disabled={processing}>
                                        Batal
                                    </button>
                                    <button className="db-btn primary" type="button" onClick={confirmTransfer} disabled={!transferTo || processing}>
                                        {processing ? 'Memproses…' : 'Pindahkan'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <ImportModal
                    open={importOpen}
                    title="Impor Dosen Wali"
                    description="Mahasiswa by NIM, dosen by NIDN/NIP, tahun by kode. Bentrok penugasan aktif membuat baris ditolak."
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
