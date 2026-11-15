// Form penugasan dosen wali — mode tunggal & massal kohort.
import { Head, useForm } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import { useEffect, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, SwitchField, TextareaField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    assignment: {
        id?: number;
        lecturer_profile_id: number | string;
        lecturer_label?: string;
        student_profile_id: number | string;
        student_label?: string;
        academic_year_id: number | string;
        start_date: string; end_date: string;
        is_active: boolean; notes: string;
        selected_student_ids: number[];
    };
    years: { id: number | string; name: string }[];
    programs?: { id: number; name: string }[];
    entryYears?: number[];
    urls: {
        index: string; submit: string;
        searchStudents: string; searchLecturers: string; browseStudents: string;
    };
};

type BrowseRow = { id: number; nim: string | null; name: string; program: string; entryYear: number | null; semester: number | null };

export default function AdvisorAssignmentForm({ shell, mode, assignment, years, programs = [], entryYears = [], urls }: Props) {
    const isCreate = mode === 'create';
    const [assignMode, setAssignMode] = useState<'single' | 'bulk'>('single');

    const form = useForm({
        assign_mode: 'single',
        lecturer_profile_id: assignment.lecturer_profile_id as number | '',
        student_profile_id: assignment.student_profile_id as number | '',
        selected_student_ids: assignment.selected_student_ids as number[],
        academic_year_id: assignment.academic_year_id as number | '',
        start_date: assignment.start_date,
        end_date: assignment.end_date,
        is_active: assignment.is_active,
        notes: assignment.notes,
    });

    const [lecturer, setLecturer] = useState<AsyncOption | null>(
        assignment.lecturer_profile_id ? { id: Number(assignment.lecturer_profile_id), label: assignment.lecturer_label ?? '' } : null,
    );
    const [student, setStudent] = useState<AsyncOption | null>(
        assignment.student_profile_id ? { id: Number(assignment.student_profile_id), label: assignment.student_label ?? '' } : null,
    );

    // Jelajah kohort (mode massal).
    const [browseProgram, setBrowseProgram] = useState('');
    const [browseYear, setBrowseYear] = useState('');
    const [browseSemester, setBrowseSemester] = useState('');
    const [browseQ, setBrowseQ] = useState('');
    const [browseRows, setBrowseRows] = useState<BrowseRow[]>([]);
    const [browsePage, setBrowsePage] = useState(1);
    const [browseLast, setBrowseLast] = useState(1);
    const [browseTotal, setBrowseTotal] = useState(0);
    const [browsing, setBrowsing] = useState(false);

    const selectedSet = new Set(form.data.selected_student_ids);

    const loadBrowse = async (page: number) => {
        setBrowsing(true);
        try {
            const params = new URLSearchParams({
                page: String(page), per_page: '25',
                program: browseProgram, entry_year: browseYear, semester: browseSemester, q: browseQ,
            });
            const response = await fetch(`${urls.browseStudents}?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await response.json();
            setBrowseRows(json.rows ?? []);
            setBrowsePage(json.currentPage ?? 1);
            setBrowseLast(json.lastPage ?? 1);
            setBrowseTotal(json.total ?? 0);
        } finally {
            setBrowsing(false);
        }
    };

    useEffect(() => {
        if (isCreate && assignMode === 'bulk') loadBrowse(1);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [assignMode]);

    const toggleBrowse = (id: number) => {
        form.setData('selected_student_ids', selectedSet.has(id)
            ? form.data.selected_student_ids.filter((v) => v !== id)
            : [...form.data.selected_student_ids, id]);
    };

    const selectAllFilter = async () => {
        const params = new URLSearchParams({
            program: browseProgram, entry_year: browseYear, semester: browseSemester, q: browseQ, all_ids: '1',
        });
        const response = await fetch(`${urls.browseStudents}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await response.json();
        const merged = new Set([...form.data.selected_student_ids, ...(json.ids ?? [])]);
        form.setData('selected_student_ids', [...merged]);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            form.post(urls.submit);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Tambah' : 'Edit'} Dosen Wali · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={GraduationCap}
                        eyebrow="Akademik"
                        title={isCreate ? 'Tugaskan Dosen Wali' : `Edit Penugasan`}
                        description="Mode massal memilih kohort per prodi/angkatan sekaligus — bentrok dilewati otomatis."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />
                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><GraduationCap size={16} /> Form Penugasan</h2>
                            {isCreate && (
                                <div className="crud-tabs" role="tablist" aria-label="Mode penugasan">
                                    {(['single', 'bulk'] as const).map((m) => (
                                        <button
                                            key={m}
                                            type="button"
                                            role="tab"
                                            aria-selected={assignMode === m}
                                            className={`crud-tab${assignMode === m ? ' active' : ''}`}
                                            onClick={() => { setAssignMode(m); form.setData('assign_mode', m); }}
                                        >
                                            {m === 'single' ? 'Tunggal' : 'Massal'}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <AsyncSelect
                                        label="Dosen PA"
                                        required
                                        fetchUrl={urls.searchLecturers}
                                        value={lecturer}
                                        onChange={(o) => {
                                            setLecturer(o);
                                            form.setData('lecturer_profile_id', o ? Number(o.id) : '');
                                        }}
                                        placeholder="Ketik NIDN/nama dosen…"
                                        error={errors.lecturer_profile_id}
                                    />
                                    <SelectField
                                        label="Tahun akademik"
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        error={errors.academic_year_id}
                                        hint="Dikosongkan berarti berlaku umum semua tahun."
                                    >
                                        {years.map((year) => (
                                            <option key={String(year.id)} value={year.id}>{year.name}</option>
                                        ))}
                                    </SelectField>
                                    {assignMode === 'single' && (
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <AsyncSelect
                                                label="Mahasiswa"
                                                required
                                                fetchUrl={urls.searchStudents}
                                                value={student}
                                                onChange={(o) => {
                                                    setStudent(o);
                                                    form.setData('student_profile_id', o ? Number(o.id) : '');
                                                }}
                                                placeholder="Ketik NIM/nama/email…"
                                                error={errors.student_profile_id}
                                            />
                                        </div>
                                    )}
                                    <TextField
                                        label="Tanggal mulai"
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(e) => form.setData('start_date', e.target.value)}
                                        error={errors.start_date}
                                    />
                                    <TextField
                                        label="Tanggal selesai"
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                        error={errors.end_date}
                                    />
                                    <SwitchField
                                        label="Aktif"
                                        checked={form.data.is_active}
                                        onChange={(v) => form.setData('is_active', v)}
                                        error={errors.is_active}
                                        hint="Aktif dicek bentrok dengan penugasan lain."
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                            error={errors.notes}
                                        />
                                    </div>
                                </div>

                                {isCreate && assignMode === 'bulk' && (
                                    <div style={{ marginTop: 18 }}>
                                        <span className="crud-label">Pilih mahasiswa ({form.data.selected_student_ids.length} dipilih)</span>
                                        <div className="crud-toolbar">
                                            <select className="db-input" value={browseProgram} onChange={(e) => setBrowseProgram(e.target.value)} aria-label="Prodi">
                                                <option value="">Semua prodi</option>
                                                {programs.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                            </select>
                                            <select className="db-input" value={browseYear} onChange={(e) => setBrowseYear(e.target.value)} aria-label="Angkatan">
                                                <option value="">Semua angkatan</option>
                                                {entryYears.map((y) => <option key={y} value={y}>{y}</option>)}
                                            </select>
                                            <select className="db-input" value={browseSemester} onChange={(e) => setBrowseSemester(e.target.value)} aria-label="Semester">
                                                <option value="">Smt: semua</option>
                                                {[1, 2, 3, 4, 5, 6, 7, 8].map((s) => <option key={s} value={s}>Smt {s}</option>)}
                                            </select>
                                            <div className="crud-search">
                                                <input className="db-input" value={browseQ} placeholder="NIM/nama…" onChange={(e) => setBrowseQ(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); loadBrowse(1); } }} />
                                                <button className="db-btn ghost" type="button" onClick={() => loadBrowse(1)}>Cari</button>
                                            </div>
                                            <span className="crud-toolbar-spacer" />
                                            <button className="db-btn ghost sm" type="button" onClick={selectAllFilter}>
                                                Pilih semua hasil filter
                                            </button>
                                        </div>
                                        {errors.selected_student_ids && <div className="db-error">{errors.selected_student_ids}</div>}
                                        <div className="crud-table-wrap">
                                            <table className="crud-table">
                                                <thead>
                                                    <tr>
                                                        <th style={{ width: 36 }}>
                                                            <input
                                                                type="checkbox"
                                                                checked={browseRows.length > 0 && browseRows.every((r) => selectedSet.has(r.id))}
                                                                onChange={() => {
                                                                    const ids = browseRows.map((r) => r.id);
                                                                    form.setData('selected_student_ids', ids.every((id) => selectedSet.has(id))
                                                                        ? form.data.selected_student_ids.filter((id) => !ids.includes(id))
                                                                        : [...new Set([...form.data.selected_student_ids, ...ids])]);
                                                                }}
                                                                aria-label="Pilih halaman ini"
                                                            />
                                                        </th>
                                                        <th>NIM</th>
                                                        <th>Nama</th>
                                                        <th>Prodi</th>
                                                        <th>Angkatan</th>
                                                        <th>Smt</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {browsing && <tr><td colSpan={6} style={{ textAlign: 'center' }}><span className="db-hint">Memuat…</span></td></tr>}
                                                    {!browsing && browseRows.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center' }}><span className="db-hint">Atur filter lalu tekan Cari.</span></td></tr>}
                                                    {browseRows.map((row) => (
                                                        <tr key={row.id}>
                                                            <td>
                                                                <input type="checkbox" checked={selectedSet.has(row.id)} onChange={() => toggleBrowse(row.id)} aria-label={`Pilih ${row.nim}`} />
                                                            </td>
                                                            <td><span className="crud-name">{row.nim ?? '-'}</span></td>
                                                            <td>{row.name}</td>
                                                            <td>{row.program}</td>
                                                            <td>{row.entryYear ?? '-'}</td>
                                                            <td>{row.semester ?? '-'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 14, marginTop: 12 }}>
                                            <button type="button" className="db-btn ghost sm" disabled={browsePage <= 1} onClick={() => loadBrowse(browsePage - 1)}>Sebelumnya</button>
                                            <span className="db-hint">Halaman {browsePage} dari {browseLast} · {browseTotal} mahasiswa</span>
                                            <button type="button" className="db-btn ghost sm" disabled={browsePage >= browseLast} onClick={() => loadBrowse(browsePage + 1)}>Berikutnya</button>
                                        </div>
                                    </div>
                                )}

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? (assignMode === 'single' ? 'Simpan Penugasan' : `Tugaskan ${form.data.selected_student_ids.length} Mahasiswa`) : 'Simpan Perubahan'}
                                    processing={form.processing}
                                />
                            </form>
                        </div>
                    </section>
                </div>
            </div>
        </AdminShell>
    );
}
