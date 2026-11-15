// Detail reservasi + approval — pola Show academic.
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, Check, Pencil, Trash2, Undo2, X } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Reservation = {
    id: number; reservationNumber: string; title: string; purpose: string | null;
    room: string | null; building: string | null; requester: string;
    date: string | null; time: string; attendeeCount: number;
    status: string; statusLabel: string; rejectionReason: string | null;
    approver: string | null; approvedAt: string | null; conflict: string | null;
    createdAt: string | null;
};

type Props = {
    shell: ShellProps;
    reservation: Reservation;
    can: { update: boolean; delete: boolean; approve: boolean };
    urls: { index: string; edit: string; destroy: string; approve: string; reject: string; cancel: string };
};

export default function ReservationShow({ shell, reservation, can, urls }: Props) {
    const [pending, setPending] = useState<'approve' | 'reject' | 'cancel' | 'delete' | null>(null);
    const [processing, setProcessing] = useState(false);
    const rejectForm = useForm({ rejection_reason: '' });

    const isPending = reservation.status === 'pending';
    const isActive = reservation.status === 'pending' || reservation.status === 'approved';

    const confirm = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending === 'approve') {
            router.post(urls.approve, {}, { preserveScroll: true, onFinish: done });
        } else if (pending === 'cancel') {
            router.post(urls.cancel, {}, { preserveScroll: true, onFinish: done });
        } else if (pending === 'delete') {
            router.delete(urls.destroy, { onFinish: done });
        } else {
            rejectForm.post(urls.reject, {
                preserveScroll: true,
                onFinish: done,
            });
        }
    };

    const copy = {
        approve: {
            title: 'Setujui reservasi?',
            body: `Reservasi ${reservation.reservationNumber} disetujui.${reservation.conflict ? ' PERHATIAN: ' + reservation.conflict : ''}`,
            confirm: 'Ya, setujui',
            danger: false,
        },
        reject: {
            title: 'Tolak reservasi?',
            body: `Reservasi ${reservation.reservationNumber} ditolak. Alasan wajib diisi di bawah.`,
            confirm: 'Ya, tolak',
            danger: true,
        },
        cancel: {
            title: 'Batalkan reservasi?',
            body: `Reservasi ${reservation.reservationNumber} dibatalkan.`,
            confirm: 'Ya, batalkan',
            danger: true,
        },
        delete: {
            title: 'Hapus reservasi?',
            body: `Reservasi ${reservation.reservationNumber} dipindah ke sampah.`,
            confirm: 'Ya, hapus',
            danger: true,
        },
    }[pending ?? 'approve'];

    return (
        <AdminShell shell={shell}>
            <Head title={`${reservation.reservationNumber} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarCheck}
                        eyebrow="Kampus · Detail Reservasi"
                        title={reservation.title}
                        description={`${reservation.reservationNumber}${reservation.room ? ` · ${reservation.room}` : ''} · ${reservation.requester}`}
                        badges={[reservation.statusLabel]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && (
                                    <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>
                                )}
                            </>
                        )}
                    />

                    {reservation.conflict && isPending && (
                        <div className="db-note" style={{ color: 'var(--db-red)', background: 'var(--db-red-soft)' }}>
                            <b>Bentrok terdeteksi:</b> {reservation.conflict}
                        </div>
                    )}

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Detail Permohonan</h2>
                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                {can.approve && isPending && (
                                    <>
                                        <button className="db-btn ghost sm" type="button" onClick={() => setPending('approve')}>
                                            <Check size={14} /> Setujui
                                        </button>
                                        <button className="db-btn ghost sm" type="button" onClick={() => setPending('reject')}>
                                            <X size={14} /> Tolak
                                        </button>
                                    </>
                                )}
                                {can.update && isActive && (
                                    <button className="db-btn ghost sm" type="button" onClick={() => setPending('cancel')}>
                                        <Undo2 size={14} /> Batalkan
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
                                    <div>{reservation.room ?? '-'}{reservation.building ? ` (${reservation.building})` : ''}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Jadwal</span>
                                    <div>{reservation.date ?? '-'}, {reservation.time}</div>
                                </div>
                                <div>
                                    <span className="crud-label">Peserta</span>
                                    <div>{reservation.attendeeCount} orang</div>
                                </div>
                                <div>
                                    <span className="crud-label">Pemohon</span>
                                    <div>{reservation.requester}</div>
                                </div>
                                <div style={{ gridColumn: '1 / -1' }}>
                                    <span className="crud-label">Tujuan</span>
                                    <div>{reservation.purpose ?? '-'}</div>
                                </div>
                                {reservation.status === 'rejected' && (
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <span className="crud-label">Alasan penolakan</span>
                                        <div>{reservation.rejectionReason ?? '-'}</div>
                                    </div>
                                )}
                                {reservation.approver && (
                                    <div>
                                        <span className="crud-label">Disetujui oleh</span>
                                        <div>{reservation.approver}{reservation.approvedAt ? ` · ${reservation.approvedAt}` : ''}</div>
                                    </div>
                                )}
                                <div>
                                    <span className="crud-label">Dibuat</span>
                                    <div className="db-hint">{reservation.createdAt ?? '-'}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy.title}
                    message={copy.body}
                    confirmLabel={copy.confirm}
                    danger={copy.danger}
                    processing={processing || rejectForm.processing}
                    onConfirm={confirm}
                    onCancel={() => setPending(null)}
                >
                    {pending === 'reject' && (
                        <div style={{ marginTop: 12, textAlign: 'left' }}>
                            <TextareaField
                                label="Alasan penolakan"
                                required
                                rows={3}
                                value={rejectForm.data.rejection_reason}
                                onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                                error={rejectForm.errors.rejection_reason}
                            />
                        </div>
                    )}
                </ConfirmModal>
            </div>
        </AdminShell>
    );
}
