import { Head, Link } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; id: number; urls: { index: string; show: string } };

export default function AssignmentsEdit({ shell, id, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Edit Tugas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ClipboardList} eyebrow="Akademik" title="Edit Tugas" description={`Tugas #${id}`} badges={[]} />
                    <Link href={urls.show} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Form edit tugas.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}