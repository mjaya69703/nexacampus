// Modal impor spreadsheet kit CRUD shared (template + unggah file).
import { FileSpreadsheet } from 'lucide-react';
import { useEffect, useState } from 'react';
import '../../../../css/dashboard.css';

type Props = {
    open: boolean;
    title: string;
    description: string;
    templateUrl: string;
    templateLabel?: string;
    accept?: string;
    processing?: boolean;
    onSubmit: (file: File) => void;
    onClose: () => void;
};

export function ImportModal({ open, title, description, templateUrl, templateLabel = 'Unduh template', accept = '.xlsx,.xls,.csv', processing, onSubmit, onClose }: Props) {
    const [file, setFile] = useState<File | null>(null);

    useEffect(() => {
        if (open) setFile(null);
    }, [open ]);

    if (!open) return null;

    return (
        <div className="db-root">
            <div className="db-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true" aria-label={title}>
                <div className="db-modal" style={{ width: 'min(30rem, 100%)', textAlign: 'left' }} onClick={(e) => e.stopPropagation()}>
                    <h3 style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <FileSpreadsheet size={18} /> {title}
                    </h3>
                    <p>{description}</p>
                    <div style={{ display: 'grid', gap: 12, marginTop: 14 }}>
                        <a className="db-btn ghost sm" href={templateUrl}>
                            {templateLabel}
                        </a>
                        <input
                            type="file"
                            accept={accept}
                            className="db-input"
                            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        />
                    </div>
                    <div className="db-modal-actions">
                        <button className="db-btn ghost" type="button" onClick={onClose} disabled={processing}>
                            Batal
                        </button>
                        <button className="db-btn primary" type="button" onClick={() => file && onSubmit(file)} disabled={!file || processing}>
                            {processing ? 'Mengimpor…' : 'Mulai impor'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

export type ImportResult = {
    success: boolean;
    created: number;
    rejected: number;
    errors: { row: number; messages: string[] }[];
} | null;

export function ImportResultBanner({ result, successText, failText }: {
    result: ImportResult;
    successText: (created: number) => string;
    failText: (rejected: number) => string;
}) {
    if (!result) return null;

    return (
        <div
            className="db-note info"
            style={result.success ? undefined : { color: 'var(--db-red)', background: 'var(--db-red-soft)' }}
        >
            <span>
                <b>{result.success ? successText(result.created) : failText(result.rejected)}</b>
                {!result.success && result.errors.length > 0 && (
                    <span style={{ display: 'block', marginTop: 6 }}>
                        {result.errors.map((err) => (
                            <span key={err.row} style={{ display: 'block' }}>
                                Baris {err.row}: {err.messages.join('; ')}
                            </span>
                        ))}
                    </span>
                )}
            </span>
        </div>
    );
}
