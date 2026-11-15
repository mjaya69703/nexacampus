// Daftar riwayat sertifikasi & pelatihan pegawai (admin kepegawaian).
import { Head, router } from '@inertiajs/react';
import { Award, BadgeCheck, Clock3, FileText, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    userName: string;
    userEmail: string | null;
    type: string;
    typeLabel: string;
    title: string;
    organizer: string | null;
    credentialNumber: string | null;
    startDate: string | null;
    endDate: string | null;
    expiresAt: string | null;
    cost: number | null;
    attachmentsCount: number;
    isVerified: boolean;
    verifierName: string | null;
    verifiedAt: string | null;
    verificationNotes: string | null;
    createdAt: string | null;
    editUrl: string;
    verifyUrl: string;
    unverifyUrl: string;
    deleteUrl: string;
};

type Option = { id: string; label: string };

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; verified: number; pending: number };
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
        is_verified: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    types: Option[];
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

const formatCurrency = (value: number | null) => {
    if (value === null) return '-';
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
};

export default function UserDevelopmentRecordsIndex({ shell, can, stats, data, filters, types, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const activeFilterCount = (filters.type !== '' ? 1 : 0) + (filters.is_verified !== '' ? 1 : 0);

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
        visit({ type: '', is_verified: '', page: 1 }, true, '');
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const verifyToggle = (row: Row) => {
        router.post(row.isVerified ? row.unverifyUrl : row.verifyUrl, {}, { preserveScroll: true });
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

    const pendingTitle = pending?.kind === 'bulk' ? 'Hapus riwayat terpilih?' : 'Hapus riwayat pengembangan?';

    const pendingMessage = (() => {
        if (!pending) return '';
        if (pending.kind === 'bulk') return `${selected.length} riwayat sertifikasi/pelatihan akan dihapus.`;
        return `"${pending.row.title}" milik ${pending.row.userName} akan dipindahkan ke tempat sampah.`;
    })();

    return (
        <AdminShell shell={shell}>
            <Head title={`Sertifikasi & Pelatihan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Kepegawaian"
                        title="Sertifikasi & Pelatihan Pegawai"
                        description="Pantau rekam jejak pengembangan kompetensi, sertifikasi profesi, pelatihan eksternal, workshop, dan seminar dari seluruh dosen maupun staf tenaga kependidikan."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Riwayat</a>
                        ) : undefined}
                    />

                    <OrgStats
                        label="Statistik sertifikasi & pelatihan"
                        items={[
                            { label: 'Total Riwayat', value: stats.total, icon: Award },
                            { label: 'Terverifikasi', value: stats.verified, icon: BadgeCheck, tone: 'green' },
                            { label: 'Menunggu Verifikasi', value: stats.pending, icon: Clock3, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Direktori Pengembangan Kompetensi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Pegawai & Riwayat',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                <small className="db-hint">
                                                    {row.userName}
                                                    {row.organizer ? ` · ${row.organizer}` : ''}
                                                    {row.cost !== null ? ` · ${formatCurrency(row.cost)}` : ''}
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'type',
                                        label: 'Tipe',
                                        sortable: true,
                                        render: (row) => (
                                            <span className="db-badge gray" style={{ textTransform: 'none', letterSpacing: 0 }}>
                                                {row.typeLabel}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'start_date',
                                        label: 'Tanggal',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ whiteSpace: 'nowrap' }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.startDate ?? '-'}</b>
                                                <small className="db-hint">
                                                    {row.endDate ? `s.d. ${row.endDate}` : 'Sehari / berlangsung'}
                                                </small>
                                                {row.expiresAt && (
                                                    <small className="db-hint" style={{ display: 'block' }}>
                                                        Berlaku s.d. {row.expiresAt}
                                                    </small>
                                                )}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'attachments_count',
                                        label: 'Lampiran',
                                        sortable: true,
                                        align: 'right',
                                        render: (row) => (
                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                <FileText size={13} /> {row.attachmentsCount}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'is_verified',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ whiteSpace: 'nowrap' }}>
                                                <span className={`db-badge ${row.isVerified ? 'green' : 'amber'}`}>
                                                    {row.isVerified ? 'Terverifikasi' : 'Menunggu'}
                                                </span>
                                                {row.isVerified && (row.verifierName || row.verifiedAt) && (
                                                    <small className="db-hint" style={{ display: 'block' }}>
                                                        {row.verifierName ?? '-'}{row.verifiedAt ? ` · ${row.verifiedAt}` : ''}
                                                    </small>
                                                )}
                                            </span>
                                        ),
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
                                searchPlaceholder="Cari judul, pegawai, penyelenggara, atau nomor kredensial…"
                                filterBar={(
                                    <FilterPanel count={activeFilterCount} onReset={resetFilters}>
                                        <select
                                            className="db-input"
                                            value={filters.type}
                                            onChange={(event) => visit({ type: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tipe pengembangan"
                                        >
                                            <option value="">Semua tipe</option>
                                            {types.map((type) => (
                                                <option key={type.id} value={type.id}>{type.label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_verified}
                                            onChange={(event) => visit({ is_verified: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status verifikasi"
                                        >
                                            <option value="">Semua status</option>
                                            <option value="1">Terverifikasi</option>
                                            <option value="0">Menunggu verifikasi</option>
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
                                customActions={can.update ? (row) => (
                                    <button
                                        className={`db-btn ${row.isVerified ? 'ghost' : 'primary'} sm`}
                                        type="button"
                                        onClick={() => verifyToggle(row)}
                                        title={row.isVerified ? 'Kembalikan ke menunggu verifikasi' : 'Verifikasi riwayat'}
                                    >
                                        {row.isVerified ? 'Batalkan' : 'Verifikasi'}
                                    </button>
                                ) : undefined}
                                showActions={can.update || can.delete}
                                canCreate={false}
                                emptyText="Belum ada riwayat sertifikasi/pelatihan yang cocok dengan filter."
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
