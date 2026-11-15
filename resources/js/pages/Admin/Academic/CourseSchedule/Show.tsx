// Detail jadwal kuliah — read-only.
import { Head } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Pencil } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    schedule: {
        id: number; course: string; offering: string | null; year: string | null;
        lecturer: string; day: string | null; time: string; room: string;
        type: string | null; mode: string | null; meetingLink: string | null;
        notes: string | null; isActive: boolean;
    };
    canUpdate: boolean;
    urls: { index: string; edit: string; offering: string };
};

export default function CourseScheduleShow({ shell, schedule, canUpdate, urls }: Props) {
    const rows: [string, string][] = [
        ['Kelas', schedule.course],
        ['Penawaran', schedule.offering ?? '-'],
        ['Tahun', schedule.year ?? '-'],
        ['Dosen', schedule.lecturer],
        ['Hari', schedule.day ?? '-'],
        ['Jam', schedule.time],
        ['Ruang', schedule.room],
        ['Tipe', schedule.type ?? '-'],
        ['Mode', schedule.mode ?? '-'],
        ['Link meeting', schedule.meetingLink ?? '-'],
        ['Status', schedule.isActive ? 'Aktif' : 'Nonaktif'],
        ['Catatan', schedule.notes ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Jadwal ${schedule.course} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={CalendarDays}
                        eyebrow="Akademik"
                        title={`${schedule.day ?? ''} · ${schedule.time}`}
                        description={`${schedule.course} · ${schedule.lecturer}`}
                        badges={[schedule.room, schedule.isActive ? 'Aktif' : 'Nonaktif']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Daftar</a>
                                <a className="db-btn light" href={urls.offering}>Workspace kelas</a>
                                {canUpdate && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan jadwal</h2>
                        </div>
                        <div className="db-card-body tight">
                            {rows.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <div>
                                        <b>{label}</b>
                                        <small>{value}</small>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
