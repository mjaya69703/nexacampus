// Daftar prestasi mahasiswa (admin).
import { Head, router } from '@inertiajs/react';
import { Award, CheckCircle2, Clock3, Eye, FileText, Plus, Star, Trophy } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { buildQuery, StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number;
    no: number;
    title: string;
    student: string;
    nim: string | null;
    program: string | null;
    organization: string | null;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    level: string;
    levelLabel: string;
    levelTone: string;
    rankLabel: string;
    achievedAt: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    isFeatured: boolean;
    hasCertificate: boolean;
    certificateUrl: string | null;
    showUrl: string;
    editUrl: string;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean; delete: boolean; verify: boolean };
    stats: { total: number; pending: number; verified: number; featured: number };
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: {
        q: string;
        category: string;
        level: string;
        status: string;
        is_featured: string;
        sort: string;
        direction: 'asc' | 'desc';
        perPage: number;
    };
    options: { categories: Record<string, string>; levels: Record<string, string>; statuses: Record<string, string> };
    urls: { index: string; create: string; bulkDestroy: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function AchievementsIndex({ shell, can, stats, data, filters, options, urls }: Props) {
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

    const activeFilterCount = [filters.category, filters.level, filters.status, filters.is_featured]
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
            <Head title={`Prestasi Mahasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Trophy}
                        eyebrow="Kemahasiswaan"
                        title="Prestasi Mahasiswa"
                        description="Catat capaian mahasiswa, unggah sertifikat, dan verifikasi sebelum tampil di galeri publik."
                        actions={can.create ? (
                            <a className="db-btn light" href={urls.create}><Plus size={15} /> Tambah Prestasi</a>
                        ) : undefined}
                    />

                    <StudentAffairsStats
                        label="Statistik prestasi mahasiswa"
                        items={[
                            { label: 'Total prestasi', value: stats.total, icon: Trophy },
                            { label: 'Menunggu verifikasi', value: stats.pending, icon: Clock3, tone: 'gold' },
                            { label: 'Terverifikasi', value: stats.verified, icon: CheckCircle2, tone: 'green' },
                            { label: 'Sorotan publik', value: stats.featured, icon: Star, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Prestasi</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'title',
                                        label: 'Prestasi',
                                        sortable: true,
                                        render: (row) => (
                                            <span style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                                <span className={`db-stat-icon ${row.isFeatured ? 'gold' : ''}`.trim()} style={{ width: 36, height: 36, borderRadius: 10 }}>
                                                    {row.isFeatured ? <Star size={16} /> : <Award size={16} />}
                                                </span>
                                                <span>
                                                    <a href={row.showUrl} style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13, fontWeight: 700, textDecoration: 'none' }}>
                                                        {row.title}
                                                    </a>
                                                    <small className="db-hint">
                                                        {row.organization || 'Individu'}{row.rankLabel ? ` · ${row.rankLabel}` : ''}
                                                    </small>
                                                </span>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.student}</b>
                                                <small className="db-hint">
                                                    {row.nim || '—'}{row.program ? ` · ${row.program}` : ''}
                                                </small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${toneClass(row.categoryTone)}`}>{row.categoryLabel}</span>,
                                    },
                                    {
                                        key: 'level',
                                        label: 'Tingkat',
                                        sortable: true,
                                        render: (row) => <span className={`db-badge ${toneClass(row.levelTone)}`}>{row.levelLabel}</span>,
                                    },
                                    {
                                        key: 'achieved_at',
                                        label: 'Tanggal',
                                        sortable: true,
                                        render: (row) => <span className="db-hint">{row.achievedAt || '—'}</span>,
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${toneClass(row.statusTone)}`}>{row.statusLabel}</span>
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
                                searchPlaceholder="Cari judul, mahasiswa, NIM, atau penyelenggara…"
                                filterBar={(
                                    <FilterPanel
                                        count={activeFilterCount}
                                        onReset={() => visit({ category: '', level: '', status: '', is_featured: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.category}
                                            onChange={(event) => visit({ category: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori prestasi"
                                        >
                                            <option value="">Semua kategori</option>
                                            {Object.entries(options.categories).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.level}
                                            onChange={(event) => visit({ level: event.target.value, page: 1 }, true)}
                                            aria-label="Filter tingkat prestasi"
                                        >
                                            <option value="">Semua tingkat</option>
                                            {Object.entries(options.levels).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.status}
                                            onChange={(event) => visit({ status: event.target.value, page: 1 }, true)}
                                            aria-label="Filter status prestasi"
                                        >
                                            <option value="">Semua status</option>
                                            {Object.entries(options.statuses).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                        <select
                                            className="db-input"
                                            value={filters.is_featured}
                                            onChange={(event) => visit({ is_featured: event.target.value, page: 1 }, true)}
                                            aria-label="Filter sorotan publik"
                                        >
                                            <option value="">Semua prestasi</option>
                                            <option value="1">Sorotan publik</option>
                                            <option value="0">Bukan sorotan</option>
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
                                    <>
                                        {row.certificateUrl && (
                                            <a className="db-btn ghost sm" href={row.certificateUrl} title="Unduh sertifikat">
                                                <FileText size={13} />
                                            </a>
                                        )}
                                        <a className="db-btn ghost sm" href={row.showUrl} title="Detail prestasi">
                                            <Eye size={13} />
                                        </a>
                                    </>
                                )}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada prestasi yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus prestasi terpilih?' : 'Hapus prestasi?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} prestasi akan dihapus beserta sertifikatnya.`
                        : pending?.kind === 'delete'
                            ? `"${pending.row.title}" akan dihapus beserta sertifikatnya.`
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
