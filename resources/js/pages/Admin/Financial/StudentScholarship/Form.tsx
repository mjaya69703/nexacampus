// Form alokasi beasiswa — massal saat create, tunggal saat edit (paritas Blade).
import { Head, router, useForm } from '@inertiajs/react';
import { Award, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, SelectField, TextField } from '../../../../components/Shared/Crud/fields';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type StudentOption = { id: number; label: string };

type Props = {
    shell: ShellProps;
    mode: 'create' | 'edit';
    assignment: {
        scholarship_id: string; academic_year_id: string; semester: string;
        start_date: string | null; end_date: string | null; status: string; notes: string | null;
    };
    selectedStudents: StudentOption[];
    scholarships: { id: number; name: string }[];
    years: { id: number; name: string }[];
    statuses: string[];
    urls: { index: string; submit: string; searchStudents: string };
};

const STATUS_TEXT: Record<string, string> = { active: 'Aktif Menerima', completed: 'Selesai', revoked: 'Dicabut' };

export default function StudentScholarshipForm({ shell, mode, assignment, selectedStudents: initialSelected, scholarships, years, statuses, urls }: Props) {
    const isCreate = mode === 'create';
    const form = useForm({
        scholarship_id: assignment.scholarship_id ?? '',
        academic_year_id: assignment.academic_year_id ?? '',
        semester: assignment.semester ?? '',
        start_date: assignment.start_date ?? '',
        end_date: assignment.end_date ?? '',
        status: assignment.status ?? 'active',
        notes: assignment.notes ?? '',
    });
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<StudentOption[]>([]);
    const [searching, setSearching] = useState(false);
    const [picked, setPicked] = useState<StudentOption[]>(initialSelected);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            setSearching(true);
            try {
                const response = await fetch(`${urls.searchStudents}?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                setOptions(json.options ?? []);
            } catch {
                setOptions([]);
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [query, urls.searchStudents]);

    const togglePicked = (option: StudentOption) => {
        if (!isCreate) return;
        setPicked((prev) => (prev.some((p) => p.id === option.id) ? prev.filter((p) => p.id !== option.id) : [...prev, option]));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isCreate) {
            router.post(urls.submit, { ...form.data, student_profile_ids: picked.map((p) => p.id) } as never);
        } else {
            form.put(urls.submit);
        }
    };

    const errors = form.errors as Record<string, string>;

    return (
        <AdminShell shell={shell}>
            <Head title={`${isCreate ? 'Alokasi' : 'Ubah'} Beasiswa · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={Award}
                        eyebrow="Keuangan"
                        title={isCreate ? 'Alokasi Beasiswa' : 'Ubah Alokasi'}
                        description={isCreate ? 'Satu program untuk banyak mahasiswa sekaligus.' : 'Perbarui cakupan dan status alokasi.'}
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <section className="db-card">
                        <div className="db-card-head">
                            <h2 className="db-card-title"><Award size={16} /> Data Alokasi</h2>
                        </div>
                        <div className="db-card-body">
                            <form onSubmit={submit}>
                                <div className="crud-grid">
                                    <SelectField
                                        label="Program Beasiswa"
                                        required
                                        value={form.data.scholarship_id}
                                        onChange={(e) => form.setData('scholarship_id', e.target.value)}
                                        error={errors.scholarship_id}
                                    >
                                        <option value="">Pilih program…</option>
                                        {scholarships.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Status"
                                        required
                                        value={form.data.status}
                                        onChange={(e) => form.setData('status', e.target.value)}
                                        error={errors.status}
                                    >
                                        {statuses.map((s) => <option key={s} value={s}>{STATUS_TEXT[s] ?? s}</option>)}
                                    </SelectField>
                                    <SelectField
                                        label="Tahun Akademik (opsional)"
                                        value={form.data.academic_year_id}
                                        onChange={(e) => form.setData('academic_year_id', e.target.value)}
                                        error={errors.academic_year_id}
                                        hint="Kosong = berlaku semua tahun."
                                    >
                                        <option value="">Semua tahun…</option>
                                        {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                                    </SelectField>
                                    <TextField
                                        label="Semester (opsional)"
                                        type="number"
                                        min={1}
                                        max={14}
                                        value={form.data.semester}
                                        onChange={(e) => form.setData('semester', e.target.value)}
                                        error={errors.semester}
                                        hint="Kosong = berlaku semua semester."
                                    />
                                    <TextField
                                        label="Mulai Berlaku"
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(e) => form.setData('start_date', e.target.value)}
                                        error={errors.start_date}
                                    />
                                    <TextField
                                        label="Berakhir"
                                        type="date"
                                        value={form.data.end_date}
                                        onChange={(e) => form.setData('end_date', e.target.value)}
                                        error={errors.end_date}
                                    />
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label className="crud-label" htmlFor="ss-notes">Catatan</label>
                                        <textarea
                                            id="ss-notes"
                                            className="db-input"
                                            rows={2}
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                        />
                                        {errors.notes && <div className="db-error">{errors.notes}</div>}
                                    </div>
                                </div>

                                <div style={{ marginTop: 14 }}>
                                    <span className="crud-label">
                                        {isCreate ? 'Pilih Mahasiswa (bisa banyak)' : 'Mahasiswa Penerima'} <span>*</span>
                                    </span>
                                    {isCreate && (
                                        <input
                                            className="db-input"
                                            style={{ marginBottom: 8 }}
                                            value={query}
                                            placeholder="Cari NIM, nama, atau email…"
                                            onChange={(e) => setQuery(e.target.value)}
                                            aria-label="Cari mahasiswa"
                                        />
                                    )}
                                    {isCreate && (
                                        <div style={{ border: '1px solid var(--db-line)', borderRadius: 10, padding: 10, maxHeight: 220, overflowY: 'auto' }}>
                                            {searching && <span className="db-hint">Mencari…</span>}
                                            {!searching && options.length === 0 && <div className="db-hint" style={{ textAlign: 'center' }}>Mahasiswa tidak ditemukan.</div>}
                                            {options.map((option) => (
                                                <label key={option.id} style={{ display: 'block', padding: '4px 0', fontSize: 13 }}>
                                                    <input
                                                        type="checkbox"
                                                        checked={picked.some((p) => p.id === option.id)}
                                                        onChange={() => togglePicked(option)}
                                                        style={{ marginRight: 8 }}
                                                    />
                                                    {option.label}
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                    <div className="db-hint" style={{ marginTop: 6 }}>{picked.length} mahasiswa terpilih.</div>
                                    {picked.length > 0 && (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                                            {picked.map((p) => (
                                                <span key={p.id} className="db-badge green">
                                                    {p.label}
                                                    {isCreate && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPicked((prev) => prev.filter((x) => x.id !== p.id))}
                                                            style={{ background: 'none', border: 0, cursor: 'pointer', marginLeft: 4 }}
                                                            aria-label={`Hapus ${p.label}`}
                                                        >
                                                            <X size={11} />
                                                        </button>
                                                    )}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                    {(errors.student_profile_ids || errors['student_profile_ids.0']) && (
                                        <div className="db-error">{errors.student_profile_ids ?? errors['student_profile_ids.0']}</div>
                                    )}
                                </div>

                                <FormActions
                                    cancelHref={urls.index}
                                    submitLabel={isCreate ? 'Simpan Alokasi' : 'Simpan Perubahan'}
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
