// Detail event kemahasiswaan: informasi acara dan kelola kehadiran peserta.
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft, CalendarDays, CheckCircle2, Clock3, MapPin, Pencil, Star,
    Trash2, Users, XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { ConfirmModal } from '../../../../components/Shared/Crud/ConfirmModal';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { StudentAffairsStats, toneClass } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Registration = {
    id: number;
    student: string;
    nim: string | null;
    program: string | null;
    status: string;
    statusLabel: string;
    statusTone: string;
    registeredAt: string | null;
    attendedAt: string | null;
    pointsAwarded: number;
    attendanceUrl: string;
};

type Props = {
    shell: ShellProps;
    can: { update: boolean; delete: boolean; publish: boolean };
    event: {
        id: number;
        title: string;
        categoryLabel: string;
        categoryTone: string;
        description: string | null;
        organization: string | null;
        organizer: string | null;
        location: string | null;
        startsAt: string | null;
        endsAt: string | null;
        registrationDeadline: string | null;
        quota: number | null;
        points: number;
        status: string;
        statusLabel: string;
        statusTone: string;
        isFeatured: boolean;
        coverUrl: string | null;
        creator: string | null;
        createdAt: string | null;
        updatedAt: string | null;
    };
    stats: { registered: number; attended: number; cancelled: number; quota: number | null };
    registrations: Registration[];
    options: { registrationStatuses: Record<string, string> };
    urls: {
        index: string;
        edit: string;
        delete: string;
        publish: string;
        complete: string;
        cancel: string;
    };
};

type Pending = { kind: 'delete' | 'publish' | 'complete' | 'cancel' } | null;

const ACTION_COPY: Record<Exclude<Pending, null>['kind'], { title: string; message: string; confirm: string; danger: boolean }> = {
    publish: { title: 'Publikasikan event?', message: 'Event akan tampil di portal mahasiswa dan pendaftaran dapat dibuka.', confirm: 'Ya, publikasikan', danger: false },
    complete: { title: 'Tandai event selesai?', message: 'Event akan ditandai selesai. Pastikan kehadiran peserta sudah dicatat.', confirm: 'Ya, selesaikan', danger: false },
    cancel: { title: 'Batalkan event?', message: 'Event akan dibatalkan dan tidak lagi tampil untuk pendaftaran mahasiswa.', confirm: 'Ya, batalkan', danger: true },
    delete: { title: 'Hapus event?', message: 'Event beserta data pendaftarannya akan dihapus.', confirm: 'Ya, hapus', danger: true },
};

