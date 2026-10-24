import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; offeringId: number; urls: { index: string } };

export default function AssignmentsCreate({ shell, offeringId, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Buat Tugas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ClipboardList} eyebrow="Akademik" title="Buat Tugas" description={`Kelas #${offeringId}`} badges={[]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Form pembuatan tugas.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}