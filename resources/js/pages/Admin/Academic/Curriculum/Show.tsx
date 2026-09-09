// Detail kurikulum — susunan MK per semester + total SKS.
import { Head } from '@inertiajs/react';
import { ArrowLeft, GraduationCap, Pencil } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    curriculum: {
        id: number; name: string; code: string | null; program: string | null;
        startYear: number | null; endYear: number | null;
        isActive: boolean; desc: string | null;
    };
    groups: {
        semester: string;
        courses: { id: number; code: string | null; name: string | null; credits: number | null; required: boolean; notes: string | null }[];
        sks: number; count: number;
    }[];
    canUpdate: boolean;
    urls: { index: string; edit: string };
};

export default function CurriculumShow({ shell, curriculum, groups, canUpdate, urls }: Props) {
    const totalSks = groups.reduce((sum, group) => sum + group.sks, 0);
    const totalCourses = groups.reduce((sum, group) => sum + group.count, 0);

    return (
        <AdminShell shell={shell}>
            <Head title={`Kurikulum ${curriculum.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title={curriculum.name}
                        description={`${curriculum.program ?? '-'} · ${curriculum.startYear ?? '-'}–${curriculum.endYear ?? '-'}`}
                        badges={[
                            `${totalCourses} MK`,
                            `${totalSks} SKS`,
                            curriculum.isActive ? 'Aktif' : 'Nonaktif',
                        ]}
                        actions={(
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {canUpdate && <a className="db-btn light" href={urls.edit}><Pencil size={15} /> Kelola</a>}
                            </>
                        )}
                    />

                    {groups.length === 0 && (
                        <section className="db-card">
                            <div className="db-card-body">
                                <p className="db-hint" style={{ margin: 0 }}>Belum ada MK dalam kurikulum ini.</p>
                            </div>
                        </section>
                    )}

                    {groups.length > 0 && (
                        <div className="cx-cards-2">
                            {groups.map((group) => (
                                <section className="db-card" key={group.semester}>
                                    <div className="db-card-head">
                                        <h2 className="db-card-title">{group.semester}</h2>
                                        <span className="db-badge">{group.count} MK · {group.sks} SKS</span>
                                    </div>
                                    <div className="db-card-body tight">
                                        {group.courses.map((course) => (
                                            <div className="db-summary" key={course.id}>
                                                <div>
                                                    <b><span className="crud-name">{course.code ?? '-'}</span> · {course.name ?? '-'}</b>
                                                    {course.notes && <small>{course.notes}</small>}
                                                </div>
                                                <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                                                    <span className={`db-badge ${course.required ? '' : 'gray'}`}>{course.required ? 'Wajib' : 'Pilihan'}</span>
                                                    <span className="db-badge green">{course.credits ?? '-'} SKS</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminShell>
    );
}
