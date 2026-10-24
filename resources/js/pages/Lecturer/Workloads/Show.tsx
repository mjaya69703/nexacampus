import { Head, Link } from '@inertiajs/react';
import { Briefcase } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; id: number; urls: { index: string } };

export default function WorkloadsShow({ shell, id, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Detail BKD · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={Briefcase} eyebrow="Akademik" title="Detail Beban Kerja" description={`Pengajuan #${id}`} badges={[]} />
                    <Link href={urls.index} className="db-btn ghost sm">← Kembali</Link>
                    <section className="db-card">
                        <div className="db-card-body">Detail beban kerja dosen.</div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}