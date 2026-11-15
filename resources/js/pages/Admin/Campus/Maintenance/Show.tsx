// Detail tiket kerusakan + penyelesaian — pola Show academic.
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Ticket = {
    id: number; ticketNumber: string; title: string; description: string;
    room: string | null; building: string | null; asset: string | null; assetCode: string | null;
    reporter: string; reporterEmail: string | null; assignee: string | null;
    priority: string; priorityLabel: string; status: string; statusLabel: string;
    resolutionNotes: string | null; repairCost: string | null; resolvedAt: string | null;
    createdAt: string | null;
};

type Props = {
    shell: ShellProps;
    ticket: Ticket;
    can: { update: boolean; delete: boolean; resolve: boolean };
    urls: { index: string; edit: string; destroy: string; resolve: string };
};

export default function MaintenanceShow({ shell, ticket, can, urls }: Props) {
    const [pending, setPending] = useState<'resolve' | 'delete' | null>(null);
    const [processing, setProcessing] = useState(false);
    const resolveForm = useForm({ resolution_notes: '', repair_cost: '' });

    const canResolve = ticket.status !== 'resolved' && ticket.status !== 'closed';

    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending === 'delete') {
            router.delete(urls.destroy, { onFinish: done });
        } else {
            resolveForm.post(urls.resolve, { preserveScroll: true, onFinish: done });
        }
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`${ticket.ticketNumber} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Wrench}
                        eyebrow="Kampus · Detail Tiket"
                        title={ticket.title}
                        description={`${ticket.ticketNumber} · dilaporkan ${ticket.createdAt ?? '-'}`}
                        badges={[ticket.priorityLabel, ticket.statusLabel]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>
                                )}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Detail Laporan</h2>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {can.resolve && canResolve && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('resolve')}>
                                        <Wrench size={14} /> Tandai selesai
                                    </button>
                                )}
                                {can.delete && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('delete')}>
                                        <Trash2 size={14} /> Hapus
                                    </button>
                                )}
                            </div>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-grid">
                                <div>
                                    <span className="crud-label">Ruangan</span>
                                    <div>{ticket.room ?? '-'}{ticket.building ? ` (${ticket.building})` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Aset terkait</span>
                                    <div>{ticket.asset ?? '-'}{ticket.assetCode ? ` (${ticket.assetCode})` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Pelapor</span>
                                    <div>{ticket.reporter}{ticket.reporterEmail ? ` (${ticket.reporterEmail})` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Teknisi</span>
                                    <div>{ticket.assignee ?? 'Belum ditugaskan'}</div>
                                </div>
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <span className="crud-label">Rincian kerusakan</span>
                                    <div style={{ whiteSpace: 'pre-wrap' }}>{ticket.description}</div>
                                </div>
                                {ticket.resolutionNotes && (
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Catatan penyelesaian</span>
                                        <div>{ticket.resolutionNotes}</div>
                                    </div>
                                )}
                                <div>
                                    <span className="crud-label">Biaya perbaikan</span>
                                    <div>{ticket.repairCost ?? '-'}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Diselesaikan</span>
                                    <div>{ticket.resolvedAt ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={pending === 'resolve' ? 'Tandai selesai?' : 'Hapus tiket?'}
                    message={pending === 'resolve'
                        ? `Tiket ${ticket.ticketNumber} selesai. Aset terkait kembali baik + aktif. Catatan wajib diisi di bawah.`
                        : `Tiket ${ticket.ticketNumber} dipindah ke sampah.`}
                    confirmLabel={pending === 'resolve' ? 'Ya, selesai' : 'Ya, hapus'}
                    danger={pending !== 'resolve'}
                    processing={processing || resolveForm.processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                >
                    {pending === 'resolve' && (
                        <div style={{ marginTop: 12, textAlign: 'left', display: 'grid', gap: 12 }}>
                            <TextareaField
                                label="Catatan penyelesaian"
                                required
                                rows={3}
                                value={resolveForm.data.resolution_notes}
                                onChange={(e) => resolveForm.setData('resolution_notes', e.target.value)}
                                error={resolveForm.errors.resolution_notes}
                            />
                            <TextField
                                label="Biaya perbaikan (Rp)"
                                type="number"
                                min={0}
                                value={resolveForm.data.repair_cost}
                                onChange={(e) => resolveForm.setData('repair_cost', e.target.value)}
                                error={resolveForm.errors.repair_cost}
                            />
                        </div>
                    )}
                </ConfirmModal>
            </div>
        </AdminShell>
    );
}
