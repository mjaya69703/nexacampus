// Poin kemahasiswaan: papan peringkat dan buku besar poin (admin).
import { Head, router, useForm } from '@inertiajs/react';
import { Award, Crown, Plus, Star, TrendingUp, Users } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect } from '../../../../components/Shared/Crud/AsyncSelect';
import type { AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { buildQuery, StudentAffairsStats, StudentAffairsModal, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LeaderRow = {
    rank: number;
    userId: number;
    name: string;
    nim: string | null;
    program: string | null;
    total: number;
};

type Row = {
    id: number;
    no: number;
    student: string;
    nim: string | null;
    program: string | null;
    points: number;
    category: string;
    categoryLabel: string;
    categoryTone: string;
    description: string;
    awardedAt: string | null;
    awardedBy: string | null;
    deleteUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; delete: boolean };
    stats: { totalPoints: number; participants: number; entries: number; thisMonth: number };
    leaderboard: LeaderRow[];
    data: { rows: Row[]; currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { q: string; category: string; sort: string; direction: 'asc' | 'desc'; perPage: number };
    options: { categories: Record<string, string> };
    urls: { index: string; store: string; bulkDestroy: string; searchStudents: string };
};

type Pending = { kind: 'delete'; row: Row } | { kind: 'bulk' } | null;

export default function PointsIndex({ shell, can, stats, leaderboard, data, filters, options, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [selected, setSelected] = useState<number[]>([]);
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);
    const [awardOpen, setAwardOpen] = useState(false);
    const [student, setStudent] = useState<AsyncOption | null>(null);

    const form = useForm({
        user_id: '',
        points: 10,
        category: 'manual',
        description: '',
        awarded_at: new Date().toISOString().slice(0, 10),
    });

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => { if (clearSelection) setSelected([]); },
        });
    };

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        const ids = data.rows.map((row) => row.id);
        setSelected((prev) => (ids.every((id) => prev.includes(id)) ? prev.filter((id) => !ids.includes(id)) : [...new Set([...prev, ...ids])]));
    };

    const confirmDelete = () => {
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

    const submitAward = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.store, {
            preserveScroll: true,
            onSuccess: () => {
                setAwardOpen(false);
                setStudent(null);
                form.reset();
            },
        });
    };

    const topThree = leaderboard.slice(0, 3);
    const rest = leaderboard.slice(3);

    return (
        <AdminShell shell={shell}>
            <Head title={`Poin & Leaderboard · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Star}
                        eyebrow="Kemahasiswaan"
                        title="Poin & Leaderboard"
                        description="Berikan poin manual, pantau peringkat mahasiswa, dan telusuri riwayat perolehan poin."
                        actions={can.create ? (
                            <button className="db-btn light" type="button" onClick={() => setAwardOpen(true)}>
                                <Plus size={15} /> Beri Poin
                            </button>
                        ) : undefined}
                    />

                    <StudentAffairsStats
                        label="Statistik poin kemahasiswaan"
                        items={[
                            { label: 'Total poin', value: stats.totalPoints, icon: Star, tone: 'gold' },
                            { label: 'Mahasiswa terlibat', value: stats.participants, icon: Users },
                            { label: 'Entri poin', value: stats.entries, icon: Award },
                            { label: 'Poin bulan ini', value: stats.thisMonth, icon: TrendingUp, tone: 'green' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Crown size={15} /> Papan Peringkat</h2>
                            <span className="db-badge gold">20 besar</span>
                        </div>
                        <div className="db-card-body">
                            {leaderboard.length === 0 ? (
                                <span className="db-hint">Belum ada poin yang tercatat.</span>
                            ) : (
                                <div className="db-stack">
                                    <div className="db-grid-3">
                                        {topThree.map((row) => (
                                            <article key={row.userId} className="db-fact" style={{ display: 'grid', gap: 8, borderColor: row.rank === 1 ? 'var(--db-gold, #d9a400)' : undefined }}>
                                                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                    <span className={`db-badge ${row.rank === 1 ? 'gold' : row.rank === 2 ? 'gray' : 'amber'}`}>#{row.rank}</span>
                                                    {row.rank === 1 && <Crown size={16} />}
                                                </div>
                                                <b style={{ color: 'var(--db-heading)', fontSize: 13.5 }}>{row.name}</b>
                                                <span className="db-hint">{row.nim || '—'}{row.program ? ` · ${row.program}` : ''}</span>
                                                <span className="db-stat-num" style={{ fontSize: 20 }}>{row.total} <small className="db-hint">poin</small></span>
                                            </article>
                                        ))}
                                    </div>

                                    {rest.length > 0 && (
                                        <div style={{ display: 'grid', gap: 6 }}>
                                            {rest.map((row) => (
                                                <div key={row.userId} className="db-fact wide" style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                                                    <span className="db-badge gray" style={{ minWidth: 34, justifyContent: 'center' }}>#{row.rank}</span>
                                                    <span style={{ flex: 1, minWidth: 0 }}>
                                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.name}</b>
                                                        <small className="db-hint">{row.nim || '—'}{row.program ? ` · ${row.program}` : ''}</small>
                                                    </span>
                                                    <span className="db-badge gold">{row.total} poin</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Buku Besar Poin</h2>
                        </div>
                        <div className="db-card-body">
                            <CrudTable<Row>
                                columns={[
                                    { key: 'id', label: 'No', render: (row) => row.no },
                                    {
                                        key: 'student',
                                        label: 'Mahasiswa',
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.student}</b>
                                                <small className="db-hint">{row.nim || '—'}{row.program ? ` · ${row.program}` : ''}</small>
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'points',
                                        label: 'Poin',
                                        sortable: true,
                                        render: (row) => (
                                            <span className={`db-badge ${row.points >= 0 ? 'green' : 'red'}`}>
                                                {row.points > 0 ? `+${row.points}` : row.points}
                                            </span>
                                        ),
                                    },
                                    {
                                        key: 'category',
                                        label: 'Kategori',
                                        render: (row) => <span className={`db-badge ${toneClass(row.categoryTone)}`}>{row.categoryLabel}</span>,
                                    },
                                    {
                                        key: 'description',
                                        label: 'Keterangan',
                                        render: (row) => <span className="db-hint">{row.description}</span>,
                                    },
                                    {
                                        key: 'awarded_at',
                                        label: 'Tanggal',
                                        sortable: true,
                                        render: (row) => (
                                            <span>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 12.5 }}>{row.awardedAt || '—'}</b>
                                                <small className="db-hint">oleh {row.awardedBy || 'sistem'}</small>
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
                                searchPlaceholder="Cari nama mahasiswa atau NIM…"
                                filterBar={(
                                    <FilterPanel
                                        count={filters.category !== '' ? 1 : 0}
                                        onReset={() => visit({ category: '', page: 1 }, true)}
                                    >
                                        <select
                                            className="db-input"
                                            value={filters.category}
                                            onChange={(event) => visit({ category: event.target.value, page: 1 }, true)}
                                            aria-label="Filter kategori poin"
                                        >
                                            <option value="">Semua kategori</option>
                                            {Object.entries(options.categories).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
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
                                canUpdate={false}
                                onDeleteRow={can.delete ? (row) => setPending({ kind: 'delete', row }) : undefined}
                                showActions
                                canCreate={false}
                                emptyText="Belum ada entri poin yang cocok dengan filter."
                                onPage={(page) => visit({ page })}
                                perPage={filters.perPage}
                                onPerPageChange={(perPage) => visit({ perPage, page: 1 })}
                            />
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending?.kind === 'bulk' ? 'Hapus entri poin terpilih?' : 'Hapus entri poin?'}
                    message={pending?.kind === 'bulk'
                        ? `${selected.length} entri poin akan dihapus dan total poin mahasiswa menyesuaikan.`
                        : pending?.kind === 'delete'
                            ? `Entri "${pending.row.description}" akan dihapus.`
                            : ''}
                    confirmLabel={pending?.kind === 'bulk' ? 'Ya, hapus semua' : 'Ya, hapus'}
                    danger
                    processing={processing}
                    onConfirm={confirmDelete}
                    onCancel={() => setPending(null)}
                />

                {awardOpen && (
                    <StudentAffairsModal
                        title="Beri Poin Mahasiswa"
                        description="Poin manual dapat bernilai negatif untuk koreksi. Satu entri hanya berlaku untuk satu mahasiswa."
                        onClose={() => setAwardOpen(false)}
                    >
                        <form onSubmit={submitAward} style={{ display: 'grid', gap: 12 }}>
                            <AsyncSelect
                                label="Mahasiswa"
                                required
                                fetchUrl={urls.searchStudents}
                                value={student}
                                onChange={(option) => {
                                    setStudent(option);
                                    form.setData('user_id', option ? String(option.id) : '');
                                }}
                                error={form.errors.user_id}
                                placeholder="Cari nama atau NIM…"
                            />
                            <TextField
                                label="Poin"
                                type="number"
                                required
                                value={form.data.points}
                                onChange={(event) => form.setData('points', Number(event.target.value))}
                                error={form.errors.points}
                                hint="Gunakan nilai negatif untuk mengurangi poin."
                            />
                            <SelectField
                                label="Kategori"
                                required
                                value={form.data.category}
                                onChange={(event) => form.setData('category', event.target.value)}
                                error={form.errors.category}
                            >
                                {Object.entries(options.categories).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </SelectField>
                            <TextField
                                label="Tanggal pemberian"
                                type="date"
                                value={form.data.awarded_at}
                                onChange={(event) => form.setData('awarded_at', event.target.value)}
                                error={form.errors.awarded_at}
                            />
                            <TextareaField
                                label="Keterangan"
                                required
                                rows={3}
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                                error={form.errors.description}
                                placeholder="Contoh: Kontribusi sebagai panitia seminar nasional."
                            />
                            <div className="crud-actions">
                                <button className="db-btn ghost" type="button" onClick={() => setAwardOpen(false)}>Batal</button>
                                <button className="db-btn primary" type="submit" disabled={form.processing}>Simpan Poin</button>
                            </div>
                        </form>
                    </StudentAffairsModal>
                )}
            </div>
        </AdminShell>
    );
}
