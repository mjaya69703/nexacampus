import { Head, Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; offeringId: number; urls: { index: string; show: string } };

export default function CourseOfferingsAttendance({ shell, offeringId, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Absensi Kelas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={CalendarCheck} eyebrow="Akademik" title="Absensi Kelas" description={`Kelas #${offeringId}`} badges={[]} />
                    <Link href={urls.show} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <EmptyState icon={CalendarCheck} title="Data absensi" description="Sesi absensi kelas akan ditampilkan di sini." />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}