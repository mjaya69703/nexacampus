// Field partials kit CRUD shared — gaya db-* (lihat dashboard.css).
import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import '../../../../css/crud.css';

type Base = {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
};

export function TextField({ label, required, error, hint, id, ...props }: Base & InputHTMLAttributes<HTMLInputElement>) {
    const fieldId = id ?? `crud-${label}`;
    return (
        <div>
            <label className="crud-label" htmlFor={fieldId}>
                {label} {required && <span>*</span>}
            </label>
            <input id={fieldId} className="db-input" {...props} />
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}

export function SelectField({ label, required, error, hint, id, children, ...props }: Base & SelectHTMLAttributes<HTMLSelectElement>) {
    const fieldId = id ?? `crud-${label}`;
    return (
        <div>
            <label className="crud-label" htmlFor={fieldId}>
                {label} {required && <span>*</span>}
            </label>
            <select id={fieldId} className="db-input" {...props}>
                {children}
            </select>
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}

export function SwitchField({ label, hint, error, checked, onChange, disabled }: {
    label: string;
    hint?: string;
    error?: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}) {
    return (
        <div>
            <span className="crud-label">{label}</span>
            <label style={{ display: 'inline-flex', alignItems: 'center', gap: 10, cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.6 : 1 }}>
                <input
                    type="checkbox"
                    checked={checked}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.checked)}
                    style={{ width: 18, height: 18, accentColor: 'var(--db-brand)', cursor: disabled ? 'not-allowed' : 'pointer' }}
                />
                <span style={{ fontSize: 13, color: 'var(--db-ink)' }}>{checked ? 'Aktif' : 'Nonaktif'}</span>
            </label>
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}

export function TextareaField({ label, required, error, hint, id, ...props }: Base & TextareaHTMLAttributes<HTMLTextAreaElement>) {
    const fieldId = id ?? `crud-${label}`;
    return (
        <div>
            <label className="crud-label" htmlFor={fieldId}>
                {label} {required && <span>*</span>}
            </label>
            <textarea id={fieldId} className="db-input" rows={3} {...props} />
            {error && <div className="db-error">{error}</div>}
            {hint && <div className="crud-hint">{hint}</div>}
        </div>
    );
}

export function FormActions({ cancelHref, submitLabel, processing, extra }: {
    cancelHref: string;
    submitLabel: string;
    processing?: boolean;
    extra?: ReactNode;
}) {
    return (
        <div className="crud-actions">
            {extra}
            <a className="db-btn ghost" href={cancelHref}>Batal</a>
            <button className="db-btn primary" type="submit" disabled={processing}>
                {submitLabel}
            </button>
        </div>
    );
}
