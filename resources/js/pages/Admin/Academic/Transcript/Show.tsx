// Detail transkrip mahasiswa — profil + snapshot semester + nilai terbaik.
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileBadge, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Result = {
    id: number; year: string; semester: number | null;
    courses: number; taken: number; passed: number;
    ips: string | null; ipk: string | null; status: string | null;
};

type Entry = {
    id: number; code: string; name: string; year: string;
    semester: number | null; credits: number;
    score: number | null; letter: string | null; point: number | null;
    result: string | null;
};

type Props = {
    shell: ShellProps;
    can: { sync: boolean };
    student: { id: number; name: string; nim: string | null; program: string | null };
    results: Result[];
    entries: Entry[];
    urls: { index: string; sync: string };
};

export default function TranscriptShow({ shell, can, student, results, entries, urls }: Props) {
    const [processing, setProcessing] = useState(false);

    const syncNow = () => {
        setProcessing(true);
        router.post(urls.sync, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Transkrip ${student.name} · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={FileBadge}
                        eyebrow="Akademik"
                        title={`Transkrip · ${student.name}`}
                        description={`${student.nim ?? '-'} · ${student.program ?? '-'}`}
                        actions={
                            <>
                                <a className="db-btn light" href={urls.index}><ArrowLeft size={15} /> Kembali</a>
                                {can.sync && (
                                    <button type="button" className="db-btn light" disabled={processing} onClick={syncNow}>
                                        <RefreshCw size={15} /> Sinkron Ulang
                                    </button>
                                )}
                            </>
                        }
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Snapshot Semester ({results.length})</h2>
                        </div>
                        <div className="db-card-body tight">
                            {results.length === 0 ? (
                                <p className="db-hint">Belum ada snapshot. Klik Sinkron Ulang untuk menghitung dari nilai yang difinalisasi.</p>
                            ) : (
                                results.map((r) => (
                                    <div className="db-summary" key={r.id}>
                                        <span className="db-hint">{r.year} · Semester {r.semester ?? '-'}</span>
                                        <span>
                                            {r.courses} MK · {r.taken} SKS ({r.passed} lulus) · IPS <b>{r.ips ?? '-'}</b> · IPK <b>{r.ipk ?? '-'}</b>
                                            {r.status ? <span className="db-badge" style={{ marginLeft: 8 }}>{r.status}</span> : null}
                                        </span>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title">Nilai Terbaik per MK ({entries.length})</h2>
                        </div>
                        <div className="db-card-body">
                            {entries.length === 0 ? (
                                <p className="db-hint">Belum ada entri transkrip.</p>
                            ) : (
                                <table className="db-table">
                                    <thead>
                                        <tr><th>Mata Kuliah</th><th>Tahun / Sem</th><th style={{ textAlign: 'center' }}>SKS</th><th style={{ textAlign: 'center' }}>Skor</th><th style={{ textAlign: 'center' }}>Huruf</th><th style={{ textAlign: 'center' }}>Hasil</th></tr>
                                    </thead>
                                    <tbody>
                                        {entries.map((e) => (
                                            <tr key={e.id}>
                                                <td><b>{e.code}</b> — {e.name}</td>
                                                <td><small className="db-hint">{e.year} · Sem {e.semester ?? '-'}</small></td>
                                                <td style={{ textAlign: 'center' }}>{e.credits}</td>
                                                <td style={{ textAlign: 'center' }}><b>{e.score ?? '-'}</b></td>
                                                <td style={{ textAlign: 'center' }}><span className="db-badge green">{e.letter ?? '-'}</span></td>
                                                <td style={{ textAlign: 'center' }}><small className="db-hint">{e.result ?? '-'}</small></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
