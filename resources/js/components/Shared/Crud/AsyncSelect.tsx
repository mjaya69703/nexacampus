// Combobox async kit CRUD shared — pencarian server-side dengan debounce
// (pengganti limit-8 Livewire). Single-select; untuk multi pakai Checklist
// atau grid kustom di halaman.
import { useEffect, useRef, useState } from 'react';
import '../../../../css/crud.css';

export type AsyncOption = { id: number | string; label: string };

type Props = {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    fetchUrl: string;
    value: AsyncOption | null;
    onChange: (option: AsyncOption | null) => void;
    placeholder?: string;
};

export function AsyncSelect({ label, required, error, hint, fetchUrl, value, onChange, placeholder = 'Ketik untuk mencari…' }: Props) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<AsyncOption[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    // Dropdown + fetch hanya jalan setelah user menyentuh input (tidak saat mount).
    const [touched, setTouched] = useState(false);
    const boxRef = useRef<HTMLDivElement>(null);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const close = (e: MouseEvent) => {
            if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    useEffect(() => {
        if (!touched) return;
        if (timer.current) clearTimeout(timer.current);
        timer.current = setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(`${fetchUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                setOptions(json.options ?? []);
                setOpen(true);
            } catch {
                setOptions([]);
            } finally {
                setLoading(false);
            }
        }, 300);

        return () => {
            if (timer.current) clearTimeout(timer.current);
        };
    }, [query, fetchUrl, touched]);

    return (
        <div>
            <span className="crud-label">{label} {required && <span>*</span>}</span>
            <div ref={boxRef} style={{ position: 'relative' }}>
                <div style={{ display: 'flex', gap: 8 }}>
                    <input
                        className="db-input"
                        style={{ flex: 1 }}
                        value={value ? value.label : query}
                        placeholder={value ? value.label : placeholder}
                        onChange={(e) => {
                            if (value) onChange(null);
                            setTouched(true);
                            setQuery(e.target.value);
                        }}
                        onFocus={() => {
                            setTouched(true);
                            setOpen(true);
                        }}
                        aria-label={label}
                    />
                    {value && (
                        <button className="db-btn ghost sm" type="button" onClick={() => { onChange(null); setQuery(''); }}>
                            Ganti
                        </button>
                    )}
                </div>
                {open && !value && (
                    <div className="crud-export-menu" style={{ left: 0, right: 'auto', minWidth: '100%' }}>
                        {loading && <span className="db-hint" style={{ padding: '8px 12px' }}>Mencari…</span>}
                        {!loading && options.length === 0 && <span className="db-hint" style={{ padding: '8px 12px' }}>Tidak ditemukan.</span>}
                        {!loading && options.map((option) => (
                            <a
                                key={option.id}
                                href="#"
                                onClick={(e) => {
                                    e.preventDefault();
                                    onChange(option);
                                    setOpen(false);
                                    setQuery('');
                                }}
                            >
                                {option.label}
                            </a>
                        ))}
                    </div>
                )}
            </div>
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}
