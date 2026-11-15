// Tabel generik kit CRUD shared — pengganti PowerGrid untuk halaman React.
// Paginasi + sort + search lewat server (Inertia query string); seleksi
// bulk + export + aksi baris dikonfigurasi per halaman via props.
import type { ReactNode } from 'react';
import { ArrowDownUp, ChevronLeft, ChevronRight, Download, Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import '../../../../css/crud.css';

export type CrudColumn<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right';
    render: (row: T) => ReactNode;
};

export type CrudPageInfo = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type Props<T extends { id: number }> = {
    columns: CrudColumn<T>[];
    rows: T[];
    page: CrudPageInfo;
    sort: string;
    direction: 'asc' | 'desc';
    onSort: (key: string) => void;
    search: string;
    onSearchChange: (value: string) => void;
    onSearchSubmit: () => void;
    searchPlaceholder?: string;
    filterBar?: ReactNode;
    selected: number[];
    onToggle: (id: number) => void;
    onToggleAll: () => void;
    canDelete: boolean;
    onBulkDelete: () => void;
    bulkLabel?: string;
    canRestore?: boolean;
    onBulkRestore?: () => void;
    bulkRestoreLabel?: string;
    canUpdate: boolean;
    editUrl?: (row: T) => string;
    onRestoreRow?: (row: T) => void;
    onDeleteRow?: (row: T) => void;
    customActions?: (row: T) => ReactNode;
    showActions: boolean;
    createUrl?: string;
    canCreate?: boolean;
    createLabel?: string;
    exportHref?: string;
    exportExtra?: { label: string; href: string }[];
    extraActions?: ReactNode;
    selectable?: boolean;
    emptyText: string;
    onPage: (page: number) => void;
    perPage: number;
    perPageOptions?: number[];
    onPerPageChange: (perPage: number) => void;
};

