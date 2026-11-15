// Detail pengajuan cuti pegawai: informasi, saldo, approval, dan pembatalan.
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    Info,
    UserRound,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Leave = {
    id: number;
    requestNumber: string;
    status: string;
    statusLabel: string;
    employeeName: string;
    employeeNumber: string | null;
    employeeEmail: string | null;
    workUnit: string | null;
    leaveType: string | null;
    leaveTypeCode: string | null;
    startsAt: string | null;
    endsAt: string | null;
    startsAtDay: string | null;
    endsAtDay: string | null;
    totalDays: number;
    reason: string;
    employeeNotes: string | null;
    adminNotes: string | null;
    createdAt: string | null;
    approval: { id: number; subject: string; status: string; statusLabel: string; url: string } | null;
};

type Balance = {
    year: number;
    availableDays: number;
    allocatedDays: number;
    usedDays: number;
    pendingDays: number;
    carriedOverDays: number;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    leave: Leave;
    balance: Balance | null;
    canCancel: boolean;
    urls: { index: string; cancel: string; delete: string };
};

function statusTone(status: string): string {
    if (status === 'approved') return 'green';
    if (['rejected', 'cancelled'].includes(status)) return 'red';
    return 'gold';
}

export default function EmployeeLeaveRequestShow({ shell, leave, balance, canCancel, urls }: Props) {
    const [notes, setNotes] = useState('');
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const cancel = () => {
        setProcessing(true);
        router.post(urls.cancel, { notes }, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${leave.requestNumber} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarClock}
                        eyebrow="Kepegawaian"
                        title={leave.requestNumber}
                        description={`${leave.employeeName} · ${leave.leaveType ?? 'Jenis cuti tidak diketahui'} · ${leave.startsAt ?? '-'} s.d. ${leave.endsAt ?? '-'}`}
                        badges={[leave.statusLabel, `${leave.totalDays.toFixed(1)} hari`, leave.workUnit ?? 'Tanpa unit kerja']}
                        actions={<a className="db-btn light" href={urls.index}><ArrowLeft size={14} /> Kembali ke daftar</a>}
                    />

                    <section className="db-stats" aria-label="Ringkasan pengajuan cuti">
                        <div className="db-stat">
                            <span className="db-stat-icon"><UserRound size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{leave.employeeName}</span>
                                <span className="db-stat-label">{leave.employeeNumber ?? 'Tanpa nomor pegawai'}</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><CalendarClock size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{leave.totalDays.toFixed(1)} hari</span>
                                <span className="db-stat-label">{leave.leaveType ?? 'Jenis cuti'}</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className="db-stat-icon"><ClipboardCheck size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{leave.statusLabel}</span>
                                <span className="db-stat-label">Status Pengajuan</span>
                            </div>
                        </div>
                        <div className="db-stat">
                            <span className={`db-stat-icon ${statusTone(leave.status) === 'green' ? 'green' : statusTone(leave.status) === 'red' ? 'red' : 'gold'}`}><Wallet size={20} /></span>
                            <div>
                                <span className="db-stat-num" style={{ fontSize: 16 }}>{balance ? `${balance.availableDays.toFixed(1)} hari` : '-'}</span>
                                <span className="db-stat-label">Sisa Saldo Cuti</span>
                            </div>
                        </div>
                    </section>

                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 2fr) minmax(280px, 1fr)', gap: 16, alignItems: 'start' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Info size={16} /> Informasi Pengajuan</h2>
                                </div>
                                <div className="db-card-body">
                                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                                        <tbody>
                                            {[
                                                ['Nama Pegawai', leave.employeeName],
                                                ['Nomor Pegawai', leave.employeeNumber ?? '-'],
                                                ['Email', leave.employeeEmail ?? '-'],
                                                ['Unit Kerja', leave.workUnit ?? '-'],
                                                ['Jenis Cuti', leave.leaveType ? `${leave.leaveType}${leave.leaveTypeCode ? ` (${leave.leaveTypeCode})` : ''}` : '-'],
                                                ['Tanggal Mulai', leave.startsAtDay ?? leave.startsAt ?? '-'],
                                                ['Tanggal Berakhir', leave.endsAtDay ?? leave.endsAt ?? '-'],
                                                ['Total Hari', `${leave.totalDays.toFixed(1)} hari`],
                                                ['Diajukan Pada', leave.createdAt ?? '-'],
                                            ].map(([label, value]) => (
                                                <tr key={label} style={{ borderBottom: '1px solid var(--db-line)' }}>
                                                    <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500, width: 180 }}>{label}</th>
                                                    <td style={{ padding: '10px 0', color: 'var(--db-ink)' }}>{value}</td>
                                                </tr>
                                            ))}
                                            <tr>
                                                <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500 }}>Alasan</th>
                                                <td style={{ padding: '10px 0', color: 'var(--db-ink)', whiteSpace: 'pre-line' }}>{leave.reason}</td>
                                            </tr>
                                            {leave.employeeNotes && (
                                                <tr style={{ borderTop: '1px solid var(--db-line)' }}>
                                                    <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500 }}>Catatan Pegawai</th>
                                                    <td style={{ padding: '10px 0', color: 'var(--db-ink)', whiteSpace: 'pre-line' }}>{leave.employeeNotes}</td>
                                                </tr>
                                            )}
                                            {leave.adminNotes && (
                                                <tr style={{ borderTop: '1px solid var(--db-line)' }}>
                                                    <th style={{ textAlign: 'left', padding: '10px 0', color: 'var(--db-muted)', fontWeight: 500 }}>Catatan Admin / Approver</th>
                                                    <td style={{ padding: '10px 0', color: 'var(--db-ink)', whiteSpace: 'pre-line' }}>{leave.adminNotes}</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            {leave.approval && (
                                <section className="db-card">
                                    <div className="db-card-head">
                                        <h2 className="db-card-title"><ClipboardCheck size={16} /> Alur Persetujuan</h2>
                                        <span className={`db-badge ${statusTone(leave.approval.status)}`}>{leave.approval.statusLabel}</span>
                                    </div>
                                    <div className="db-card-body">
                                        <div style={{ padding: 14, border: '1px solid var(--db-line)', borderRadius: 12, background: 'var(--db-card)', marginBottom: 12 }}>
                                            <div className="db-hint" style={{ marginTop: 0, marginBottom: 4 }}>Judul approval:</div>
                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{leave.approval.subject}</b>
                                        </div>
                                        <a className="db-btn primary" href={leave.approval.url} style={{ width: '100%', justifyContent: 'center' }}>
                                            <ClipboardCheck size={14} /> Buka Alur Persetujuan
                                        </a>
                                    </div>
                                </section>
                            )}
                        </div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Wallet size={16} /> Saldo Cuti {balance ? balance.year : ''}</h2>
                                </div>
                                <div className="db-card-body">
                                    {balance ? (
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                                            <div style={{ padding: 12, borderRadius: 12, border: '1px solid var(--db-line)', background: 'var(--db-card)' }}>
                                                <b style={{ display: 'block', color: 'var(--db-brand)', fontSize: 20 }}>{balance.availableDays.toFixed(1)}</b>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Sisa Tersedia</span>
                                            </div>
                                            <div style={{ padding: 12, borderRadius: 12, border: '1px solid var(--db-line)', background: 'var(--db-card)' }}>
                                                <b style={{ display: 'block', color: 'var(--db-red, #dc2626)', fontSize: 20 }}>{balance.usedDays.toFixed(1)}</b>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Hari Terpakai</span>
                                            </div>
                                            <div style={{ padding: 12, borderRadius: 12, border: '1px solid var(--db-line)', background: 'var(--db-card)' }}>
                                                <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 16 }}>{balance.allocatedDays.toFixed(1)}</b>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Jatah Kuota</span>
                                            </div>
                                            <div style={{ padding: 12, borderRadius: 12, border: '1px solid var(--db-line)', background: 'var(--db-card)' }}>
                                                <b style={{ display: 'block', color: 'var(--db-gold, #d97706)', fontSize: 16 }}>{balance.pendingDays.toFixed(1)}</b>
                                                <span className="db-hint" style={{ marginTop: 0 }}>Hari Pending</span>
                                            </div>
                                            {balance.carriedOverDays > 0 && (
                                                <div style={{ gridColumn: '1 / -1', padding: 12, borderRadius: 12, border: '1px solid var(--db-line)', background: 'var(--db-card)' }}>
                                                    <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 14 }}>{balance.carriedOverDays.toFixed(1)} hari</b>
                                                    <span className="db-hint" style={{ marginTop: 0 }}>Cuti terbawa dari tahun sebelumnya</span>
                                                </div>
                                            )}
                                        </div>
                                    ) : (
                                        <div style={{ textAlign: 'center', padding: '18px 0' }}>
                                            <AlertTriangle size={30} style={{ color: 'var(--db-gold, #d97706)', marginBottom: 8 }} />
                                            <div className="db-hint" style={{ marginTop: 0 }}>
                                                Saldo cuti belum dibuat atau diatur untuk jenis cuti ini pada tahun {leave.startsAt ? leave.startsAt.slice(-4) : '-'}.
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </section>

                            {canCancel && (
                                <section className="db-card" style={{ borderColor: 'rgba(220, 38, 38, .35)' }}>
                                    <div className="db-card-head">
                                        <h2 className="db-card-title" style={{ color: 'var(--db-red, #dc2626)' }}><Ban size={16} /> Batalkan Pengajuan</h2>
                                    </div>
                                    <div className="db-card-body">
                                        <TextareaField
                                            label="Catatan / alasan pembatalan"
                                            rows={3}
                                            placeholder="Tuliskan alasan pengajuan cuti ini dibatalkan…"
                                            value={notes}
                                            onChange={(event) => setNotes(event.target.value)}
                                        />
                                        <button
                                            className="db-btn danger"
                                            type="button"
                                            onClick={() => setConfirming(true)}
                                            disabled={processing}
                                            style={{ width: '100%', justifyContent: 'center', marginTop: 12 }}
                                        >
                                            <Ban size={14} /> Batalkan Pengajuan
                                        </button>
                                    </div>
                                </section>
                            )}

                            {leave.status === 'approved' && (
                                <section className="db-card">
                                    <div className="db-card-body" style={{ textAlign: 'center', padding: '18px 0' }}>
                                        <CheckCircle2 size={34} style={{ color: 'var(--db-green, #16a34a)', marginBottom: 8 }} />
                                        <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>Pengajuan Disetujui</b>
                                        <div className="db-hint" style={{ marginTop: 4 }}>Saldo cuti telah dipotong sesuai durasi pengajuan.</div>
                                    </div>
                                </section>
                            )}
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={confirming}
                    title="Batalkan pengajuan cuti?"
                    message={`Pengajuan ${leave.requestNumber} atas nama ${leave.employeeName} akan dibatalkan dan saldo pending dikembalikan.`}
                    confirmLabel="Ya, batalkan"
                    danger
                    processing={processing}
                    onConfirm={cancel}
                    onCancel={() => setConfirming(false)}
                />
            </div>
        </AdminShell>
    );
}
