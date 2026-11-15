// Rich text editor field kit CRUD shared — Jodit v4, mesin yang sama
// dengan editor Blade (livewire:jodit-text-editor) sehingga output HTML
// identik dan konten lama aman. Dipakai jawaban FAQ, isi pengumuman,
// isi berita. Di-lazy-load dari Form pages agar bundle Index tetap ramping.
//
// Tema mengikuti dark-mode global (data-bs-theme di documentElement):
// tema gelap bawaan Jodit (jodit_theme_dark) + variabel --jd-dark-*
// di-override ke palet ungu app di crud.css agar menyatu.
import JoditEditor from 'jodit-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import 'jodit/es2021/jodit.min.css';
import '../../../../css/crud.css';

type Props = {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    id?: string;
    value: string;
    onChange: (value: string) => void;
    height?: number;
};

function isAppDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

export function RichTextField({ label, required, error, hint, id, value, onChange, height = 350 }: Props) {
    const editor = useRef(null);
    const fieldId = id ?? `crud-${label}`;
    const [dark, setDark] = useState(isAppDark);
    // Nilai live (per-keystroke) tanpa rerender — cegah ketikan hilang
    // saat editor di-remount (mis. toggle dark mode sebelum blur).
    const liveRef = useRef(value);

    useEffect(() => {
        const observer = new MutationObserver(() => setDark(isAppDark()));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        liveRef.current = value;
    }, [value]);

    const config = useMemo(
        () => ({
            readonly: false,
            height,
            theme: dark ? 'dark' : 'default',
            toolbarAdaptive: false,
            spellcheck: false,
            askBeforePasteHTML: false,
            askBeforePasteFromWord: false,
            defaultActionOnPaste: 'insert_clear_html' as const,
        }),
        [height, dark],
    );

    return (
        <div>
            <label className="crud-label" htmlFor={fieldId}>
                {label} {required && <span>*</span>}
            </label>
            <div id={fieldId} className="crud-richtext">
                <JoditEditor
                    key={dark ? 'dark' : 'light'}
                    ref={editor}
                    value={liveRef.current}
                    config={config}
                    onChange={(next) => {
                        liveRef.current = next;
                    }}
                    onBlur={(next) => {
                        liveRef.current = next;
                        onChange(next);
                    }}
                />
            </div>
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}
