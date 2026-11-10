// Daftar template alur persetujuan (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, CirclePause, GitBranch, Layers, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Option = { id: string; label: string };

type Row = {
    id: number;
    no: number;
    name: string;
    code: string;
    module: string;
    stepsCount: number;
    isActive: boolean;
    createdAt: string | null;
    editUrl: string;
    toggleUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; inactive: number; modules: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        module: string;
        is_active: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: { modules: Option[] };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function ApprovalTemplatesIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.module, filters.is_active].filter((value) => value !== '').length;

    const visit = (overrides: Record<string, string | number | undefined>, clear = false, searchValue = search) => {
        router.get(urls.index, buildQuery(filters, searchValue, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clear) setSelected([]);
            },
        });
    };

    const resetFilters = () => {
        setSearch('');
        visit({ module: '', is_active: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
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

        if (pending.kind === 'delete') {
            router.delete(pending.row.deleteUrl, { preserveScroll: true, onFinish: done });
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus template terpilih?' : 'Hapus template approval?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} template approval akan dipindahkan ke tempat sampah.`;
        return `Template "${pending.row.name}" akan dipindahkan ke tempat sampah. Template yang sudah pernah dipakai pada pengajuan tidak dapat dihapus.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Template Approval · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GitBranch}
                        eyebrow="Kepegawaian"
                        title="Template Alur Persetujuan"
                        description="Konfigurasi alur verifikasi berjenjang untuk cuti, penugasan, dan pengajuan lain. Setiap langkah dapat ditargetkan ke role, permission, jabatan, atau unit kerja."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Template</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik template approval"
                        items={[
                            { label: 'Total Template', value: stats.total, icon: GitBranch },
                            { label: 'Template Aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Template Nonaktif', value: stats.inactive, icon: CirclePause, tone: 'red' },
                            { label: 'Modul Terdaftar', value: stats.modules, icon: Layers, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Template Approval</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'name',
                                        label: 'Template',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.name}</b>
                                                <small className="db-hint">Kode: {row.code}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'module',
                                        label: 'Modul',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray">{row.module}</span>,
                                    },
                                    {
                                        key: 'steps_count',
                                        label: 'Langkah',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (
                                            <b style={{ color: row.stepsCount > 0 ? 'var(--db-brand)' : 'var(--db-muted)', fontSize: 13 }}>
                                                {row.stepsCount} langkah
                                            </b>
                                        ),
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Aktif',
                                        sortable: true,
                                        render: (row) => (
                                            can.update ? (
                                                <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, cursor: 'pointer' }} title={row.isActive ? 'Nonaktifkan' : 'Aktifkan'}>
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
                                    {
                                        key: 'created_at',
                                        label: 'Dibuat',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.createdAt ?? '-'}</span>,
                                    },
                                ]}
                                rows={data.rows}
                                page={{ currentPage: data.currentPage, lastPage: data.lastPage, perPage: data.perPage, total: data.total }}
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={(key) => visit({
                                    sort: key,
                                    direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc',
                                    page: 1,
                                })}
                                search={search}
                                onSearchChange={setSearch}
                                onSearchSubmit={() => visit({ page: 1 }, true)}
                                searchPlaceholder="Cari nama, kode, modul, atau deskripsi template…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.module}
                                            onChange={(event) => visit({ module: event.target.value, page: 1 }, true)}
                                            aria-label="Filter modul template"
                                        >
                                            <option value="">Semua modul</option>
                                            {options.modules.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status template"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Aktif</option>
                                            <option value="0">Nonaktif</option>
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.update}
                                editUrl={(row) => row.editUrl}
                                onDeleteRow={can.delete && pending?.kind !== 'delete' ? (row) => {
                                    if (row.canDelete) {
                                        setPending({ kind: 'delete', row });
                                    }
                                } : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada template approval yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pendingTitle}
                    message={pendingMessage}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirmPending}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
