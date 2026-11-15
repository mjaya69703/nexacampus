// Laporan rekapitulasi keuangan — ringkasan + unduh CSV/Excel/PDF (paritas Blade).
import { Head } from '@inertiajs/react';
import { ChartPie, FileDown, FileSpreadsheet, FileText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type TypeRow = { type: string; count: number; total: number; paid: number; outstanding: number };

type Props = {
    shell: ShellProps;
    stats: { verifiedPayments: number; outstanding: number; adjustments: number; credits: number };
    typeRows: TypeRow[];
    urls: { csv: string; xlsx: string; pdf: string };
};

const money = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

export default function FinancialReport({ shell, stats, typeRows, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Laporan Keuangan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={ChartPie}
                        eyebrow="Keuangan"
                        title="Laporan Keuangan"
                        description="Rekap penerimaan, tunggakan, penyesuaian, dan deposit per jenis tagihan."
                        actions={
                            <>
                                <a className="db-btn light" href={urls.csv}><FileText size={15} /> CSV</a>
                                <a className="db-btn light" href={urls.xlsx}><FileSpreadsheet size={15} /> Excel</a>
                                <a className="db-btn light" href={urls.pdf} target="_blank" rel="noreferrer"><FileDown size={15} /> PDF</a>
                            </>
                        }
                    />

                    <section className="db-stats" aria-label="Statistik laporan">
                        <div className="db-stat">
                            <span className="db-stat-icon green"><ChartPie size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.verifiedPayments)}</span><span className="db-stat-label">Penerimaan terverifikasi</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon red"><ChartPie size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.outstanding)}</span><span className="db-stat-label">Tunggakan aktif</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon gold"><ChartPie size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.adjustments)}</span><span className="db-stat-label">Total penyesuaian</span></div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><ChartPie size={20} /></span>
                            <div><span className="db-stat-num">{money(stats.credits)}</span><span className="db-stat-label">Deposit mahasiswa</span></div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Rekap per Jenis Tagihan</h2>
                        </div>
                        <div className="db-card-body">
                            {typeRows.length === 0 ? (
                                <p className="db-hint">Belum ada data tagihan terbit.</p>
                            ) : (
                                <div className="db-table-wrap">
                                    <table className="db-table">
                                        <thead>
                                            <tr><th>Jenis</th><th style={{ textAlign: 'right' }}>Invoice</th><th style={{ textAlign: 'right' }}>Total</th><th style={{ textAlign: 'right' }}>Terbayar</th><th style={{ textAlign: 'right' }}>Sisa</th></tr>
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
                </div>
            </div>
        </AdminShell>
    );
}
