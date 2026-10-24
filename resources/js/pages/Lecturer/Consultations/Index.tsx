import { Head } from '@inertiajs/react';
import { Calendar, Users } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Slot = { id: number; title: string; weekday: string; time: string; slotMinutes: number; capacity: number; mode: string; location: string; isActive: boolean; upcomingCount: number };
type Appt = { id: number; student: string; nim: string; studyProgram: string; topic: string; status: string; statusLabel: string; schedule: string; mode: string; place: string };

type Props = { shell: ShellProps; stats: any; slots: Slot[]; appointments: Appt[]; urls: { index: string } };

export default function ConsultationsIndex({ shell, stats, slots, appointments, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Konsultasi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Calendar} eyebrow="Akademik" title="Konsultasi" description="Slot konsultasi dan janji temu mahasiswa." badges={[`${stats.activeSlots} slot aktif`, `${stats.requested} permintaan`]} />
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title">Slot Konsultasi</h2></div>
                        <div className="db-card-body">
                            {slots.length === 0 ? <EmptyState icon={Calendar} title="Belum ada slot" description="Buat slot konsultasi." /> : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {slots.map((s) => (
                                        <div key={s.id} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>
                                            <b>{s.title}</b> · {s.weekday} {s.time} · {s.mode} · {s.location} · kapasitas {s.capacity} · {s.upcomingCount} upcoming
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                    <section className="db-card">
                        <div className="db-card-head"><h2 className="db-card-title"><Users size={16} /> Janji Temu</h2></div>
                        <div className="db-card-body">
                            {appointments.length === 0 ? <EmptyState icon={Users} title="Belum ada janji temu" description="Janji temu mahasiswa akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {appointments.map((a) => (
                                        <div key={a.id} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>
                                            <b>{a.student}</b> ({a.nim}) · {a.topic} · {a.schedule} · {a.statusLabel}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}