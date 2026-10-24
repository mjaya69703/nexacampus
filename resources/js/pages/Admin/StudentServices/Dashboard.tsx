import { Head } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; stats: any; urls: { index: string } };

export default function StudentServicesDashboard({ shell, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Dashboard Layanan · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={LayoutDashboard} eyebrow="Student Services" title="Dashboard Layanan Mahasiswa" description="Ringkasan layanan mahasiswa." badges={[]} />
                    <section className="db-card">
                        <div className="db-card-body">Dashboard layanan mahasiswa admin.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}