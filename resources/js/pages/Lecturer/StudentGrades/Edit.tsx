import { Head, Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; id: number; urls: { index: string } };

export default function StudentGradesEdit({ shell, id, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Edit Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={GraduationCap} eyebrow="Akademik" title="Edit Nilai" description={`Detail #${id}`} badges={[]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Form edit nilai mahasiswa.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}