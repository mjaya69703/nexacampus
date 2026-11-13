// Form catat peminjaman perpustakaan (admin).
import { Head, useForm } from '@inertiajs/react';
import { BookMarked, CalendarDays, Info } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { AdminShell, ShellProps } from '../../../../components/Shared/AdminShell';
import { AsyncSelect, type AsyncOption } from '../../../../components/Shared/Crud/AsyncSelect';
import { CrudHero } from '../../../../components/Shared/Crud/CrudHero';
import { FormActions, TextareaField } from '../../../../components/Shared/Crud/fields';
import { formatRupiah } from '../shared';
import '../../../../../css/dashboard.css';
import '../../../../../css/crud.css';

type Props = {
    shell: ShellProps;
    preselectedCopy: {
        id: number;
        label: string;
        code: string;
        bookTitle: string | null;
        author: string | null;
    } | null;
    defaults: { borrowed_at: string; due_at: string; notes: string };
    options: { loanDays: number; finePerDay: number };
    urls: { index: string; submit: string; searchMembers: string; searchCopies: string };
};

export default function LoanCreate({ shell, preselectedCopy, defaults, options, urls }: Props) {
    const [member, setMember] = useState<AsyncOption | null>(null);
    const [copy, setCopy] = useState<AsyncOption | null>(preselectedCopy ? {
        id: preselectedCopy.id,
        label: preselectedCopy.label,
        meta: { author: preselectedCopy.author ?? '', code: preselectedCopy.code },
    } : null);

    const form = useForm({
        user_id: '',
        book_copy_id: preselectedCopy ? String(preselectedCopy.id) : '',
        borrowed_at: defaults.borrowed_at,
        due_at: defaults.due_at,
        notes: defaults.notes,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(urls.submit, { preserveScroll: true });
    };

    const onBorrowedChange = (value: string) => {
        form.setData('borrowed_at', value);

        if (!value) return;

        const borrowed = new Date(`${value}T00:00:00`);
        borrowed.setDate(borrowed.getDate() + options.loanDays);
        const month = String(borrowed.getMonth() + 1).padStart(2, '0');
        const day = String(borrowed.getDate()).padStart(2, '0');
        form.setData('due_at', `${borrowed.getFullYear()}-${month}-${day}`);
    };

    return (
        <AdminShell shell={shell}>
            <Head title={`Catat Peminjaman · ${shell.appName}`} />
            <div className="db-root">
                <div className="db-stack">
                    <CrudHero
                        icon={BookMarked}
                        eyebrow="Perpustakaan"
                        title="Catat Peminjaman"
                        description="Cari anggota dan eksemplar yang tersedia. Sistem memeriksa batas pinjam, denda tertunggak, dan reservasi."
                        actions={<a className="db-btn light" href={urls.index}>Kembali ke daftar</a>}
                    />

                    <div className="db-note info">
                        <Info size={15} />
                        <span>
                            Masa pinjam <b>{options.loanDays} hari</b>. Denda keterlambatan <b>{formatRupiah(options.finePerDay)}/hari</b>.
                            Eksemplar berstatus <b>Dipesan</b> hanya dapat dipinjam oleh anggota yang memegang reservasi.
                        </span>
                    </div>

                    <form onSubmit={submit}>
                        <section className="db-card">
                            <div className="db-card-head">
                                <h2 className="db-card-title">Detail Peminjaman</h2>
                            </div>
                            <div className="db-card-body">
                                <div className="crud-grid">
                                    <AsyncSelect
                                        label="Anggota"
                                        required
                                        fetchUrl={urls.searchMembers}
                                        value={member}
                                        onChange={(option) => {
                                            setMember(option);
                                            form.setData('user_id', option ? String(option.id) : '');
                                        }}
                                        error={form.errors.user_id}
                                        placeholder="Cari nama, NIM, NIDN, atau username…"
                                    />
                                    <AsyncSelect
                                        label="Eksemplar buku"
                                        required
                                        fetchUrl={urls.searchCopies}
                                        value={copy}
                                        onChange={(option) => {
                                            setCopy(option);
                                            form.setData('book_copy_id', option ? String(option.id) : '');
                                        }}
                                        error={form.errors.book_copy_id}
                                        placeholder="Cari kode eksemplar, judul, atau ISBN…"
                                    />
                                    <div>
                                        <label className="crud-label" htmlFor="loan-borrowed">Tanggal pinjam <span>*</span></label>
                                        <input
                                            id="loan-borrowed"
                                            type="date"
                                            className="db-input"
                                            value={form.data.borrowed_at}
                                            onChange={(event) => onBorrowedChange(event.target.value)}
                                        />
                                        {form.errors.borrowed_at && <div className="db-error">{form.errors.borrowed_at}</div>}
                                    </div>
                                    <div>
                                        <label className="crud-label" htmlFor="loan-due">Jatuh tempo <span>*</span></label>
                                        <input
                                            id="loan-due"
                                            type="date"
                                            className="db-input"
                                            value={form.data.due_at}
                                            onChange={(event) => form.setData('due_at', event.target.value)}
                                        />
                                        {form.errors.due_at && <div className="db-error">{form.errors.due_at}</div>}
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <TextareaField
                                            label="Catatan"
                                            value={form.data.notes}
                                            onChange={(event) => form.setData('notes', event.target.value)}
                                            error={form.errors.notes}
                                            placeholder="Catatan tambahan untuk peminjaman ini…"
                                        />
                                    </div>
                                </div>

                                <div className="db-note muted" style={{ marginTop: 14 }}>
                                    <CalendarDays size={15} />
                                    <span>Jatuh tempo otomatis dihitung {options.loanDays} hari dari tanggal pinjam, tetapi tetap dapat disesuaikan.</span>
                                </div>
                            </div>
                        </section>

                        <FormActions
                            cancelHref={urls.index}
                            submitLabel="Simpan Peminjaman"
                            processing={form.processing}
                        />
                    </form>
                </div>
            </div>
        </AdminShell>
    );
}