export default function EventShow({ shell, can, event, stats, registrations, options, urls }: Props) {
    const [pending, setPending] = useState<Pending>(null);
    const [processing, setProcessing] = useState(false);

    const runAction = () => {
        if (!pending) return;
        setProcessing(true);
        const done = () => {
            setProcessing(false);
            setPending(null);
        };

        if (pending.kind === 'delete') {
            router.delete(urls.delete, { onFinish: done });
            return;
        }

        router.post(urls[pending.kind], {}, { preserveScroll: true, onFinish: done });
    };

    const copy = pending ? ACTION_COPY[pending.kind] : null;

    return (
        <AdminShell shell={shell}>
            <Head title={`${event.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Kemahasiswaan · Event"
                        title={event.title}
                        description={event.description || 'Detail event kemahasiswaan.'}
                        badges={[
                            event.categoryLabel,
                            event.statusLabel,
                            ...(event.isFeatured ? ['Sorotan'] : []),
                        ]}
                        actions={(
                            <>
                                <a className="db-btn ghost" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.update && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Edit</a>}
                                {can.publish && event.status === 'draft' && (
                                    <button className="db-btn primary" type="button" onClick={() => setPending({ kind: 'publish' })}>
                                        <CheckCircle2 size={15} /> Publikasikan
                                    </button>
                                )}
                                {can.publish && ['published', 'ongoing'].includes(event.status) && (
                                    <button className="db-btn ghost" type="button" onClick={() => setPending({ kind: 'complete' })}>
                                        <CheckCircle2 size={15} /> Tandai Selesai
                                    </button>
                                )}
                                {can.publish && !['cancelled', 'completed'].includes(event.status) && (
                                    <button className="db-btn danger" type="button" onClick={() => setPending({ kind: 'cancel' })}>
                                        <XCircle size={15} /> Batalkan
                                    </button>
                                )}
                                {can.delete && (
                                    <button className="db-btn danger" type="button" onClick={() => setPending({ kind: 'delete' })}>
                                        <Trash2 size={15} /> Hapus
                                    </button>
                                )}
                            </>
                        )}
                    />

                    <StudentAffairsStats
                        label="Statistik peserta event"
                        items={[
                            { label: 'Pendaftar', value: stats.registered, icon: Users },
                            { label: 'Hadir', value: stats.attended, icon: CheckCircle2, tone: 'green' },
                            { label: 'Dibatalkan', value: stats.cancelled, icon: XCircle, tone: 'red' },
                            { label: 'Poin per peserta', value: event.points, icon: Star, tone: 'gold' },
                        ]}
                    />

                    <div className="db-layout">
                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Informasi Acara</h2></div>
                                <div className="db-card-body">
                                    {event.coverUrl && (
                                        <img
                                            src={event.coverUrl}
                                            alt=""
                                            style={{ width: '100%', maxHeight: 240, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--db-line)', marginBottom: 14 }}
                                        />
                                    )}
                                    <div className="db-grid-2">
                                        <div className="db-fact"><span className="db-hint">Mulai</span><b>{event.startsAt || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Selesai</span><b>{event.endsAt || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Batas pendaftaran</span><b>{event.registrationDeadline || 'Tanpa batas'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Kuota</span><b>{event.quota ?? 'Tidak dibatasi'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Lokasi</span><b style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><MapPin size={13} /> {event.location || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Penyelenggara</span><b>{event.organization || event.organizer || '—'}</b></div>
                                        <div className="db-fact"><span className="db-hint">Kategori</span><b><span className={`db-badge ${toneClass(event.categoryTone)}`}>{event.categoryLabel}</span></b></div>
                                        <div className="db-fact"><span className="db-hint">Status</span><b><span className={`db-badge ${toneClass(event.statusTone)}`}>{event.statusLabel}</span></b></div>
                                    </div>
                                    {event.description && <p className="db-hint" style={{ marginTop: 12 }}>{event.description}</p>}
                                </div>
                            </section>

                            <section className="db-card">
                                <div className="db-card-head">
                                    <h2 className="db-card-title"><Users size={15} /> Peserta & Kehadiran</h2>
                                    <span className="db-badge gray">{registrations.length} pendaftar</span>
                                </div>
                                <div className="db-card-body">
                                    {registrations.length === 0 ? (
                                        <span className="db-hint">Belum ada mahasiswa yang mendaftar pada event ini.</span>
                                    ) : (
                                        <div style={{ display: 'grid', gap: 10 }}>
                                            {registrations.map((registration) => (
                                                <article key={registration.id} className="db-fact wide" style={{ display: 'grid', gap: 8 }}>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                                                        <span>
                                                            <b style={{ display: 'block', color: 'var(--db-heading)', fontSize: 13 }}>{registration.student}</b>
                                                            <small className="db-hint">
                                                                {registration.nim || '—'}{registration.program ? ` · ${registration.program}` : ''}
                                                            </small>
                                                        </span>
                                                        <span className={`db-badge ${toneClass(registration.statusTone)}`}>{registration.statusLabel}</span>
                                                    </div>
                                                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12 }}>
                                                        <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                            <Clock3 size={12} /> Daftar {registration.registeredAt || '—'}
                                                        </span>
                                                        {registration.attendedAt && (
                                                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                                                                <CheckCircle2 size={12} /> Hadir {registration.attendedAt}
                                                            </span>
                                                        )}
                                                        {registration.pointsAwarded > 0 && (
                                                            <span className="db-badge gold">{registration.pointsAwarded} poin</span>
                                                        )}
                                                        <label className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginLeft: 'auto' }}>
                                                            Kehadiran
                                                            <select
                                                                className="db-input"
                                                                style={{ width: 'auto' }}
                                                                value={registration.status}
                                                                onChange={(changeEvent) => router.post(
                                                                    registration.attendanceUrl,
                                                                    { status: changeEvent.target.value },
                                                                    { preserveScroll: true },
                                                                )}
                                                                aria-label={`Status kehadiran ${registration.student}`}
                                                            >
                                                                {Object.entries(options.registrationStatuses).map(([value, label]) => (
                                                                    <option key={value} value={value}>{label}</option>
                                                                ))}
                                                            </select>
                                                        </label>
                                                    </div>
                                                </article>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        </div>

                        <div className="db-stack">
                            <section className="db-card">
                                <div className="db-card-head"><h2 className="db-card-title">Metadata</h2></div>
                                <div className="db-card-body" style={{ display: 'grid', gap: 10 }}>
                                    <div className="db-fact"><span className="db-hint">Dibuat oleh</span><b>{event.creator || '—'}</b></div>
                                    <div className="db-fact"><span className="db-hint">Dibuat pada</span><b>{event.createdAt || '—'}</b></div>
                                    <div className="db-fact"><span className="db-hint">Diperbarui</span><b>{event.updatedAt || '—'}</b></div>
                                </div>
                            </section>

                            <div className="adm-note gold" style={{ margin: 0 }}>
                                <Star size={16} />
                                <div>
                                    <b style={{ display: 'block', marginBottom: 2, color: 'var(--db-heading)' }}>Poin otomatis.</b>
                                    Mengubah kehadiran menjadi “Hadir” akan memberi poin event kepada mahasiswa. Membatalkan kehadiran akan menarik kembali poin tersebut.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <ConfirmModal
                    open={pending !== null}
                    title={copy?.title ?? ''}
                    message={copy?.message ?? ''}
                    confirmLabel={copy?.confirm ?? 'Lanjutkan'}
                    danger={copy?.danger}
                    processing={processing}
                    onConfirm={runAction}
                    onCancel={() => setPending(null)}
                />
            </div>
        </AdminShell>
    );
}
