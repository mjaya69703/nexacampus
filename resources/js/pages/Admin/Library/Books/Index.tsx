// Daftar buku & eksemplar perpustakaan (admin).
import { Head, router } from '@inertiajs/react';
import { BookMarked, BookOpen, CheckCircle2, Eye, Library, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, LibraryStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    title: string;
    author: string | null;
    category: string | null;
    isbn: string | null;
    year: number | null;
    shelfLocation: string | null;
    copiesCount: number;
    availableCount: number;
    activeLoansCount: number;
    isActive: boolean;
    coverUrl: string | null;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean };
    stats: { total: number; active: number; copies: number; available: number; borrowed: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        category_id: string;
        is_active: string;
        availability: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    categories: Array<{ id: number; name: string }>;
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function BooksIndex({ shell, can, stats, data, filters, categories, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const activeFilterCount = [filters.category_id, filters.is_active, filters.availability]
        .filter((value) => value !== '').length;

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirm = () => {
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

    return (
        <AdminShell shell={shell}>
            <Head title={`Buku Perpustakaan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookOpen}
                        eyebrow="Perpustakaan"
                        title="Buku & Eksemplar"
                        description="Kelola judul koleksi, data bibliografi, lokasi rak, dan jumlah eksemplar yang dapat dipinjam."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Buku</a>
                        ) : undefined}
                    />

                    <LibraryStats
                        label="Statistik koleksi buku"
                        items={[
                            { label: 'Judul buku', value: stats.total, icon: BookOpen },
                            { label: 'Buku aktif', value: stats.active, icon: CheckCircle2, tone: 'green' },
                            { label: 'Total eksemplar', value: stats.copies, icon: Library },
                            { label: 'Tersedia', value: stats.available, icon: BookMarked, tone: 'gold' },
                            { label: 'Sedang dipinjam', value: stats.borrowed, icon: BookMarked, tone: 'red' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Buku</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Buku',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                {row.coverUrl ? (
                                                    <img
                                                        src={row.coverUrl}
                                                        alt=""
                                                        style={{ width: 34, height: 46, objectFit: 'cover', borderRadius: 6, border: '1px solid var(--db-line)' }}
                                                    />
                                                ) : (
                                                    <span className="db-stat-icon" style={{ width: 34, height: 46, borderRadius: 6 }}>
                                                        <BookOpen size={16} />
                                                    </span>
                                                )}
                                                <span>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{row.title}</b>
                                                    <small className="db-hint">
                                                        {row.author || 'Tanpa penulis'}
                                                        {row.isbn ? ` · ISBN ${row.isbn}` : ''}
                                                    </small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        render: (row) => <span className="db-badge gray">{row.category || 'Umum'}</span>,
                                    },
                                    {
                                        key: 'publication_year',
                                        label: 'Tahun',
                                        sortable: true,
                                        render: (row) => row.year ?? '—',
                                    },
                                    {
                                        key: 'copies',
                                        label: 'Eksemplar',
                                        align: 'right',
                                        render: (row) => (
                                            <span>
                                                <b style={{ color: 'var(--db-heading)' }}>{row.availableCount}</b>
                                                <span className="db-hint"> / {row.copiesCount} tersedia</span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'shelf_location',
                                        label: 'Rak',
                                        render: (row) => <span className="db-hint">{row.shelfLocation || '—'}</span>,
                                    },
                                    {
                                        key: 'is_active',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.isActive ? 'green' : 'gray'}`}>
                                                {row.isActive ? 'Aktif' : 'Nonaktif'}
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
                                searchPlaceholder="Cari judul, penulis, atau ISBN…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ category_id: '', is_active: '', availability: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.category_id}
                                            onChange={(event) => visit({ category_id: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori"
                                        >
                                            <option value="">Semua kategori</option>
                                            {categories.map((category) => (
                                                <option key={category.id} value={category.id}>{category.name}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.availability}
                                            onChange={(event) => visit({ availability: event.target.value, page: 1 }, true)}
                                            aria-label="Filter ketersediaan"
                                        >
                                            <option value="">Semua ketersediaan</option>
                                            <option value="available">Ada eksemplar tersedia</option>
                                            <option value="unavailable">Tidak tersedia</option>
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_active}
                                            onChange={(event) => visit({ is_active: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status buku"
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
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                customActions={(row) => (
                                    <a className="db-btn ghost sm" href={row.showUrl} title="Detail buku">
                                        <Eye size={13} />
                                    </a>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada buku yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus buku terpilih?' : 'Hapus buku?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} buku akan dihapus. Buku dengan transaksi aktif akan dilewati.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.title}" beserta eksemplarnya akan dihapus.`
                            : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
