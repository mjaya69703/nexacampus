// Dashboard operasional keuangan — agregat read-only (paritas Blade).
import { Head } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, ChartPie, Clock, Lock, PiggyBank, Receipt, Wallet } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type BarRow = { label: string; amount: number };
type StatusRow = { status: string; count: number; total: number; outstanding: number };
type TypeRow = { type: string; count: number; total: number; paid: number; outstanding: number };

type Props = {
    shell: ShellProps;
    can: { viewInvoices: boolean; viewPayments: boolean; viewHolds: boolean; runSchedule: boolean };
    stats: {
        verifiedPayments: number;
        pendingPayments: number;
        outstanding: number;
        overdueInvoices: number;
        activeHolds: number;
        dueSchedules: number;
        studentCredits: number;
        activeScholarships: number;
        netAdjustments: number;
    };
    collection: {
        billed: number;
        paid: number;
        outstanding: number;
        paidPercent: number;
        outstandingPercent: number;
        maxOutstanding: number;
    };
    statusRows: StatusRow[];
    typeRows: TypeRow[];
    trend: BarRow[];
    aging: BarRow[];
    methods: BarRow[];
    programs: BarRow[];
    recentPayments: { id: number; number: string; invoice: string | null; student: string | null; amount: number; status: string; url: string }[];
    overdue: { id: number; number: string; student: string | null; outstanding: number; dueDate: string | null; url: string }[];
    dueSchedules: { id: number; name: string; year: string | null; publishAt: string | null; url: string }[];
    holds: { id: number; student: string | null; invoice: string | null; holdType: string; url: string }[];
    urls: { invoices: string; payments: string; holds: string; schedules: string; reports: string };
};

const money = (n: number) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;

