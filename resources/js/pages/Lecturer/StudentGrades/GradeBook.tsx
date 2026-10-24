import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; rows: any[]; statistics: any; distribution: any; courseOptions: any[]; yearOptions: any[]; semesterOptions: any[]; urls: { index: string } };

export default function GradeBook({ shell, rows, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Buku Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={BookOpen} eyebrow="Akademik" title="Buku Nilai" description="Rekap nilai mahasiswa." badges={[`${rows.length} baris`]} />
                    <section className="db-card">
                        <div className="db-card-body">
                            {rows.length === 0 ? <EmptyState icon={BookOpen} title="Belum ada data" description="Pilih filter untuk menampilkan data." /> : (
                                <div>{rows.length} baris</div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}