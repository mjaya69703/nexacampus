import { Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; sessionId: number; urls: { index: string } };

export default function AttendanceSessionsEdit({ shell, sessionId, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Edit Sesi Absensi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarCheck} eyebrow="Akademik" title="Edit Sesi Absensi" description={`Sesi #${sessionId}`} badges={[]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Form edit sesi absensi.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}