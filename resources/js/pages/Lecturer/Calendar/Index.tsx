import { Head } from '@inertiajs/react';
import { CalendarDays, Clock } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Sched = { id: number; courseCode: string; courseName: string; classLabel: string; day: string; startTime: string; endTime: string; room: string; building: string; deliveryMode: string; academicYear: string };
type Props = { shell: ShellProps; hasProfile: boolean; lecturerInfo: any; schedules: Sched[]; yearOptions: string[]; modeOptions: string[]; urls: { index: string } };

export default function CalendarIndex({ shell, hasProfile, lecturerInfo, schedules, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Kalender Mengajar · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarDays} eyebrow="Akademik" title="Kalender Mengajar" description={hasProfile ? `${lecturerInfo.name} · ${lecturerInfo.study_program}` : 'Profil dosen belum ditemukan.'} badges={[`${schedules.length} jadwal`]} />
                    {!hasProfile && <div className="db-note danger">Profil dosen belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {schedules.length === 0 ? <EmptyState icon={Clock} title="Belum ada jadwal" description="Jadwal mengajar akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {schedules.map((s) => (
                                        <div key={s.id} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>
                                            <b>{s.courseCode} - {s.courseName}</b> · {s.classLabel}
                                            <div style={{ fontSize: 13, color: '#555' }}>{s.day} {s.startTime}-{s.endTime} · {s.room} ({s.building}) · {s.deliveryMode} · {s.academicYear}</div>
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