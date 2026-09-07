// Pengganti <x-alert /> untuk React — baca flash bawaan Inertia sekali,
// dipakai di shell agar seluruh halaman otomatis kebagian.
import { usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { useState } from 'react';
import '../../../css/shared.css';

export type FlashProps = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};

type FlashKind = 'success' | 'error' | 'info' | 'warning';

const kinds: Array<{ key: FlashKind; icon: typeof Info }> = [
    { key: 'success', icon: CircleCheck },
    { key: 'error', icon: CircleAlert },
    { key: 'info', icon: Info },
    { key: 'warning', icon: TriangleAlert },
];

export function FlashAlert() {
    const { flash } = usePage<{ flash?: FlashProps }>().props;
    const [dismissed, setDismissed] = useState<FlashKind[]>([]);

    const items = kinds.flatMap(({ key, icon: Icon }) => {
        const message = flash?.[key];
        if (!message || dismissed.includes(key)) return [];
        return [{ key, message, Icon }];
    });

    if (items.length === 0) return null;

    return (
        <div className="public-container shared-flash" role="status" aria-live="polite">
            {items.map(({ key, message, Icon }) => (
                <div key={key} className={`shared-flash-item shared-flash-${key}`}>
                    <Icon size={16} />
                    <span>{message}</span>
                    <button type="button" onClick={() => setDismissed((current) => [...current, key])} aria-label="Tutup notifikasi">
                        <X size={14} />
                    </button>
                </div>
            ))}
        </div>
    );
}
