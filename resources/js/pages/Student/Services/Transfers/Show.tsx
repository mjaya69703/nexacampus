import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Req = { id: number; number: string; fromProgram: string; toProgram: string; transferType: string; fromClassType: string | null; toClassType: string | null; currentSemester: number | null; recommendedSemester: number | null; reason: string; status: string; statusLabel: string; studentNotes: string | null; adminNotes: string | null; academicEvaluationNotes: string | null; creditMappingNotes: string | null; financeNotes: string | null; transferFeeAmount: number | null; transferFeeDueDate: string | null; attachmentUrl: string | null; histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[]; approval: { status: string; waitingMessage: string | null } | null };

type Props = { shell: ShellProps; request: Req; urls: { index: string; edit: string | null } };

export default function TransfersShow({ shell, request, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Pindah · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ArrowRightLeft} eyebrow="Student Services" title={request.number} description={`${request.fromProgram} → ${request.toProgram}`} badges={[request.statusLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Status:</b> {request.statusLabel}</div>
                            <div><b>Jenis:</b> {request.transferType}</div>
                            <div><b>Alasan:</b> {request.reason}</div>
                            {request.studentNotes && <div><b>Catatan Mahasiswa:</b> {request.studentNotes}</div>}
                            {request.adminNotes && <div><b>Catatan Admin:</b> {request.adminNotes}</div>}
                            {request.academicEvaluationNotes && <div><b>Evaluasi Akademik:</b> {request.academicEvaluationNotes}</div>}
                            {request.transferFeeAmount !== null && <div><b>Biaya Transfer:</b> {request.transferFeeAmount}</div>}
                            {request.attachmentUrl && <a href={request.attachmentUrl} target="_blank" className="db-btn ghost sm">Lampiran</a>}
                            {urls.edit && <Link href={urls.edit} className="db-btn primary sm">Perbaiki</Link>}
                        </div>
                    </section>
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Riwayat</h2></div>
                        <div className="db-card-body">
                            {request.histories.map((h) => (
                                <div key={h.id} style={{ padding: '6px 0', borderBottom: '1px solid #eee' }}>
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
