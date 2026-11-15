// Detail penugasan dosen wali — read-only.
import { Head } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Pencil } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    assignment: {
        id: number; student: string; nim: string | null; program: string | null;
        advisor: string; year: string; startDate: string | null; endDate: string | null;
        isActive: boolean; notes: string | null; createdAt: string | null;
    };
    canUpdate: boolean;
    urls: { index: string; edit: string };
};

export default function AdvisorAssignmentShow({ shell, assignment, canUpdate, urls }: Props) {
    const rows: [string, string][] = [
        ['Mahasiswa', `${assignment.student}${assignment.nim ? ` · ${assignment.nim}` : ''}`],
        ['Program studi', assignment.program ?? '-'],
        ['Dosen PA', assignment.advisor],
        ['Tahun akademik', assignment.year],
        ['Rentang', `${assignment.startDate ?? '-'} → ${assignment.endDate ?? '-'}`],
        ['Status', assignment.isActive ? 'Aktif' : 'Nonaktif'],
        ['Dibuat', assignment.createdAt ?? '-'],
    ];

    return (
        <AdminShell shell={shell}>
            <Head title={`Detail Dosen Wali · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title={assignment.student}
                        description={`${assignment.nim ?? ''} · dibimbing ${assignment.advisor}`}
                        badges={[assignment.year, assignment.isActive ? 'Aktif' : 'Nonaktif']}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {canUpdate && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Ubah</a>}
                            </>
                        )}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Ringkasan penugasan</h2>
                        </div>
                        <div className="db-card-body tight">
                            {rows.map(([label, value]) => (
                                <div className="db-summary" key={label}>
                                    <div>
                                        <b>{label}</b>
                                        <small>{value}</small>
                                    </div>
                                </div>
                            ))}
                            {assignment.notes && (
                                <div className="db-summary">
                                    <div>
                                        <b>Catatan</b>
                                        <small>{assignment.notes}</small>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
