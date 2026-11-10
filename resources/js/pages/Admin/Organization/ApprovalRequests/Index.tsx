// Daftar permohonan approval (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Clock3, FileSignature, Plus, XCircle } from 'lucide-react';
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
    subject: string;
    reference: string | null;
    templateName: string;
    templateCode: string | null;
    requesterName: string;
    status: string;
    statusLabel: string;
    currentStep: number | null;
    submittedAt: string | null;
    createdAt: string | null;
    showUrl: string;
    deleteUrl: string;
    canDelete: boolean;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; in_progress: number; approved: number; rejected: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        status: string;
        template_id: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: {
        statuses: Option[];
        templates: Option[];
    };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function ApprovalRequestsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = [filters.status, filters.template_id].filter((value) => value !== '').length;

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
        visit({ status: '', template_id: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
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
            return;
        }

        router.post(urls.bulkDestroy, { ids: selected }, { preserveScroll: true, onFinish: done });
    };

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus pengajuan terpilih?' : 'Hapus pengajuan approval?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} pengajuan akan dihapus. Hanya pengajuan berstatus draft, dibatalkan, atau ditolak yang diproses.`;
        return `Pengajuan "${pending.row.subject}" akan dihapus dari riwayat.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Approval Request · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileSignature}
                        eyebrow="Kepegawaian"
                        title="Permohonan Approval"
                        description="Pantau seluruh permohonan persetujuan lintas modul, lihat posisi langkah saat ini, dan proses keputusan langsung dari halaman detail."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Buat Permohonan</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik permohonan approval"
                        items={[
                            { label: 'Total Permohonan', value: stats.total, icon: FileSignature },
                            { label: 'Dalam Proses', value: stats.in_progress, icon: Clock3, tone: 'gold' },
                            { label: 'Disetujui', value: stats.approved, icon: CheckCircle2, tone: 'green' },
                            { label: 'Ditolak / Batal', value: stats.rejected, icon: XCircle, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Permohonan</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'subject',
                                        label: 'Permohonan',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.subject}</b>
                                                <small className="db-hint">Referensi: {row.reference ?? '-'}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'template_name',
                                        label: 'Template',
                                        render: (row) => (
                                            <span>
                                                <span className="db-badge gray">{row.templateName}</span>
                                                {row.templateCode && <small className="db-hint" style={{ display: 'block' }}>{row.templateCode}</small>}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'requester_name',
                                        label: 'Pemohon',
                                        render: (row) => <span className="db-hint">{row.requesterName}</span>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.status === 'approved' ? 'green' : row.status === 'rejected' || row.status === 'cancelled' ? 'red' : 'gray'}`}>
                                                {row.statusLabel}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'current_step_order',
                                        label: 'Langkah',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{row.currentStep ?? '-'}</b>,
                                    },
                                    {
                                        key: 'submitted_at',
                                        label: 'Diajukan',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.submittedAt ?? row.createdAt ?? '-'}</span>,
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
                                searchPlaceholder="Cari subjek, referensi, template, atau pemohon…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status permohonan"
                                        >
                                            <option value="">Semua status</option>
                                            {options.statuses.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.template_id}
                                            onChange={(event) => visit({ template_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter template"
                                        >
                                            <option value="">Semua template</option>
                                            {options.templates.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={can.delete}
                                onBulkDelete={() => setPending({ kind: 'bulk' })}
                                bulkLabel="Hapus terpilih"
                                canUpdate={can.view}
                                editUrl={(row) => row.showUrl}
                                onDeleteRow={can.delete ? (row) => {
                                    if (row.canDelete) {
                                        setPending({ kind: 'delete', row });
                                    }
                                } : undefined}
                                showActions={can.view || can.delete}
                                canCreate={false}
                                emptyText="Belum ada permohonan approval yang cocok dengan filter."
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
