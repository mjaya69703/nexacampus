// Konfirmasi pendaftaran event alumni (Inertia React).
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, MapPin, Ticket } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { TextareaField } from '../../../components/Shared/Crud/fields';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type AlumniEvent = {
    id: number;
    title: string;
    eventTypeLabel: string | null;
    eventDate: string | null;
    location: string | null;
    isOnline: boolean;
};

type Props = {
    shell: ShellProps;
    event: AlumniEvent;
    urls: { show: string; store: string };
};

export default function EventRegister({ shell, event, urls }: Props) {
    const form = useForm<{ notes: string }>({ notes: '' });

    const submit = (submitEvent: FormEvent) => {
        submitEvent.preventDefault();
        form.post(urls.store, { preserveScroll: true });
    };

    const errors = form.errors as Record<string, string | undefined>;
    const error = errors.notes ?? errors.event;

    return (
        <AdminShell shell={shell}>
            <Head title={`Daftar ${event.title} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Ticket}
                        eyebrow="Konfirmasi pendaftaran"
                        title={event.title}
                        description="Periksa kembali detail event sebelum menyelesaikan pendaftaran."
                        badges={[event.eventTypeLabel ?? 'Event alumni']}
                        actions={(
                            <a className="db-btn light" href={urls.show}><ArrowLeft size={15} /> Kembali</a>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><CalendarDays size={16} /> Ringkasan Event</h2>
                        </div>
                        <div className="db-card-body" style={{ display: 'grid', gap: 8 }}>
                            <strong style={{ color: 'var(--db-heading)', fontSize: 16 }}>{event.title}</strong>
                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <CalendarDays size={13} /> {event.eventDate ?? '-'}
                            </span>
                            <span className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                                <MapPin size={13} /> {event.location ?? (event.isOnline ? 'Online' : '-')}
                            </span>
                            {event.eventTypeLabel && <span className="db-badge">{event.eventTypeLabel}</span>}
                        </div>
                    </section>

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title"><Check size={16} /> Data Pendaftaran</h2>
                            </div>
                            <div className="db-card-body" style={{ display: 'grid', gap: 14 }}>
                                <TextareaField
                                    label="Catatan untuk panitia"
                                    value={form.data.notes}
                                    onChange={(changeEvent) => form.setData('notes', changeEvent.target.value)}
                                    error={error}
                                    hint="Opsional — misalnya kebutuhan khusus, pertanyaan, atau info tambahan."
                                    placeholder="Tulis catatan (opsional)..."
                                    rows={3}
                                    maxLength={1000}
                                />
                                <div className="db-note info">
                                    <Ticket size={15} />
                                    <span>Dengan mendaftar, kamu menyetujui kehadiran dan bersedia dihubungi panitia event.</span>
                                </div>
                            </div>
                        </section>

                        <div className="crud-actions">
                            <a className="db-btn ghost" href={urls.show}>Batal</a>
                            <button className="db-btn primary" type="submit" disabled={form.processing}>
                                <Check size={15} /> {form.processing ? 'Mendaftar...' : 'Konfirmasi Pendaftaran'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
