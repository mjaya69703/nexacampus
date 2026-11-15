// Detail periode BKD beserta laporan dosen yang masuk.
import { Head } from '@inertiajs/react';
import { ArrowLeft, CalendarCheck, CheckCircle2, Eye, FileText, Pencil } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { OrgStats } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Period = {
    id: number;
    name: string;
    code: string;
    status: string;
    statusLabel: string;
    academicYear: string;
    academicPeriod: string;
    startsAt: string;
    endsAt: string;
    sksRange: string;
    notes: string | null;
    submissionsCount: number;
    approvedCount: number;
    averageSks: number;
};

type Submission = {
    id: number;
    ownerName: string;
    ownerEmail: string | null;
    status: string;
    statusLabel: string;
    teachingSks: number;
    structuralSks: number;
    tridharmaSks: number;
    totalSks: number;
    showUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean };
    period: Period;
    submissions: Submission[];
    urls: { index: string; edit: string };
};

const periodTone = (status: string) => {
    if (status === 'open') return 'green';
    if (status === 'review') return 'amber';
    if (status === 'closed') return 'gray';
    return '';
};

const submissionTone = (status: string) => {
    if (status === 'approved') return 'green';
    if (status === 'rejected') return 'red';
    if (status === 'in_approval' || status === 'submitted') return 'amber';
    if (status === 'revision') return 'gold';
    return 'gray';
};

const formatSks = (value: number) => `${value.toFixed(1)} SKS`;

export default function LecturerWorkloadPeriodShow({ shell, can, period, submissions, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`${period.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kepegawaian · Periode BKD"
                        title={period.name}
                        description={`Kode: ${period.code} · ${period.startsAt} s.d. ${period.endsAt}`}
                        badges={[period.statusLabel]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali ke daftar</a>
                                {can.update && <a className="db-btn primary" href={urls.edit}><Pencil size={15} /> Edit Periode</a>}
                            </>
                        )}
                    />

                    <OrgStats
                        label="Statistik periode BKD"
                        items={[
                            { label: 'Status Periode', value: period.statusLabel, icon: CalendarCheck },
                            { label: 'Total Laporan Masuk', value: period.submissionsCount, icon: FileText },
                            { label: 'Disetujui (Approved)', value: period.approvedCount, icon: CheckCircle2, tone: 'green' },
                            { label: 'Rata-Rata SKS', value: formatSks(period.averageSks), icon: CalendarCheck, tone: 'gold' },
                        ]}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarCheck size={16} /> Informasi Periode</h2>
                            <span className={`db-badge ${periodTone(period.status)}`.trim()}>{period.statusLabel}</span>
                        </div>
                        <div className="db-card-body">
                            <div className="db-facts">
                                <div className="db-fact"><span>Kode Periode</span><b>{period.code}</b></div>
                                <div className="db-fact"><span>Tahun Akademik</span><b>{period.academicYear}</b></div>
                                <div className="db-fact"><span>Periode Akademik</span><b>{period.academicPeriod}</b></div>
                                <div className="db-fact"><span>Rentang SKS</span><b>{period.sksRange}</b></div>
                                <div className="db-fact"><span>Mulai Pengajuan</span><b>{period.startsAt}</b></div>
                                <div className="db-fact"><span>Batas Akhir</span><b>{period.endsAt}</b></div>
                                <div className="db-fact"><span>Jumlah Laporan</span><b>{period.submissionsCount} laporan</b></div>
                                <div className="db-fact"><span>Disetujui</span><b>{period.approvedCount} laporan</b></div>
                                <div className="db-fact wide"><span>Catatan & Keterangan</span><b>{period.notes ?? '-'}</b></div>
                            </div>
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><FileText size={16} /> Daftar Laporan BKD Masuk</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Dosen Pemohon</th>
                                            <th>Status Laporan</th>
                                            <th style={{ textAlign: 'right' }}>Mengajar</th>
                                            <th style={{ textAlign: 'right' }}>Struktural</th>
                                            <th style={{ textAlign: 'right' }}>Tridharma Lainnya</th>
                                            <th style={{ textAlign: 'right' }}>Total SKS</th>
                                            <th style={{ textAlign: 'right' }}>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {submissions.length === 0 && (
                                            <tr>
                                                <td colSpan={7} style={{ textAlign: 'center', padding: 24 }}>
                                                    <span className="db-hint">Belum ada laporan BKD masuk dari dosen untuk periode ini.</span>
                                                </td>
                                            </tr>
                                        )}
                                        {submissions.map((submission) => (
                                            <tr key={submission.id}>
                                                <td>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{submission.ownerName}</b>
                                                    <small className="db-hint">{submission.ownerEmail ?? '-'}</small>
                                                </td>
                                                <td>
                                                    <span className={`db-badge ${submissionTone(submission.status)}`.trim()}>{submission.statusLabel}</span>
                                                </td>
                                                <td style={{ textAlign: 'right' }}>{formatSks(submission.teachingSks)}</td>
                                                <td style={{ textAlign: 'right' }}>{formatSks(submission.structuralSks)}</td>
                                                <td style={{ textAlign: 'right' }}>{formatSks(submission.tridharmaSks)}</td>
                                                <td style={{ textAlign: 'right' }}>
                                                    <b style={{ color: 'var(--db-brand)', fontSize: 13 }}>{formatSks(submission.totalSks)}</b>
                                                </td>
                                                <td>
                                                    <div className="crud-row-actions">
                                                        <a className="db-btn ghost sm" href={submission.showUrl} title="Lihat detail laporan">
                                                            <Eye size={13} />
                                                        </a>
                                                    </div>
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
        </AdminShell>
    );
}
