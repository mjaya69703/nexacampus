<?php

namespace App\Enums;

enum FinePaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum dibayar',
            self::PENDING => 'Menunggu verifikasi',
            self::VERIFIED => 'Lunas',
            self::REJECTED => 'Bukti ditolak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::UNPAID => 'amber',
            self::PENDING => 'blue',
            self::VERIFIED => 'green',
            self::REJECTED => 'red',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UNPAID => 'Denda belum dibayar. Unggah bukti transfer atau bayar langsung di perpustakaan.',
            self::PENDING => 'Bukti pembayaran sudah diunggah dan sedang menunggu verifikasi pustakawan.',
            self::VERIFIED => 'Pembayaran denda sudah diverifikasi oleh pustakawan.',
            self::REJECTED => 'Bukti pembayaran ditolak. Silakan periksa alasan penolakan lalu unggah ulang.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
