// Manajemen saldo cuti pegawai — penyesuaian inline & daftar saldo terbaru.
import { Head, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarMinus,
    CalendarPlus,
    History,
    Layers,
    Search,
    SlidersHorizontal,
    UserRound,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { SelectField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import { OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type LeaveType = {
    id: number;
    name: string;
    code: string;
    defaultDaysPerYear: number;
};

type EmployeeOption = {
    id: number;
    name: string;
    employeeNumber: string | null;
    email: string | null;
};

type BalanceRow = {
    id: number;
    employeeName: string;
    employeeNumber: string | null;
    leaveTypeName: string;
    year: number;
    allocatedDays: number;
    pendingDays: number;
    usedDays: number;
    carriedOverDays: number;
    availableDays: number;
    notes: string | null;
};

type Props = {
    shell: ShellProps;
    can: { create: boolean; update: boolean };
    stats: {
        totalEmployees: number;
        totalBalances: number;
        totalAllocated: number;
        totalUsed: number;
    };
    currentYear: number;
    defaults: { year: number; allocated_days: number; carried_over_days: number };
    leaveTypes: LeaveType[];
    employees: EmployeeOption[];
    balances: BalanceRow[];
    urls: { index: string; save: string; searchEmployees: string };
};

type FormData = {
    employee_profile_id: number | '';
    employee_leave_type_id: number | '';
    year: number | string;
    allocated_days: number | string;
    carried_over_days: number | string;
    notes: string;
};

const formatDays = (value: number) => (Number.isInteger(value) ? String(value) : value.toFixed(1));

export default function EmployeeLeaveBalancesIndex({ shell, can, stats, currentYear, defaults, leaveTypes, employees, balances, urls }: Props) {
    const form = useForm<FormData>({
        employee_profile_id: '',
        employee_leave_type_id: leaveTypes[0]?.id ?? '',
        year: defaults.year,
        allocated_days: defaults.allocated_days,
        carried_over_days: defaults.carried_over_days,
        notes: '',
    });

    const [selected, setSelected] = useState<EmployeeOption | null>(null);
    const [search, setSearch] = useState('');
    const [results, setResults] = useState<EmployeeOption[]>(employees);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        const keyword = search.trim();

        if (keyword === '') {
            setResults(employees);
            setSearching(false);
            return;
        }

        const timer = setTimeout(async () => {
            setSearching(true);

            try {
                const params = new URLSearchParams({ q: keyword });
                const response = await fetch(`${urls.searchEmployees}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                const json = await response.json();
                setResults(Array.isArray(json.options) ? json.options : []);
            } catch {
                setResults([]);
            } finally {
                setSearching(false);
            }
        }, 350);

        return () => clearTimeout(timer);
    }, [search, employees, urls.searchEmployees]);

    const selectEmployee = (employee: EmployeeOption) => {
        setSelected(employee);
        form.setData('employee_profile_id', employee.id);
        setSearch('');
    };

    const clearEmployee = () => {
        setSelected(null);
        form.setData('employee_profile_id', '');
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(urls.save, {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('notes', '');
                setSearch('');
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Saldo Cuti Pegawai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kepegawaian"
                        title="Manajemen Saldo Cuti Pegawai"
                        description="Atur kuota cuti tahunan, carry-over sisa cuti tahun lalu, serta pantau jumlah pemakaian hak cuti masing-masing pegawai."
                    />

                    <OrgStats
                        label="Statistik saldo cuti"
                        items={[
                            { label: `Pegawai Bersaldo (${currentYear})`, value: stats.totalEmployees, icon: Users },
                            { label: 'Catatan Saldo', value: stats.totalBalances, icon: Layers, tone: 'gold' },
                            { label: 'Total Kuota Dialokasikan', value: `${formatDays(stats.totalAllocated)} Hari`, icon: CalendarPlus, tone: 'green' },
                            { label: 'Total Hari Terpakai', value: `${formatDays(stats.totalUsed)} Hari`, icon: CalendarMinus, tone: 'red' },
                        ]}
                    />

                    <div className="db-grid-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><SlidersHorizontal size={16} /> Atur & Penyesuaian Saldo</h2>
                            </div>
                            <div className="db-card-body">
                                <p className="db-hint" style={{ marginTop: 0, marginBottom: 14 }}>
                                    Tentukan kuota hak cuti pegawai pada periode tahun terpilih. Data dengan kombinasi pegawai, jenis cuti,
                                    dan tahun yang sama akan diperbarui.
                                </p>

                                <form onSubmit={submit}>
                                    <div style={{ marginBottom: 16 }}>
                                        <label className="crud-label">Pilih pegawai <span>*</span></label>

                                        {selected ? (
                                            <div
                                                style={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between',
                                                    gap: 12,
                                                    flexWrap: 'wrap',
                                                    padding: '11px 14px',
                                                    border: '1px solid var(--db-brand)',
                                                    borderRadius: 12,
                                                    background: 'var(--db-brand-soft)',
                                                }}
                                            >
                                                <span style={{ display: 'flex', alignItems: 'center', gap: 11, minWidth: 0 }}>
                                                    <span className="db-badge" style={{ width: 36, height: 36, justifyContent: 'center', borderRadius: 999, fontSize: 13, padding: 0 }}>
                                                        <UserRound size={16} />
                                                    </span>
                                                    <span style={{ minWidth: 0 }}>
                                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{selected.name}</b>
                                                        <small className="db-hint" style={{ marginTop: 0 }}>
                                                            {selected.employeeNumber || 'Nomor pegawai belum diisi'}{selected.email ? ` · ${selected.email}` : ''}
                                                        </small>
                                                    </span>
                                                </span>
                                                <button className="db-btn ghost sm" type="button" onClick={clearEmployee}>
                                                    Ganti
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <div style={{ position: 'relative' }}>
                                                    <Search size={15} style={{ position: 'absolute', left: 11, top: 12, color: 'var(--db-muted)' }} />
                                                    <input
                                                        className="db-input"
                                                        style={{ paddingLeft: 34 }}
                                                        type="search"
                                                        value={search}
                                                        onChange={(event) => setSearch(event.target.value)}
                                                        placeholder="Cari nama, email, username, atau kode pegawai…"
                                                        aria-label="Cari pegawai"
                                                    />
                                                </div>

                                                <div
                                                    style={{
                                                        marginTop: 8,
                                                        border: '1px solid var(--db-line)',
                                                        borderRadius: 12,
                                                        overflow: 'hidden',
                                                        background: 'var(--db-card)',
                                                    }}
                                                >
                                                    {searching && <div className="db-hint" style={{ padding: '10px 14px', marginTop: 0 }}>Mencari pegawai…</div>}
                                                    {!searching && results.length === 0 && (
                                                        <div className="db-hint" style={{ padding: '10px 14px', marginTop: 0 }}>
                                                            Tidak ada pegawai aktif yang cocok dengan pencarian.
                                                        </div>
                                                    )}
                                                    {!searching && results.map((employee) => (
                                                        <button
                                                            key={employee.id}
                                                            className="db-inbox-row"
                                                            type="button"
                                                            onClick={() => selectEmployee(employee)}
                                                            style={{
                                                                display: 'flex',
                                                                width: '100%',
                                                                alignItems: 'center',
                                                                justifyContent: 'space-between',
                                                                gap: 12,
                                                                padding: '10px 14px',
                                                                border: 0,
                                                                borderTop: '1px solid var(--db-line)',
                                                                background: 'transparent',
                                                                textAlign: 'left',
                                                                cursor: 'pointer',
                                                            }}
                                                        >
                                                            <span style={{ minWidth: 0 }}>
                                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{employee.name}</b>
                                                                <small className="db-hint" style={{ marginTop: 0 }}>
                                                                    {employee.employeeNumber || '-'}{employee.email ? ` · ${employee.email}` : ''}
                                                                </small>
                                                            </span>
                                                            <span className="db-badge gray">Pilih</span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </>
                                        )}

                                        {form.errors.employee_profile_id && <div className="db-error">{form.errors.employee_profile_id}</div>}
                                    </div>

                                    <div style={{ marginBottom: 14 }}>
                                        <SelectField
                                            label="Jenis cuti"
                                            required
                                            value={form.data.employee_leave_type_id}
                                            onChange={(event) => form.setData('employee_leave_type_id', event.target.value === '' ? '' : Number(event.target.value))}
                                            error={form.errors.employee_leave_type_id}
                                        >
                                            <option value="">-- Pilih Jenis Cuti --</option>
                                            {leaveTypes.map((type) => (
                                                <option key={type.id} value={type.id}>{type.name} ({type.code})</option>
                                            ))}
                                        </SelectField>
                                    </div>

                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: 12, marginBottom: 14 }}>
                                        <TextField
                                            label="Tahun"
                                            required
                                            type="number"
                                            min={2000}
                                            max={2100}
                                            value={form.data.year}
                                            onChange={(event) => form.setData('year', event.target.value === '' ? '' : Number(event.target.value))}
                                            error={form.errors.year}
                                        />
                                        <TextField
                                            label="Jatah cuti"
                                            required
                                            type="number"
                                            min={0}
                                            step={0.5}
                                            placeholder="12"
                                            value={form.data.allocated_days}
                                            onChange={(event) => form.setData('allocated_days', event.target.value === '' ? '' : Number(event.target.value))}
                                            error={form.errors.allocated_days}
                                            hint="Dalam satuan hari."
                                        />
                                        <TextField
                                            label="Carry over"
                                            type="number"
                                            min={0}
                                            step={0.5}
                                            placeholder="0"
                                            value={form.data.carried_over_days}
                                            onChange={(event) => form.setData('carried_over_days', event.target.value === '' ? '' : Number(event.target.value))}
                                            error={form.errors.carried_over_days}
                                            hint="Sisa cuti tahun lalu."
                                        />
                                    </div>

                                    <div style={{ marginBottom: 16 }}>
                                        <TextareaField
                                            label="Catatan / keterangan"
                                            rows={3}
                                            placeholder="Alasan penyesuaian saldo atau keterangan khusus cuti…"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                        />
                                    </div>

                                    {(can.create || can.update) && (
                                        <button className="db-btn primary" type="submit" disabled={form.processing} style={{ width: '100%', justifyContent: 'center' }}>
                                            <CalendarCheck size={15} /> Simpan Penyesuaian Saldo
                                        </button>
                                    )}
                                </form>
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><History size={16} /> Daftar Saldo Cuti Terbaru</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-table-wrap">
                                    <table className="crud-table">
                                        <thead>
                                            <tr>
                                                <th>Pegawai</th>
                                                <th>Jenis & Tahun</th>
                                                <th style={{ textAlign: 'right' }}>Jatah</th>
                                                <th style={{ textAlign: 'right' }}>Pending</th>
                                                <th style={{ textAlign: 'right' }}>Terpakai</th>
                                                <th style={{ textAlign: 'right' }}>Sisa Tersedia</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {balances.length === 0 && (
                                                <tr>
                                                    <td colSpan={6} style={{ textAlign: 'center', padding: 24 }}>
                                                        <span className="db-hint">Belum ada data saldo cuti yang tercatat di sistem.</span>
                                                    </td>
                                                </tr>
                                            )}
                                            {balances.map((balance) => (
                                                <tr key={balance.id}>
                                                    <td>
                                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{balance.employeeName}</b>
                                                        <small className="db-hint">{balance.employeeNumber || '-'}</small>
                                                    </td>
                                                    <td>
                                                        <span className="db-badge gray" style={{ marginRight: 5 }}>{balance.leaveTypeName}</span>
                                                        <span className="db-badge">{balance.year}</span>
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}><b>{formatDays(balance.allocatedDays)}</b></td>
                                                    <td style={{ textAlign: 'right', color: 'var(--db-gold)' }}>{formatDays(balance.pendingDays)}</td>
                                                    <td style={{ textAlign: 'right', color: 'var(--db-red)' }}>{formatDays(balance.usedDays)}</td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <span className="db-badge green">{formatDays(balance.availableDays)} Hari</span>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </AdminShell>
    );
}
