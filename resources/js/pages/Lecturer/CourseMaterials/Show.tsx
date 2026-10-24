import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; material: any; urls: { list: string } };

export default function CourseMaterialsShow({ shell, material, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Materi · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={FileText} eyebrow="Akademik" title="Detail Materi" description={`Materi #${material}`} badges={[]} />
                    <Link href={urls.list} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Detail materi kuliah.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}