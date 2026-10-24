import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; offeringId: number; urls: { list: string } };

export default function CourseMaterialsIndex({ shell, offeringId, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Materi Kelas · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Akademik" title="Materi Kuliah" description={`Kelas #${offeringId}`} badges={[]} />
                    <Link href={urls.list} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">
                            <EmptyState icon={FileText} title="Materi" description="Materi kuliah kelas akan ditampilkan di sini." />
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}