function Bars({ rows, max, format = money }: { rows: BarRow[]; max: number; format?: (n: number) => string }) {
    if (rows.length === 0) return <p className="db-hint">Belum ada data.</p>;

    return (
        <div className="cx-bars">
            {rows.map((row) => (
                <div className="cx-bar-row" key={row.label}>
                    <div className="cx-bar-meta">
                        <span>{row.label}</span>
                        <b>{format(row.amount)}</b>
                    </div>
                    <div className="cx-bar-track">
                        <i style={{ width: `${max > 0 ? Math.min(100, Math.round((row.amount / max) * 100)) : 0}%` }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function FinancialDashboard({
    shell,
    can,
    stats,
    collection,
    statusRows,
    typeRows,
    trend,
    aging,
    methods,
    programs,
    recentPayments,
    overdue,
    dueSchedules,
    holds,
    urls,
}: Props) {
    const maxTrend = Math.max(1, ...trend.map((r) => r.amount));
    const maxAging = Math.max(1, ...aging.map((r) => r.amount));
    const maxMethod = Math.max(1, ...methods.map((r) => r.amount));
    const maxProgram = Math.max(1, ...programs.map((r) => r.amount));

    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Keuangan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ChartPie}
                        eyebrow="Keuangan"
                        title="Dashboard Keuangan"
                        description="Penerimaan, tunggakan, dan antrean kerja operasional hari ini."
                        actions={<a className="db-btn light" href={urls.reports}>Laporan</a>}
                    />

                    <section className="db-stats" aria-label="Statistik keuangan">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><Wallet size={20} /></span>
                            <div>
                                <span className="db-stat-num">{money(stats.verifiedPayments)}</span>
                                <span className="db-stat-label">Penerimaan terverifikasi</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><AlertTriangle size={20} /></span>
                            <div>
                                <span className="db-stat-num">{money(stats.outstanding)}</span>
                                <span className="db-stat-label">Tunggakan ({stats.overdueInvoices} overdue)</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><Clock size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.pendingPayments}</span>
                                <span className="db-stat-label">Bayar menunggu review</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><Lock size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.activeHolds}</span>
                                <span className="db-stat-label">Hold aktif</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num">{stats.dueSchedules}</span>
                                <span className="db-stat-label">Jadwal jatuh tempo</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon green"><PiggyBank size={20} /></span>
                            <div>
                                <span className="db-stat-num">{money(stats.studentCredits)}</span>
                                <span className="db-stat-label">Deposit mahasiswa</span>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Kolektibilitas ({collection.paidPercent}% tertagih)</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="cx-bars">
                                <div className="cx-bar-row">
                                    <div className="cx-bar-meta">
                                        <span>Tertagih {money(collection.paid)} dari {money(collection.billed)}</span>
                                        <b>{collection.paidPercent}%</b>
                                    </div>
                                    <div className="cx-bar-track"><i style={{ width: `${collection.paidPercent}%` }} /></div>
                                </div>
                                <div className="cx-bar-row">
                                    <div className="cx-bar-meta">
                                        <span>Tunggakan {money(collection.outstanding)}</span>
                                        <b>{collection.outstandingPercent}%</b>
                                    </div>
                                    <div className="cx-bar-track"><i style={{ width: `${collection.outstandingPercent}%` }} /></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Tren Penerimaan (6 bulan)</h2>
                            </div>
                            <div className="db-card-body">
                                <Bars rows={trend} max={maxTrend} />
                            </div>
                        </section>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Aging Tunggakan</h2>
                            </div>
                            <div className="db-card-body">
                                <Bars rows={aging} max={maxAging} />
                            </div>
                        </section>
                    </div>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Metode Pembayaran</h2>
                            </div>
                            <div className="db-card-body">
                                <Bars rows={methods} max={maxMethod} />
                            </div>
                        </section>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Tunggakan per Prodi</h2>
                            </div>
                            <div className="db-card-body">
                                <Bars rows={programs} max={maxProgram} />
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Komposisi per Jenis Tagihan</h2>
                        </div>
                        <div className="db-card-body">
                            {typeRows.length === 0 ? (
                                <p className="db-hint">Belum ada data.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Jenis</th>
                                                <th style={{ textAlign: 'right' }}>Invoice</th>
                                                <th style={{ textAlign: 'right' }}>Total</th>
                                                <th style={{ textAlign: 'right' }}>Terbayar</th>
                                                <th style={{ textAlign: 'right' }}>Sisa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {typeRows.map((row) => (
                                                <tr key={row.type}>
                                                    <td><b>{row.type}</b></td>
                                                    <td style={{ textAlign: 'right' }}>{row.count}</td>
                                                    <td style={{ textAlign: 'right' }}>{money(row.total)}</td>
                                                    <td style={{ textAlign: 'right' }}>{money(row.paid)}</td>
                                                    <td style={{ textAlign: 'right' }}><b>{money(row.outstanding)}</b></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Pembayaran Terakhir</h2>
                                {can.viewPayments && <a className="db-btn ghost sm" href={urls.payments}>Semua</a>}
                            </div>
                            <div className="db-card-body tight">
                                {recentPayments.length === 0 && <p className="db-hint">Belum ada pembayaran.</p>}
                                {recentPayments.map((p) => (
                                    <div className="db-summary" key={p.id}>
                                        <a className="db-hint" href={p.url}>{p.number} · {p.student ?? '-'}</a>
                                        <span>
                                            <b>{money(p.amount)}</b>
                                            <span className="db-badge" style={{ marginLeft: 8 }}>{p.status}</span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Overdue Mendesak</h2>
                                {can.viewInvoices && <a className="db-btn ghost sm" href={urls.invoices}>Semua</a>}
                            </div>
                            <div className="db-card-body tight">
                                {overdue.length === 0 && <p className="db-hint">Tidak ada tunggakan overdue.</p>}
                                {overdue.map((inv) => (
                                    <div className="db-summary" key={inv.id}>
                                        <a className="db-hint" href={inv.url}>{inv.number} · {inv.student ?? '-'} · jatuh {inv.dueDate ?? '-'}</a>
                                        <span><b>{money(inv.outstanding)}</b></span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <div className="cx-cards-2">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Jadwal Siap Eksekusi ({dueSchedules.length})</h2>
                                {can.runSchedule && <a className="db-btn ghost sm" href={urls.schedules}>Semua</a>}
                            </div>
                            <div className="db-card-body tight">
                                {dueSchedules.length === 0 && <p className="db-hint">Tidak ada jadwal yang jatuh tempo.</p>}
                                {dueSchedules.map((s) => (
                                    <div className="db-summary" key={s.id}>
                                        <a className="db-hint" href={s.url}>{s.name} · {s.year ?? '-'}</a>
                                        <span><small className="db-hint">{s.publishAt ?? '-'}</small></span>
                                    </div>
                                ))}
                            </div>
                        </section>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Hold Aktif Terbaru</h2>
                                {can.viewHolds && <a className="db-btn ghost sm" href={urls.holds}>Semua</a>}
                            </div>
                            <div className="db-card-body tight">
                                {holds.length === 0 && <p className="db-hint">Tidak ada hold aktif.</p>}
                                {holds.map((h) => (
                                    <div className="db-summary" key={h.id}>
                                        <a className="db-hint" href={h.url}>{h.student ?? '-'} · {h.invoice ?? '-'}</a>
                                        <span><span className="db-badge red">{h.holdType}</span></span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Receipt size={16} /> Status Invoice</h2>
                        </div>
                        <div className="db-card-body">
                            {statusRows.length === 0 ? (
                                <p className="db-hint">Belum ada data.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th style={{ textAlign: 'right' }}>Invoice</th>
                                                <th style={{ textAlign: 'right' }}>Total</th>
                                                <th style={{ textAlign: 'right' }}>Sisa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {statusRows.map((row) => (
                                                <tr key={row.status}>
                                                    <td><b>{row.status}</b></td>
                                                    <td style={{ textAlign: 'right' }}>{row.count}</td>
                                                    <td style={{ textAlign: 'right' }}>{money(row.total)}</td>
                                                    <td style={{ textAlign: 'right' }}><b>{money(row.outstanding)}</b></td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
