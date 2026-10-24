import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, FileText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type RequestData = {
    id: number;
    number: string;
    type: string;
    purpose: string;
    status: string;
    statusLabel: string;
    requestData: Record<string, string> | null;
    studentNotes: string | null;
    adminNotes: string | null;
    attachmentUrl: string | null;
    isDownloadable: boolean;
    histories: { id: number; from: string | null; to: string; notes: string | null; changedBy: string | null; changedAt: string | null }[];
    approval: { status: string; waitingMessage: string | null } | null;
};

type Props = {
    shell: ShellProps;
    request: RequestData;
    urls: { index: string; edit: string | null; download: string | null };
};

export default function LettersShow({ shell, request, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Surat · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Student Services" title={request.number} description={request.type} badges={[request.statusLabel]} />
                    <Link href={urls.index} className="db-btn ghost sm"><ArrowLeft size={14} /> Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <div><b>Status:</b> {request.statusLabel}</div>
                            <div><b>Tujuan:</b> {request.purpose}</div>
                            {request.studentNotes && <div><b>Catatan Mahasiswa:</b> {request.studentNotes}</div>}
                            {request.adminNotes && <div><b>Catatan Admin:</b> {request.adminNotes}</div>}
                            {request.attachmentUrl && <a href={request.attachmentUrl} target="_blank" className="db-btn ghost sm">Lampiran</a>}
                            {urls.download && (
                                <a href={urls.download} target="_blank" className="db-btn primary sm"><Download size={14} /> Unduh Surat</a>
                            )}
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
