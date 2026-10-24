import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { AdminShell, ShellProps } from '../../../components/Shared/AdminShell';
import { CrudHero } from '../../../components/Shared/Crud/CrudHero';
import { EmptyState } from '../../../components/Shared/EmptyState';
import '../../../../css/dashboard.css';
import '../../../../css/crud.css';

type Row = { id: number; student: string; nim: string; course: string; academicYear: string; status: string; category: string; reason: string; originalScore: number | null; currentScore: number | null; resolvedScore: number | null; submittedAt: string };

type Props = { shell: ShellProps; hasProfile: boolean; statusFilter: string; appealRows: Row[]; summary: { submitted: number; under_review: number; resolved: number }; urls: { index: string } };

export default function GradeAppealsIndex({ shell, hasProfile, appealRows, summary, urls }: Props) {
    return (
        <AdminShell shell={shell}>
            <Head title={`Keberatan Nilai · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero icon={AlertTriangle} eyebrow="Akademik" title="Keberatan Nilai" description="Pengajuan keberatan nilai dari mahasiswa." badges={[`${summary.submitted} baru`, `${summary.under_review} review`, `${summary.resolved} selesai`]} />
                    {!hasProfile && <div className="db-note danger">Profil dosen belum ditemukan.</div>}
                    <section className="db-card">
                        <div className="db-card-body">
                            {appealRows.length === 0 ? <EmptyState icon={AlertTriangle} title="Belum ada keberatan" description="Keberatan nilai akan muncul di sini." /> : (
                                <div style={{ display: 'grid', gap: 10 }}>
                                    {appealRows.map((r) => (
                                        <div key={r.id} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>
                                            <b>{r.student}</b> ({r.nim}) · {r.course} · {r.status} · {r.submittedAt}
                                            <div style={{ fontSize: 13, color: '#555' }}>{r.reason}</div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}