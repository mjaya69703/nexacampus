// Daftar kurikulum — kit CRUD + duplikat.
import { Head, router, useForm } from '@inertiajs/react';
import { Copy, Eye, GraduationCap, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { ImportModal, ImportResult, ImportResultBanner } from '../../../../components/Shared/Crud/ImportModal';
import { TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; name: string; code: string | null; program: string | null;
    startYear: number | null; endYear: number | null; courseCount: number;
    isActive: boolean; createdAt: string | null;
    showUrl: string; editUrl: string | null; deleteUrl: string;
    restoreUrl: string; forceUrl: string; toggleUrl: string; duplicateUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean; restore: boolean; toggle: boolean; duplicate: boolean };
    stats: { total: number; active: number; items: number; trashed: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; program: string; is_active: string;
        sort: string; direction: 'asc' | 'desc'; mode: 'all' | 'trash'; perPage: number;
    };
    programOptions: { id: number; name: string }[];
    importResult: ImportResult;
    urls: {
        index: string; create: string; export: string; exportPdf: string; importTemplate: string; importSubmit: string;
        bulkDestroy: string; bulkRestore: string; bulkForceDestroy: string;
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

export default function CurriculumIndex({ shell, can, stats, data, filters, programOptions, importResult, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [duplicateRow, setDuplicateRow] = useState<Row | null>(null);
    const isTrash = filters.mode === 'trash';

    const duplicateForm = useForm({ name: '', code: '', start_year: '', end_year: '' });

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

    const openDuplicate = (row: Row) => {
        duplicateForm.setData({
            name: `${row.name} (Salinan)`,
            code: row.code ? `${row.code}-C` : '',
            start_year: row.startYear ? String(row.startYear + 1) : '',
            end_year: row.endYear ? String(row.endYear + 1) : '',
        });
        duplicateForm.clearErrors();
        setDuplicateRow(row);
    };

    const confirmDuplicate = () => {
        if (!duplicateRow) return;
        duplicateForm.post(duplicateRow.duplicateUrl, {
            preserveScroll: true,
            onSuccess: () => setDuplicateRow(null),
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
            return { title: 'Pulihkan kurikulum?', message: `"${pending.row.name}" kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'bulk-restore') {
            return { title: 'Pulihkan terpilih?', message: `${selected.length} kurikulum kembali aktif.`, confirm: 'Ya, pulihkan', danger: false };
        }
        if (pending.kind === 'force') {
            return { title: 'Hapus permanen?', message: `"${pending.row.name}" dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-force') {
            return { title: 'Hapus permanen terpilih?', message: `${selected.length} kurikulum dihapus selamanya.`, confirm: 'Ya, hapus permanen', danger: true };
        }
        if (pending.kind === 'bulk-delete') {
            return { title: 'Hapus terpilih?', message: `${selected.length} kurikulum dipindah ke sampah. Yang masih punya MK akan dilewati.`, confirm: 'Ya, hapus', danger: true };
        }
        return { title: 'Hapus kurikulum?', message: `"${pending.row.name}" dipindah ke sampah.`, confirm: 'Ya, hapus', danger: true };
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar Kurikulum · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title="Kurikulum"
                        description="Susunan MK per semester per prodi. Duplikat untuk tahun baru tanpa menyusun ulang."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kurikulum</a>
                        ) : undefined}
                    />

                    <ImportResultBanner
                        result={importResult}
                        successText={(n) => `${n} kurikulum berhasil diimpor. Susun MK-nya dari halaman edit.`}
                        failText={(n) => `Impor dibatalkan — ${n} baris bermasalah, tidak ada data yang disimpan.`}
                    />

                    <section className="db-stats" aria-label="Statistik kurikulum">
                        <div className="db-stat">
                            <span className="db-stat-icon"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.total}</span><span className="db-stat-label">Total kurikulum</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.active}</span><span className="db-stat-label">Aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><GraduationCap size={20} /></span>
                            <div><span className="db-stat-num">{stats.items}</span><span className="db-stat-label">Baris MK</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><Trash2 size={20} /></span>
                            <div><span className="db-stat-num">{stats.trashed}</span><span className="db-stat-label">Di sampah</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Kurikulum</h2>
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
                                        key: 'name', label: 'Kurikulum', sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">{row.code ?? '-'} · {row.program ?? '-'} · {row.startYear ?? '-'}–{row.endYear ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'courses', label: 'MK', align: 'right',
                                        render: (row) => <span className="db-badge green">{row.courseCount} MK</span>,
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
                                searchPlaceholder="Cari nama, kode…"
                                filterBar={(
                                    <>
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
                                showActions
                                customActions={(row) => (
                                    <>
                                        {can.view && (
                                            <a className="db-btn ghost sm" href={row.showUrl} title="Detail">
                                                <Eye size={13} />
                                            </a>
                                        )}
                                        {!isTrash && can.update && row.editUrl && (
                                            <a className="db-btn ghost sm" href={row.editUrl} title="Ubah + kelola MK">
                                                <Pencil size={13} />
                                            </a>
                                        )}
                                        {!isTrash && can.duplicate && (
                                            <button className="db-btn ghost sm" type="button" onClick={() => openDuplicate(row)} title="Duplikat">
                                                <Copy size={13} />
                                            </button>
                                        )}
                                    </>
                                )}
                                canCreate={false}
                                createLabel="Tambah Kurikulum"
                                exportHref={exportHref}
                                exportExtra={[
                                    { label: 'PDF laporan', href: urls.exportPdf },
                                ]}
                                extraActions={(!isTrash && can.create) ? (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setImportOpen(true)}>
                                        <Upload size={14} /> Import
                                    </button>
                                ) : undefined}
                                emptyText={isTrash ? 'Sampah kosong.' : 'Belum ada kurikulum yang cocok dengan filter.'}
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

                <ImportModal
                    open={importOpen}
                    title="Impor Kurikulum"
                    description="Hanya header (tanpa baris MK) — susun MK dari halaman edit, atau duplikat kurikulum lama. Prodi ditulis by kode."
                    templateUrl={urls.importTemplate}
                    templateLabel="Unduh template"
                    processing={processing}
                    onSubmit={submitImport}
                    onClose={() => setImportOpen(false)}
                />

                {duplicateRow && (
                    <div className="db-root">
                        <div className="db-modal-backdrop" onClick={() => setDuplicateRow(null)} role="dialog" aria-modal="true" aria-label="Duplikat kurikulum">
                            <div className="db-modal" style={{ width: 'min(30rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                                <h3>Duplikat “{duplicateRow.name}”</h3>
                                <p>Seluruh {duplicateRow.courseCount} baris MK ikut disalin (nonaktif).</p>
                                <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                                    <TextField
                                        label="Nama kurikulum baru"
                                        required
                                        value={duplicateForm.data.name}
                                        onChange={(e) => duplicateForm.setData('name', e.target.value)}
                                        error={duplicateForm.errors.name}
                                    />
                                    <div className="crud-grid">
                                        <TextField
                                            label="Kode baru"
                                            value={duplicateForm.data.code}
                                            onChange={(e) => duplicateForm.setData('code', e.target.value)}
                                            error={duplicateForm.errors.code}
                                        />
                                        <TextField
                                            label="Tahun mulai"
                                            type="number"
                                            value={duplicateForm.data.start_year}
                                            onChange={(e) => duplicateForm.setData('start_year', e.target.value)}
                                            error={duplicateForm.errors.start_year}
                                        />
                                    </div>
                                    <TextField
                                        label="Tahun selesai"
                                        type="number"
                                        value={duplicateForm.data.end_year}
                                        onChange={(e) => duplicateForm.setData('end_year', e.target.value)}
                                        error={duplicateForm.errors.end_year}
                                    />
                                </div>
                                <div className="db-modal-actions">
                                    <button className="db-btn ghost" type="button" onClick={() => setDuplicateRow(null)} disabled={duplicateForm.processing}>
                                        Batal
                                    </button>
                                    <button className="db-btn primary" type="button" onClick={confirmDuplicate} disabled={duplicateForm.processing}>
                                        {duplicateForm.processing ? 'Menduplikat…' : 'Duplikat'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AdminShell>
    );
}
