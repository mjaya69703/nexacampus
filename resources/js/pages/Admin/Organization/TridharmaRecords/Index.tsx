// Daftar portofolio Tridharma (penelitian, pengabdian, publikasi) — admin kepegawaian.
import { Head, router } from '@inertiajs/react';
import { Award, BadgeCheck, CheckCircle2, Clock3, FileText, FolderOpen, Plus } from 'lucide-react';
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
    ownerName: string;
    ownerEmail: string | null;
    type: string;
    typeLabel: string;
    title: string;
    scheme: string | null;
    fundingAmount: number;
    fundingLabel: string;
    milestonesCount: number;
    completedMilestones: number;
    outputsCount: number;
    attachmentsCount: number;
    status: string;
    statusLabel: string;
    isVerified: boolean;
    createdAt: string | null;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; view: boolean };
    stats: { total: number; inApproval: number; verified: number; completed: number };
    data: {
        rows: Row[];
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: {
        q: string;
        type: string;
        status: string;
        is_verified: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    types: Option[];
    statuses: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const statusTone = (status: string) => {
    if (['approved', 'active', 'completed'].includes(status)) return 'green';
    if (status === 'rejected') return 'red';
    if (['in_approval', 'submitted'].includes(status)) return 'gold';
    if (status === 'archived') return 'gray';
    return 'gray';
};

export default function TridharmaRecordsIndex({ shell, can, stats, data, filters, types, statuses, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.type !== '' ? 1 : 0) + (filters.status !== '' ? 1 : 0) + (filters.is_verified !== '' ? 1 : 0);

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
        visit({ type: '', status: '', is_verified: '', page: 1 }, true, '');
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus record Tridharma terpilih?' : 'Hapus record Tridharma?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} record Tridharma akan dipindahkan ke tempat sampah.`;
        return `"${pending.row.title}" milik ${pending.row.ownerName} akan dipindahkan ke tempat sampah.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Tridharma · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Kepegawaian"
                        title="Daftar Kegiatan Tri Dharma & Publikasi"
                        description="Kelola portofolio penelitian, pengabdian kepada masyarakat, publikasi ilmiah, luaran hak kekayaan intelektual (HKI), hingga bukti pendanaan (evidence) dosen dan pegawai."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Kegiatan Baru</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik Tridharma"
                        items={[
                            { label: 'Total Kegiatan', value: stats.total, icon: Award },
                            { label: 'Menunggu Approval', value: stats.inApproval, icon: Clock3, tone: 'gold' },
                            { label: 'Sudah Terverifikasi', value: stats.verified, icon: BadgeCheck, tone: 'green' },
                            { label: 'Status Selesai', value: stats.completed, icon: CheckCircle2, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Rekapitulasi Tri Dharma</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Judul & Owner',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">
                                                    {row.ownerName}{row.scheme ? ` · ${row.scheme}` : ''}
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'type',
                                        label: 'Tipe',
                                        sortable: true,
                                        render: (row) => <span className="db-badge gray" style={{ textTransform: 'none', letterSpacing: 0 }}>{row.typeLabel}</span>,
                                    },
                                    {
                                        key: 'funding_amount',
                                        label: 'Dana',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => <span style={{ whiteSpace: 'nowrap' }}>{row.fundingLabel}</span>,
                                    },
                                    {
                                        key: 'milestones',
                                        label: 'Milestone',
                                        render: (row) => (
                                            <span className="db-hint" style={{ marginTop: 0 }}>
                                                {row.milestonesCount === 0 ? '-' : `${row.completedMilestones} / ${row.milestonesCount}`}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'outputs_count',
                                        label: 'Output',
                                        sortable: true,
                                        render: (row) => (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5, marginTop: 0 }}>
                                                <FolderOpen size={13} /> {row.outputsCount}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'attachments_count',
                                        label: 'Lampiran',
                                        sortable: true,
                                        render: (row) => (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5, marginTop: 0 }}>
                                                <FileText size={13} /> {row.attachmentsCount}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ whiteSpace: 'nowrap' }}>
                                                <span className={`db-badge ${statusTone(row.status)}`}>{row.statusLabel}</span>
                                                <small className="db-hint" style={{ display: 'block' }}>
                                                    {row.isVerified ? 'Terverifikasi' : 'Belum verifikasi'}
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'created_at',
                                        label: 'Dibuat',
                                        sortable: true,
                                        render: (row) => <span className="db-hint" style={{ marginTop: 0 }}>{row.createdAt ?? '-'}</span>,
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
                                searchPlaceholder="Cari judul, skema, sumber dana, atau nama dosen…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(event) => visit({ type: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tipe Tridharma"
                                        >
                                            <option value="">Semua tipe</option>
                                            {types.map((type) => (
                                                <option key={type.id} value={type.id}>{type.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status Tridharma"
                                        >
                                            <option value="">Semua status</option>
                                            {statuses.map((status) => (
                                                <option key={status.id} value={status.id}>{status.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_verified}
                                            onChange={(event) => visit({ is_verified: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status verifikasi"
                                        >
                                            <option value="">Semua verifikasi</option>
                                            <option value="1">Terverifikasi</option>
                                            <option value="0">Belum verifikasi</option>
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
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                customActions={can.view ? (row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Lihat detail kegiatan">
                                        Detail
                                    </a>
                                ) : undefined}
                                showActions={can.view || can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada record Tridharma yang cocok dengan filter."
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