export function CrudTable<T extends { id: number }>({
    columns, rows, page, sort, direction, onSort,
    search, onSearchChange, onSearchSubmit, searchPlaceholder = 'Cari…', filterBar,
    selected, onToggle, onToggleAll,
    canDelete, onBulkDelete, bulkLabel = 'Hapus terpilih',
    canRestore, onBulkRestore, bulkRestoreLabel = 'Pulihkan terpilih',
    canUpdate, editUrl, onRestoreRow, onDeleteRow, customActions, showActions,
    createUrl, canCreate, createLabel = 'Tambah', exportHref, exportExtra = [], extraActions,
    selectable = true,
    emptyText, onPage,
    perPage, perPageOptions = [10, 15, 25, 50, 100], onPerPageChange,
}: Props<T>) {
    const allSelected = rows.length > 0 && rows.every((row) => selected.includes(row.id));

    const selectedExportHref = (format: 'xlsx' | 'csv') => {
        if (!exportHref) return '#';
        const params = new URLSearchParams();
        selected.forEach((id) => params.append('ids[]', String(id)));
        params.append('format', format);
        return `${exportHref}&${params.toString()}`;
    };

    return (
        <div>
            <div className="crud-toolbar">
                <div className="crud-search">
                    <input
                        className="db-input"
                        type="search"
                        value={search}
                        placeholder={searchPlaceholder}
                        onChange={(e) => onSearchChange(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); onSearchSubmit(); } }}
                        aria-label={searchPlaceholder}
                    />
                    <button className="db-btn ghost" type="button" onClick={onSearchSubmit}>Cari</button>
                </div>
                {filterBar}
                <label className="db-hint" style={{ display: 'inline-flex', alignItems: 'center', gap: 7 }}>
                    Tampilkan
                    <select
                        className="db-input"
                        style={{ width: 'auto', minHeight: 36 }}
                        value={perPage}
                        onChange={(e) => onPerPageChange(Number(e.target.value))}
                        aria-label="Jumlah baris per halaman"
                    >
                        {perPageOptions.map((option) => (
                            <option key={option} value={option}>{option}</option>
                        ))}
                    </select>
                </label>
                <span className="crud-toolbar-spacer" />
                {extraActions}
                {exportHref && (
                    <details className="crud-export">
                        <summary className="db-btn ghost sm">
                            <Download size={14} /> Export
                        </summary>
                        <div className="crud-export-menu">
                            <a href={`${exportHref}&format=xlsx`}>Semua tersaring (.xlsx)</a>
                            <a href={`${exportHref}&format=csv`}>Semua tersaring (.csv)</a>
                            {selectable && selected.length > 0 && (
                                <>
                                    <a href={selectedExportHref('xlsx')}>{selected.length} terpilih (.xlsx)</a>
                                    <a href={selectedExportHref('csv')}>{selected.length} terpilih (.csv)</a>
                                </>
                            )}
                            {exportExtra.map((item) => (
                                <a key={item.label} href={item.href}>{item.label}</a>
                            ))}
                        </div>
                    </details>
                )}
                {canCreate && createUrl && (
                    <a className="db-btn primary sm" href={createUrl}>
                        <Plus size={14} /> {createLabel}
                    </a>
                )}
            </div>

            {selectable && selected.length > 0 && (
                <div className="crud-bulkbar">
                    <span>{selected.length} baris dipilih</span>
                    {canRestore && onBulkRestore && (
                        <button className="db-btn primary sm" type="button" onClick={onBulkRestore}>
                            <RotateCcw size={13} /> {bulkRestoreLabel} ({selected.length})
                        </button>
                    )}
                    {canDelete && (
                        <button className="db-btn danger sm" type="button" onClick={onBulkDelete}>
                            <Trash2 size={13} /> {bulkLabel} ({selected.length})
                        </button>
                    )}
                </div>
            )}

            <div className="crud-table-wrap">
                <table className="crud-table">
                    <thead>
                        <tr>
                            {selectable && (
                                <th style={{ width: 36 }}>
                                    <input
                                        type="checkbox"
                                        checked={allSelected}
                                        onChange={onToggleAll}
                                        aria-label="Pilih semua di halaman ini"
                                    />
                                </th>
                            )}
                            {columns.map((column) => (
                                <th key={column.key} style={column.align === 'right' ? { textAlign: 'right' } : undefined}>
                                    {column.sortable ? (
                                        <button type="button" onClick={() => onSort(column.key)} title={`Urutkan ${column.label}`}>
                                            {column.label}
                                            <ArrowDownUp size={12} style={{ opacity: sort === column.key ? 1 : 0.4 }} />
                                        </button>
                                    ) : column.label}
                                </th>
                            ))}
                            {showActions && <th style={{ textAlign: 'right' }}>Aksi</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr><td colSpan={columns.length + 2} style={{ textAlign: 'center', padding: 24 }}><span className="db-hint">{emptyText}</span></td></tr>
                        )}
                        {rows.map((row) => (
                            <tr key={row.id}>
                                {selectable && (
                                    <td>
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(row.id)}
                                            onChange={() => onToggle(row.id)}
                                            aria-label={`Pilih baris ${row.id}`}
                                        />
                                    </td>
                                )}
                                {columns.map((column) => (
                                    <td key={column.key} style={column.align === 'right' ? { textAlign: 'right' } : undefined}>
                                        {column.render(row)}
                                    </td>
                                ))}
                                {showActions && (
                                    <td>
                                        <div className="crud-row-actions">
                                            {customActions?.(row)}
                                            {canUpdate && editUrl && (
                                                <a className="db-btn ghost sm" href={editUrl(row)} title="Ubah">
                                                    <Pencil size={13} />
                                                </a>
                                            )}
                                            {canRestore && onRestoreRow && (
                                                <button className="db-btn primary sm" type="button" onClick={() => onRestoreRow(row)} title="Pulihkan">
                                                    <RotateCcw size={13} />
                                                </button>
                                            )}
                                            {canDelete && onDeleteRow && (
                                                <button className="db-btn danger sm" type="button" onClick={() => onDeleteRow(row)} title="Hapus">
                                                    <Trash2 size={13} />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {page.lastPage > 1 && (
                <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', justifyContent: 'center', gap: 14, marginTop: 18 }}>
                    <button type="button" className="db-btn ghost sm" disabled={page.currentPage <= 1} onClick={() => onPage(page.currentPage - 1)}>
                        <ChevronLeft size={14} /> Sebelumnya
                    </button>
                    <span className="db-hint" style={{ fontSize: 13 }}>
                        Halaman <b style={{ color: 'var(--db-brand)' }}>{page.currentPage}</b> dari {page.lastPage} &middot; {page.total} data
                    </span>
                    <button type="button" className="db-btn ghost sm" disabled={page.currentPage >= page.lastPage} onClick={() => onPage(page.currentPage + 1)}>
                        Berikutnya <ChevronRight size={14} />
                    </button>
                </div>
            )}
        </div>
    );
}
