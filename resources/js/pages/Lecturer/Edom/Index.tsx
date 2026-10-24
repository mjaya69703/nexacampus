import { Head } from '@inertiajs/react';
import { ClipboardList } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Props = { shell: ShellProps; hasProfile: boolean; lecturerInfo: any; periodOptions: { id: number; name: string }[]; selectedPeriodId: number | null; summary: any; courseRows: any[]; comments: any[]; review: any; urls: { index: string } };

export default function EdomIndex({ shell, hasProfile, periodOptions, courseRows, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`EDOM Dosen · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={ClipboardList} eyebrow="Akademik" title="EDOM Dosen" description="Evaluasi dosen oleh mahasiswa." badges={[`${periodOptions.length} periode`]} />
                    {!hasProfile && <div className="db-note danger">Profil dosen belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {courseRows.length === 0 ? <EmptyState icon={ClipboardList} title="Belum ada data EDOM" description="Data evaluasi akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 8 }}>
                                    {courseRows.map((r: any, i: number) => <div key={i} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>{JSON.stringify(r)}</div>)}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}