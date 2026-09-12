// Saldo deposit — pantau + catat refund / koreksi massal (paritas Blade).
import { Head, router, useForm } from '@inertiajs/react';
import { PiggyBank, Plus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { CrudTable } from '../../../../components/Shared/Crud/CrudTable';
import { FilterPanel } from '../../../../components/Shared/Crud/FilterPanel';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Row = {
    id: number; no: number; student: string; nim: string | null; program: string | null;
    balance: string; at: string | null;
};

type StudentOption = { id: number; label: string };

type Props = {
    shell: ShellProps;
    can: { record: boolean };
    stats: { students: number; balance: number; overpayment: number; refund: number };
    data: {
        rows: Row[];
        currentPage: number; lastPage: number; perPage: number; total: number;
    };
    filters: {
        q: string; nim: string; program: string;
        sort: string; direction: 'asc' | 'desc'; perPage: number;
    };
    programOptions: { id: number; name: string }[];
    urls: { index: string; record: string; export: string; searchStudents: string };
};

const money = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

function buildQuery(filters: Props['filters'], search: string, overrides: Record<string, string | number | undefined>) {
    const merged: Record<string, string | number> = { ...filters, q: search, ...overrides };
    return Object.fromEntries(
        Object.entries(merged).filter(([, v]) => v !== '' && v !== undefined),
    );
}

export default function StudentCreditIndex({ shell, can, stats, data, filters, programOptions, urls }: Props) {
    const [search, setSearch] = useState(filters.q);
    const [nim, setNim] = useState(filters.nim);
    const [selected, setSelected] = useState<number[]>([]);

    const recordForm = useForm({ transaction_type: 'refund', amount: '', notes: '' });
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<StudentOption[]>([]);
    const [searching, setSearching] = useState(false);
    const [picked, setPicked] = useState<StudentOption[]>([]);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(`${urls.searchStudents}?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                setOptions(json.options ?? []);
            } catch {
                setOptions([]);
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [query, urls.searchStudents]);

    const visit = (overrides: Record<string, string | number | undefined>, clearSelection = false) => {
        router.get(urls.index, buildQuery(filters, search, overrides), {
            preserveState: true,
            replace: true,
            onSuccess: () => {
                if (clearSelection) setSelected([]);
            },
        });
    };

    const activeFilterCount = [nim, filters.program]
        .filter((v) => v !== '' && v !== undefined && v !== null).length;

    const resetFilters = () => {
        setNim('');
        visit({ nim: '', program: '', page: 1 }, true);
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

    const togglePicked = (option: StudentOption) => {
        setPicked((prev) => (prev.some((p) => p.id === option.id) ? prev.filter((p) => p.id !== option.id) : [...prev, option]));
    };

    const submitRecord = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(urls.record, {
            student_profile_ids: picked.map((p) => p.id),
            transaction_type: recordForm.data.transaction_type,
            amount: recordForm.data.amount,
            notes: recordForm.data.notes,
        } as never, {
            preserveScroll: true,
            onSuccess: () => {
                setPicked([]);
                recordForm.reset();
            },
        });
    };

    const recordErrors = recordForm.errors as Record<string, string>;

    const exportHref = `${urls.export}?${new URLSearchParams(
        Object.entries(buildQuery(filters, search, {})).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    return (
        <AdminShell shell={shell}>
            <Head title={`Saldo Deposit · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={PiggyBank}
                        eyebrow="Keuangan"
                        title="Saldo Deposit"
                        description="Kelebihan bayar tercatat otomatis — refund keluar dan koreksi manual dari halaman ini."
                    />

                    <section className="db-stats" aria-label="Statistik deposit">
                        <div className="db-stat">
                            <span className="db-stat-icon"><PiggyBank size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.balance)}</span><span className="db-stat-label">Total saldo ({stats.students} mhs)</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Plus size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.overpayment)}</span><span className="db-stat-label">Kelebihan bayar</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><PiggyBank size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.refund)}</span><span className="db-stat-label">Total refund</span></div>
                        </div>
                    </section>

                    {can.record && (
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Plus size={16} /> Catat Transaksi</h2>
                            </div>
                            <div className="db-card-body">
                                <form onSubmit={submitRecord}>
                                    <span className="crud-label">Pilih Mahasiswa (bisa banyak) <span>*</span></span>
                                    <input
                                        className="db-input"
                                        style={{ marginBottom: 8 }}
                                        value={query}
                                        placeholder="Cari NIM, nama, atau email…"
                                        onChange={(e) => setQuery(e.target.value)}
                                        aria-label="Cari mahasiswa"
                                    />
                                    <div style={{ border: '1px solid var(--db-line)', borderRadius: 10, padding: 10, maxHeight: 200, overflowY: 'auto' }}>
                                        {searching && <span className="db-hint">Mencari…</span>}
                                        {!searching && options.length === 0 && <div className="db-hint" style={{ textAlign: 'center' }}>Mahasiswa tidak ditemukan.</div>}
                                        {options.map((option) => (
                                            <label key={option.id} style={{ display: 'block', padding: '4px 0', fontSize: 13 }}>
                                                <input
                                                    type="checkbox"
                                                    checked={picked.some((p) => p.id === option.id)}
                                                    onChange={() => togglePicked(option)}
                                                    style={{ marginRight: 8 }}
                                                />
                                                {option.label}
                                            </label>
                                        ))}
                                    </div>
                                    <div className="db-hint" style={{ marginTop: 6 }}>{picked.length} mahasiswa terpilih.</div>
                                    {picked.length > 0 && (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                            {picked.map((p) => (
                                                <span key={p.id} className="db-badge green">
                                                    {p.label}
                                                    <button
                                                        type="button"
                                                        onClick={() => setPicked((prev) => prev.filter((x) => x.id !== p.id))}
                                                        style={{ background: 'none', border: 0, cursor: 'pointer', marginLeft: 4 }}
                                                        aria-label={`Hapus ${p.label}`}
                                                    >
                                                        <X size={11} />
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    {(recordErrors.student_profile_ids) && (
                                        <div className="db-error">{recordErrors.student_profile_ids}</div>
                                    )}
                                    <div className="crud-grid" style={{ marginTop: 12 }}>
                                        <SelectField
                                            label="Jenis Transaksi"
                                            value={recordForm.data.transaction_type}
                                            onChange={(e) => recordForm.setData('transaction_type', e.target.value)}
                                            error={recordErrors.transaction_type}
                                        >
                                            <option value="refund">Refund Keluar (kurangi saldo)</option>
                                            <option value="manual_adjustment">Koreksi Manual</option>
                                        </SelectField>
                                        <TextField
                                            label="Nominal"
                                            required
                                            type="number"
                                            min={1}
                                            value={recordForm.data.amount}
                                            onChange={(e) => recordForm.setData('amount', e.target.value)}
                                            error={recordErrors.amount}
                                        />
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <label className="crud-label" htmlFor="credit-notes">Catatan</label>
                                            <textarea
                                                id="credit-notes"
                                                className="db-input"
                                                rows={2}
                                                value={recordForm.data.notes}
                                                onChange={(e) => recordForm.setData('notes', e.target.value)}
                                            />
                                            {recordErrors.notes && <div className="db-error">{recordErrors.notes}</div>}
                                        </div>
                                    </div>
                                    <div style={{ marginTop: 12 }}>
                                        <button type="submit" className="db-btn primary sm" disabled={recordForm.processing}>
                                            Catat Transaksi
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </section>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Tabel Saldo</h2>
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
                                        key: 'balance', label: 'Saldo', sortable: true, align: 'right',
                                        render: (row) => <b style={{ color: 'var(--db-heading)' }}>{row.balance}</b>,
                                    },
                                    { key: 'updated_at', label: 'Diperbarui', sortable: true, render: (row) => row.at ?? '-' },
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
                                            value={filters.program}
                                            onChange={(e) => visit({ program: e.target.value, page: 1 }, true)}
                                            aria-label="Filter prodi"
                                        >
                                            <option value="">Semua prodi</option>
                                            {programOptions.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                    </FilterPanel>
                                )}
                                selected={selected}
                                onToggle={toggle}
                                onToggleAll={toggleAll}
                                canDelete={false}
                                onBulkDelete={() => undefined}
                                canUpdate={false}
                                showActions={false}
                                canCreate={false}
                                createLabel="Deposit"
                                exportHref={exportHref}
                                emptyText="Tidak ada saldo nonzero yang cocok dengan filter."
                                onPage={(p) => visit({ page: p })}
                                perPage={filters.perPage}
                                onPerPageChange={(n) => visit({ perPage: n, page: 1 })}
                            />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
