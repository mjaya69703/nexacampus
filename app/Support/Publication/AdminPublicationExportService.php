<?php

namespace App\Support\Publication;

use App\Models\Publication\Agenda;
use App\Models\Publication\Announcement;
use App\Models\Publication\Faq;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\News;
use App\Models\Publication\PublicationCategory;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdminPublicationExportService
{
    public function payload(string $resource): array
    {
        return match ($resource) {
            'categories' => $this->categories(),
            'faqs' => $this->faqs(),
            'announcements' => $this->announcements(),
            'news' => $this->news(),
            'agendas' => $this->agendas(),
            'galleries' => $this->galleries(),
            default => throw new InvalidArgumentException('Resource publikasi tidak didukung.'),
        };
    }

    public function importHeaders(string $resource): array
    {
        return $this->payload($resource)['headers'];
    }

    private function categories(): array
    {
        return $this->build('Kategori Publikasi', ['Nama', 'Slug', 'Deskripsi', 'Urutan', 'Berita', 'Agenda', 'Galeri', 'Aktif'], PublicationCategory::query()
            ->withCount(['news', 'agendas', 'galleries'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PublicationCategory $category) => [
                $category->name,
                $category->slug,
                $category->desc,
                $category->sort_order,
                $category->news_count,
                $category->agendas_count,
                $category->galleries_count,
                $this->yesNo($category->is_active),
            ]));
    }

    private function faqs(): array
    {
        return $this->build('FAQ', ['Tipe', 'Kelompok', 'Pertanyaan', 'Urutan', 'Aktif'], Faq::query()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Faq $faq) => [
                $faq->type instanceof \App\Enums\FaqType ? $faq->type->label() : (string) $faq->type,
                $faq->category,
                Str::limit(strip_tags($faq->question), 200),
                $faq->sort_order,
                $this->yesNo($faq->is_active),
            ]));
    }

    private function announcements(): array
    {
        return $this->build('Pengumuman', ['Judul', 'Target', 'Prioritas', 'Pin', 'Terbit', 'Dibaca'], Announcement::query()
            ->withCount('reads')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Announcement $announcement) => [
                $announcement->title,
                (string) ($announcement->target_type?->value ?? $announcement->target_type),
                (string) ($announcement->priority?->value ?? $announcement->priority),
                $this->yesNo($announcement->is_pinned),
                $this->yesNo($announcement->is_published),
                $announcement->reads_count,
            ]));
    }

    private function news(): array
    {
        return $this->build('Berita', ['Judul', 'Slug', 'Kategori', 'Terbit', 'Tanggal Terbit'], News::query()
            ->with('category:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (News $item) => [
                $item->title,
                $item->slug,
                $item->category?->name,
                $this->yesNo($item->is_published),
                $this->date($item->published_at),
            ]));
    }

    private function agendas(): array
    {
        return $this->build('Agenda', ['Judul', 'Kategori', 'Tanggal', 'Jam', 'Lokasi', 'Terbit'], Agenda::query()
            ->with('category:id,name')
            ->orderByDesc('event_date')
            ->get()
            ->map(fn (Agenda $agenda) => [
                $agenda->title,
                $agenda->category?->name,
                $this->date($agenda->event_date),
                $agenda->event_time ? substr((string) $agenda->event_time, 0, 5) : null,
                $agenda->location,
                $this->yesNo($agenda->is_published),
            ]));
    }

    private function galleries(): array
    {
        return $this->build('Album Galeri', ['Judul', 'Slug', 'Kategori', 'Jml Foto', 'Terbit'], GalleryAlbum::query()
            ->with('category:id,name')
            ->withCount('images')
            ->orderByDesc('id')
            ->get()
            ->map(fn (GalleryAlbum $album) => [
                $album->title,
                $album->slug,
                $album->category?->name,
                $album->images_count,
                $this->yesNo($album->is_published),
            ]));
    }

    private function build(string $title, array $headers, Collection $rows): array
    {
        return [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows->values()->all(),
        ];
    }

    private function yesNo(mixed $value): string
    {
        return $value ? 'Ya' : 'Tidak';
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d M Y');
        }

        return (string) $value;
    }
}
