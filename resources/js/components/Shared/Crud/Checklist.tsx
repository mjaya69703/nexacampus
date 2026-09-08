// Checklist dengan pencarian — pengganti TomSelect multi di kit CRUD.
// Mendukung grup + "pilih semua per grup" (dipakai form role fase 2).
import { useMemo, useState } from 'react';

export type ChecklistOption = { id: number | string; label: string };
export type ChecklistGroup = { label: string; options: ChecklistOption[] };

type Props = {
    options?: ChecklistOption[];
    groups?: ChecklistGroup[];
    selected: (number | string)[];
    onChange: (selected: (number | string)[]) => void;
    placeholder?: string;
    hint?: string;
    error?: string;
};

function matches(option: ChecklistOption, query: string): boolean {
    return option.label.toLowerCase().includes(query);
}

export function Checklist({ options = [], groups = [], selected, onChange, placeholder = 'Cari…', hint, error }: Props) {
    const [query, setQuery] = useState('');
    const q = query.trim().toLowerCase();

    const visibleGroups: ChecklistGroup[] = useMemo(() => {
        const all: ChecklistGroup[] = [
            ...groups,
            ...(options.length > 0 ? [{ label: '', options }] : []),
        ];
        if (!q) return all;
        return all
            .map((group) => ({ ...group, options: group.options.filter((o) => matches(o, q)) }))
            .filter((group) => group.options.length > 0);
    }, [groups, options, q]);

    const selectedSet = useMemo(() => new Set(selected.map((v) => String(v))), [selected]);

    const toggle = (option: ChecklistOption) => {
        if (selectedSet.has(String(option.id))) {
            onChange(selected.filter((v) => String(v) !== String(option.id)));
        } else {
            onChange([...selected, option.id]);
        }
    };

    const toggleGroup = (group: ChecklistGroup, select: boolean) => {
        const ids = new Set(group.options.map((o) => String(o.id)));
        onChange(select
            ? [...selected, ...group.options.filter((o) => !selectedSet.has(String(o.id))).map((o) => o.id)]
            : selected.filter((v) => !ids.has(String(v))));
    };

    const visibleCount = visibleGroups.reduce((n, g) => n + g.options.length, 0);

    return (
        <div>
            <div className="crud-check">
                <div className="crud-check-search">
                    <input
                        className="db-input"
                        type="search"
                        value={query}
                        placeholder={placeholder}
                        onChange={(e) => setQuery(e.target.value)}
                        aria-label={placeholder}
                    />
                </div>
                <div className="crud-check-list">
                    {visibleCount === 0 && <p className="db-hint" style={{ margin: '8px 10px' }}>Tidak ada opsi yang cocok.</p>}
                    {visibleGroups.map((group) => {
                        const groupSelected = group.options.filter((o) => selectedSet.has(String(o.id))).length;
                        return (
                            <div key={group.label || 'all'}>
                                {group.label && (
                                    <div className="crud-check-group">
                                        <span>{group.label} ({groupSelected}/{group.options.length})</span>
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group, groupSelected < group.options.length)}
                                        >
                                            {groupSelected < group.options.length ? 'Pilih semua' : 'Hapus semua'}
                                        </button>
                                    </div>
                                )}
                                {group.options.map((option) => (
                                    <label className="crud-check-item" key={option.id}>
                                        <input
                                            type="checkbox"
                                            checked={selectedSet.has(String(option.id))}
                                            onChange={() => toggle(option)}
                                        />
                                        <span>{option.label}</span>
                                    </label>
                                ))}
                            </div>
                        );
                    })}
                </div>
                <div className="crud-check-foot">
                    <span>{selected.length} dipilih</span>
                    {selected.length > 0 && (
                        <button type="button" className="db-btn ghost sm" onClick={() => onChange([])}>
                            Bersihkan
                        </button>
                    )}
                </div>
            </div>
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}
