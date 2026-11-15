// Tagihan mahasiswa — daftar invoice (Inertia React).
// Invoice draft tidak ditampilkan; status overdue disegarkan di server.
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle, ArrowRight, CalendarClock, CircleDollarSign, FileText,
    Inbox, ReceiptText, TriangleAlert, Wallet,
} from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { EmptyState } from '../../../components/Shared/EmptyState';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Invoice = {
    id: number;
    number: string;
    type: string;
    typeLabel: string;
    status: string;
    statusLabel: string;
    statusTone: string;
    academicYear: string;
    semester: string | number | null;
    total: number;
    paid: number;
    outstanding: number;
    dueDate: string;
    isOverdue: boolean;
    paidPercent: number;
    itemsCount: number;
    url: string;
};

type Props = {
    shell: ShellProps;
    hasProfile: boolean;
    invoices: Invoice[];
    summary: { totalInvoices: number; outstanding: number; overdue: number; paid: number };
    urls: { index: string };
};

const money = (value: number): string => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

const toneClass = (tone: string): string => {
    switch (tone) {
        case 'green':
            return 'green';
        case 'red':
            return 'red';
        case 'amber':
            return 'amber';
        case 'blue':
            return '';
        default:
            return 'gray';
    }
};

export default function FinancialInvoices({ shell, hasProfile, invoices, summary }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Tagihan Saya · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wallet}
                        eyebrow="Financial"
                        title="Tagihan Saya"
                        description="Invoice kuliah, status pembayaran, dan sisa tagihan yang perlu diselesaikan."
                        badges={[`${summary.totalInvoices} invoice`, `${summary.overdue} jatuh tempo`]}
                    />

                    {!hasProfile && (
                        <div className="db-note danger">
                            <TriangleAlert size={15} />
                            <span><b>Profil mahasiswa belum ditemukan.</b> Tagihan tidak bisa ditampilkan.</span>
                        </div>
                    )}

                    {hasProfile && (
                        <>
                            <section className="db-stats">
                                <div className="db-stat">
                                    <span className="db-stat-icon"><ReceiptText size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.totalInvoices}</span>
                                        <span className="db-stat-label">Total invoice</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon gold"><CircleDollarSign size={20} /></span>
                                    <div>
                                        <span className="db-stat-num" style={{ fontSize: 22 }}>{money(summary.outstanding)}</span>
                                        <span className="db-stat-label">Outstanding</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon red"><AlertTriangle size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.overdue}</span>
                                        <span className="db-stat-label">Jatuh tempo</span>
                                    </div>
                                </div>
                                <div className="db-stat">
                                    <span className="db-stat-icon green"><FileText size={20} /></span>
                                    <div>
                                        <span className="db-stat-num">{summary.paid}</span>
                                        <span className="db-stat-label">Lunas</span>
                                    </div>
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><ReceiptText size={16} /> Daftar Invoice</h2>
                                    <span className="db-badge gray">{invoices.length} invoice</span>
                                </div>
                                <div className="db-card-body">
                                    {invoices.length === 0 ? (
                                        <EmptyState
                                            icon={Inbox}
                                            title="Belum ada tagihan"
                                            description="Invoice akan muncul setelah bagian keuangan menerbitkan tagihan semester."
                                        />
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {invoices.map((invoice) => (
                                                <article key={invoice.id} className="db-class">
                                                    <div className="db-class-top">
                                                        <div style={{ minWidth: 0 }}>
                                                            <b>{invoice.number}</b>
                                                            <small>
                                                                {invoice.typeLabel} · {invoice.academicYear}
                                                                {invoice.semester ? ` · Smt ${invoice.semester}` : ''}
                                                            </small>
                                                        </div>
                                                        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                                            <span className={`db-badge ${toneClass(invoice.statusTone)}`}>{invoice.statusLabel}</span>
                                                            {invoice.isOverdue && <span className="db-badge red">Lewat jatuh tempo</span>}
                                                        </div>
                                                    </div>

                                                    <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', marginTop: 10 }}>
                                                        <div><span className="db-hint">Total</span><div style={{ fontWeight: 700 }}>{money(invoice.total)}</div></div>
                                                        <div><span className="db-hint">Dibayar</span><div style={{ fontWeight: 700, color: 'var(--db-green)' }}>{money(invoice.paid)}</div></div>
                                                        <div><span className="db-hint">Sisa</span><div style={{ fontWeight: 700, color: invoice.outstanding > 0 ? 'var(--db-red)' : 'var(--db-muted)' }}>{money(invoice.outstanding)}</div></div>
                                                        <div><span className="db-hint">Jatuh tempo</span><div style={{ fontWeight: 600 }}><CalendarClock size={12} /> {invoice.dueDate}</div></div>
                                                        <div><span className="db-hint">Item</span><div style={{ fontWeight: 600 }}>{invoice.itemsCount} item</div></div>
                                                    </div>

                                                    <div className="db-progress"><i style={{ width: `${Math.min(100, Math.max(0, invoice.paidPercent))}%` }} /></div>
                                                    <div className="db-hint" style={{ marginTop: 4 }}>{invoice.paidPercent}% terbayar</div>

                                                    <div className="db-class-actions">
                                                        <Link href={invoice.url} className="db-btn primary sm">
                                                            Detail <ArrowRight size={13} />
                                                        </Link>
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
