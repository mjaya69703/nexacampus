import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarMinus } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type App = {
    id: number; number: string; academicYear: string; semester: number; duration: number; reasonCategory: string; reason: string;
    status: string; statusLabel: string; studentNotes: string | null; adminNotes: string | null; attachmentUrl: string | null;
    leaveFeeAmount: number | null; leaveFeeInvoiceId: number | null;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};

type Props = { shell: ShellProps; application: App; urls: { index: string; edit: string | null } };

export default function LeavesShow({ shell, application, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Cuti · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarMinus} eyebrow="Student Services" title={application.number} description={application.academicYear} badges={[application.statusLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Status:</b> {application.statusLabel}</div>
                            <div><b>Semester:</b> {application.semester} · Durasi {application.duration} sem</div>
                            <div><b>Alasan:</b> {application.reason}</div>
                            {application.studentNotes && <div><b>Catatan Mahasiswa:</b> {application.studentNotes}</div>}
                            {application.adminNotes && <div><b>Catatan Admin:</b> {application.adminNotes}</div>}
                            {application.attachmentUrl && <a href={application.attachmentUrl} target="_blank" className="db-btn ghost sm">Lampiran</a>}
                            {application.leaveFeeAmount !== null && <div><b>Biaya Cuti:</b> {application.leaveFeeAmount}</div>}
                            {urls.edit && <Link href={urls.edit} className="db-btn primary sm">Perbaiki</Link>}
                        </div>
                    </section>
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Riwayat</h2></div>
                        <div className="db-card-body">
                            {application.histories.map((h) => (
                                <div key={h.id} style={{ padding: '6px 0', borderBottom: '1px solid var(--db-line)' }}>
                                    <b>{h.to}</b> · {h.changedAt} · {h.changedBy ?? '-'}{h.notes ? ` — ${h.notes}` : ''}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
