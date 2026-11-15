// Detail event alumni + daftar peserta (read-only).
import { Head, router } from '@inertiajs/react';
import { CalendarDays, ExternalLink, Globe2, MapPin, Pencil, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type EventDetail = {
    id: number;
    title: string;
    description: string | null;
    eventType: string;
    eventTypeLabel: string;
    eventDate: string | null;
    endDate: string | null;
    registrationDeadline: string | null;
    registrationOpen: boolean;
    location: string | null;
    isOnline: boolean;
    meetingUrl: string | null;
    maxParticipants: number | null;
    participantsCount: number;
    slotsLeft: number | null;
    posterUrl: string | null;
    isPublished: boolean;
    createdAt: string | null;
    updatedAt: string | null;
    createdBy: string | null;
    updatedBy: string | null;
};

type Participant = {
    id: number;
    name: string;
    nim: string;
    studyProgram: string;
    registeredAt: string | null;
    attendedAt: string | null;
    attended: boolean;
    notes: string | null;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean };
    event: EventDetail;
    participants: Participant[];
    urls: { index: string; edit: string | null; destroy: string };
};

function Detail({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <b style={{ color: 'var(--db-heading)', fontWeight: 600 }}>{value || '-'}</b>
        </div>
    );
}

export default function AlumniEventShow({ shell, can, event, participants, urls }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <AdminShell shell={shell}>
            <Head title={`${event.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Alumni · Event"
                        title={event.title}
                        description={event.description || 'Tidak ada deskripsi acara.'}
                        badges={[event.eventTypeLabel, event.isPublished ? 'Terbit' : 'Draft', event.registrationOpen ? 'Pendaftaran dibuka' : 'Pendaftaran ditutup']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}>Kembali ke daftar</a>
                                {can.update && urls.edit && <a className="db-btn light" href={urls.edit}><Pencil size={14} /> Edit</a>}
                                {can.delete && <button className="db-btn light" type="button" onClick={() => setConfirmDelete(true)}><Trash2 size={14} /> Hapus</button>}
                            </>
                        )}
                    />

                    <div className="crud-grid">
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><CalendarDays size={16} /> Informasi Acara</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <Detail label="Tipe event" value={event.eventTypeLabel} />
                                    <Detail label="Mulai" value={event.eventDate} />
                                    <Detail label="Selesai" value={event.endDate} />
                                    <Detail label="Batas pendaftaran" value={event.registrationDeadline} />
                                    <Detail label="Tempat" value={event.isOnline ? 'Daring / Online' : event.location} />
                                    <div>
                                        <span className="crud-label">Tautan meeting</span>
                                        {event.meetingUrl ? (
                                            <a className="db-btn ghost sm" href={event.meetingUrl} target="_blank" rel="noreferrer">
                                                <ExternalLink size={13} /> Buka tautan
                                            </a>
                                        ) : (
                                            <b style={{ color: 'var(--db-heading)', fontWeight: 600 }}>-</b>
                                        )}
                                    </div>
                                    <div>
                                        <span className="crud-label">Mode</span>
                                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                            {event.isOnline ? <Globe2 size={14} /> : <MapPin size={14} />}
                                            {event.isOnline ? 'Online' : 'Offline'}
                                        </span>
                                    </div>
                                    <div>
                                        <span className="crud-label">Kuota peserta</span>
                                        <b style={{ color: 'var(--db-heading)', fontWeight: 600 }}>
                                            {event.maxParticipants ? `${event.participantsCount} / ${event.maxParticipants}` : `${event.participantsCount} (tanpa kuota)`}
                                        </b>
                                    </div>
                                    <Detail label="Dibuat" value={[event.createdAt, event.createdBy].filter(Boolean).join(' · ')} />
                                    <Detail label="Diperbarui" value={[event.updatedAt, event.updatedBy].filter(Boolean).join(' · ')} />
                                </div>

                                {event.posterUrl && (
                                    <div style={{ marginTop: 20 }}>
                                        <span className="crud-label">Poster</span>
                                        <img
                                            src={event.posterUrl}
                                            alt={`Poster ${event.title}`}
                                            style={{ display: 'block', width: '100%', maxWidth: 420, borderRadius: 12, border: '1px solid var(--db-line)' }}
                                        />
                                    </div>
                                )}
                            </div>
                        </section>

                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Users size={16} /> Ringkasan Peserta</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="db-stats" style={{ gridTemplateColumns: 'repeat(2, minmax(0, 1fr))' }}>
                                    <div className="db-stat">
                                        <span className="db-stat-icon"><Users size={20} /></span>
                                        <div><span className="db-stat-num">{event.participantsCount}</span><span className="db-stat-label">Terdaftar</span></div>
                                    </div>
                                    <div className="db-stat">
                                        <span className="db-stat-icon green"><CalendarDays size={20} /></span>
                                        <div><span className="db-stat-num">{event.slotsLeft ?? '∞'}</span><span className="db-stat-label">Sisa kuota</span></div>
                                    </div>
                                </div>
                                <p className="db-hint" style={{ marginTop: 14 }}>
                                    Peserta terdaftar melalui portal alumni. Kehadiran dapat dicatat pada kegiatan berlangsung.
                                </p>
                            </div>
                        </section>
                    </div>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Users size={16} /> Daftar Peserta ({participants.length})</h2>
                        </div>
                        <div className="db-card-body">
                            <div className="crud-table-wrap">
                                <table className="crud-table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>NIM</th>
                                            <th>Program Studi</th>
                                            <th>Terdaftar</th>
                                            <th>Kehadiran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {participants.length === 0 && (
                                            <tr><td colSpan={5} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">Belum ada peserta terdaftar.</span></td></tr>
                                        )}
                                        {participants.map((participant) => (
                                            <tr key={participant.id}>
                                                <td><b style={{ color: 'var(--db-heading)', fontSize: 13 }}>{participant.name}</b></td>
                                                <td>{participant.nim}</td>
                                                <td>{participant.studyProgram}</td>
                                                <td>{participant.registeredAt ?? '-'}</td>
                                                <td>
                                                    <span className={`db-badge ${participant.attended ? 'green' : 'gray'}`}>
                                                        {participant.attended ? `Hadir · ${participant.attendedAt}` : 'Belum hadir'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <ConfirmModal
                    open={confirmDelete}
                    title="Hapus event alumni?"
                    message={`"${event.title}" dipindah ke sampah dan tidak tampil di portal alumni.`}
                    confirmLabel="Ya, hapus"
                    danger
                    processing={processing}
                    onConfirm={() => {
                        setProcessing(true);
                        router.delete(urls.destroy, {
                            onFinish: () => {
                                setProcessing(false);
                                setConfirmDelete(false);
                            },
                        });
                    }}
                    onCancel={() => setConfirmDelete(false)}
                />
            </div>
        </AdminShell>
    );
}
