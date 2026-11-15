// Label & format bersama untuk seluruh halaman layanan mahasiswa.
// Dipakai agar status, prioritas, dan istilah teknis selalu tampil dalam Bahasa Indonesia.

export type BadgeTone = '' | 'green' | 'amber' | 'red' | 'gray';

const STATUS_LABELS: Record<string, string> = {
    submitted: 'Diajukan',
    under_review: 'Sedang Ditinjau',
    revision_requested: 'Perlu Perbaikan',
    in_approval: 'Menunggu Persetujuan',
    approved_pending_payment: 'Menunggu Pembayaran',
    approved: 'Disetujui',
    issued: 'Diterbitkan',
    rejected: 'Ditolak',
    cancelled: 'Dibatalkan',
    activated: 'Cuti Aktif',
    returned: 'Kembali Aktif',
    applied: 'Sudah Diterapkan',
    finalized: 'Selesai',
    in_review: 'Sedang Direview',
    waiting_student: 'Menunggu Kamu',
    responded: 'Dibalas',
    resolved: 'Selesai',
    closed: 'Ditutup',
    reopened: 'Dibuka Lagi',
    pending: 'Menunggu Verifikasi',
    verified: 'Terverifikasi',
    paid: 'Lunas',
    unpaid: 'Belum Dibayar',
    partially_paid: 'Dibayar Sebagian',
    overdue: 'Jatuh Tempo',
    draft: 'Draf',
};

const STATUS_TONES: Record<string, BadgeTone> = {
    issued: 'green',
    applied: 'green',
    activated: 'green',
    finalized: 'green',
    resolved: 'green',
    closed: 'green',
    verified: 'green',
    paid: 'green',
    unpaid: 'amber',
    partially_paid: 'amber',
    overdue: 'red',
    draft: 'gray',
    approved: '',
    responded: '',
    submitted: 'amber',
    in_approval: 'amber',
    approved_pending_payment: 'amber',
    revision_requested: 'amber',
    waiting_student: 'amber',
    under_review: '',
    in_review: '',
    reopened: '',
    returned: 'gray',
    pending: 'gray',
    rejected: 'red',
    cancelled: 'red',
};

const PRIORITY_LABELS: Record<string, string> = {
    low: 'Rendah',
    normal: 'Normal',
    high: 'Tinggi',
    urgent: 'Mendesak',
};

const PRIORITY_TONES: Record<string, BadgeTone> = {
    low: 'gray',
    normal: '',
    high: 'amber',
    urgent: 'red',
};

const REASON_LABELS: Record<string, string> = {
    personal: 'Alasan Pribadi',
    medical: 'Alasan Kesehatan',
    financial: 'Alasan Finansial',
    family: 'Alasan Keluarga',
    work: 'Alasan Pekerjaan',
    other: 'Lainnya',
};

const TRANSFER_TYPE_LABELS: Record<string, string> = {
    study_program: 'Pindah Program Studi',
    faculty: 'Pindah Fakultas',
    class_type: 'Pindah Kelas',
    other: 'Lainnya',
};

const CLASS_TYPE_LABELS: Record<string, string> = {
    regular: 'Kelas Reguler',
    evening: 'Kelas Sore',
    weekend: 'Kelas Akhir Pekan',
};

const FIELD_LABELS: Record<string, string> = {
    nim: 'NIM',
    nama: 'Nama',
    name: 'Nama',
    semester: 'Semester',
    academic_year: 'Tahun Akademik',
    academic_year_id: 'Tahun Akademik',
    birth_place: 'Tempat Lahir',
    birth_date: 'Tanggal Lahir',
    address: 'Alamat',
    phone: 'Nomor Telepon',
    email: 'Email',
    parent_name: 'Nama Orang Tua/Wali',
    parent_phone: 'Telepon Orang Tua/Wali',
    graduation_year: 'Tahun Lulus',
    company_name: 'Nama Perusahaan/Instansi',
    position: 'Jabatan',
    start_date: 'Tanggal Mulai',
    end_date: 'Tanggal Selesai',
    destination: 'Tujuan',
    reason: 'Alasan',
    letter_number: 'Nomor Surat',
    recipient: 'Penerima Surat',
    company_address: 'Alamat Perusahaan/Instansi',
    internship_period: 'Periode Magang',
    study_program: 'Program Studi',
    faculty: 'Fakultas',
    class_type: 'Kelas',
    purpose: 'Tujuan / Keperluan',
    topic: 'Topik',
    supervisor: 'Dosen Pembimbing',
    credits: 'Jumlah SKS',
    gpa: 'IPK',
};

function titleize(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function statusLabel(status: string | null | undefined): string {
    if (!status) return '-';
    return STATUS_LABELS[status] ?? titleize(status);
}

export function statusTone(status: string | null | undefined): BadgeTone {
    if (!status) return 'gray';
    return STATUS_TONES[status] ?? 'gray';
}

export function priorityLabel(priority: string | null | undefined): string {
    if (!priority) return '-';
    return PRIORITY_LABELS[priority] ?? titleize(priority);
}

export function priorityTone(priority: string | null | undefined): BadgeTone {
    if (!priority) return 'gray';
    return PRIORITY_TONES[priority] ?? 'gray';
}

export function reasonLabel(reason: string | null | undefined): string {
    if (!reason) return '-';
    return REASON_LABELS[reason] ?? titleize(reason);
}

export function transferTypeLabel(type: string | null | undefined): string {
    if (!type) return '-';
    return TRANSFER_TYPE_LABELS[type] ?? titleize(type);
}

export function classTypeLabel(type: string | null | undefined): string {
    if (!type) return '-';
    return CLASS_TYPE_LABELS[type] ?? titleize(type);
}

export function verificationLabel(status: string | null | undefined): string {
    return statusLabel(status);
}

export function senderLabel(type: string | null | undefined): string {
    if (type === 'student') return 'Mahasiswa';
    if (type === 'staff' || type === 'admin') return 'Petugas';
    if (type === 'system') return 'Sistem';
    return titleize(type ?? 'Petugas');
}

export function humanizeField(key: string): string {
    return FIELD_LABELS[key] ?? titleize(key);
}

export function formatRupiah(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatFileSize(kilobytes: number | null | undefined): string {
    if (!kilobytes) return '-';
    if (kilobytes >= 1024) return `${(kilobytes / 1024).toFixed(1)} MB`;
    return `${kilobytes} KB`;
}
