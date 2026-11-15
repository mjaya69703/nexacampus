---
name: ui-blade-darkmode
description: Aturan UI Blade, Tabler/Bootstrap, dan dark mode wajib NexaCampus. Gunakan saat membuat/mengubah view Blade, styling, komponen, tombol, atau CSS (kata kunci: blade, view, UI, dark mode, style, css, tombol, card, layout).
---

# Aturan UI NexaCampus (WAJIB — User Sangat Sensitif Soal Ini)

## Stack UI

- **Layout utama:** Bootstrap 5 (Tabler Admin theme) — `card`, `form-control`, `btn`, `row/col-*`
- **Utility tambahan:** TailwindCSS 4
- **Icon:** Font Awesome 6 (`fas fa-plus`, `fa fa-edit`)
- **Konfirmasi delete:** SweetAlert2 via `$this->js()` di Livewire
- **Flash message:** `<x-alert />` + `session()->flash('success'|'error'|'warning'|'info', '...')`

## Layout View

```php
return $this->view()->layout('layouts.app', [
    'menus' => 'Academic Management',  // konteks sidebar/breadcrumb
    'pages' => 'Judul Halaman',
]);
```

Variabel global tersedia di semua view: `$campus`, `$system`, `$user`, `$activeRole`.

## ATURAN KERAS #1: Anti-Oversize & Anti-Mini

1. ❌ **DILARANG** ukuran besar/oversize: `btn-lg`, `form-control-lg`, padding raksasa, font super besar. User sangat tidak suka desain gembrot.
2. ❌ **DILARANG** asal pakai `btn-sm`/tombol mini pada navbar/toggle/action utama. Gunakan ukuran standar (`btn`, `form-control`) yang compact dan proporsional.

```html
<button class="btn btn-primary">Primary</button>
<a class="btn btn-ghost-primary">Ghost/Outline</a>
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-danger">Delete</button>
```

## ATURAN KERAS #2: Dark Mode Global

Dark mode dikontrol terpusat via `[data-bs-theme=dark]` di `resources/css/app.css`. JANGAN buat blok dark mode custom per-komponen.

1. ❌ **DILARANG** hardcoded background terang: `bg-white`, `bg-light`, `style="background: #ffffff"`, `#f8fafc`, `#f9fafb` pada container/card — class tersebut memaksa putih `!important` dan "bocor" menyilaukan saat dark mode.
2. ✅ Gunakan `.card`, `var(--tblr-bg-surface)`, atau biarkan mengikuti tema global.
3. Class card yang sudah ternormalisasi otomatis: `.card`, `.modern-card`, `.stat-card`, `.course-card`, `.material-card`, `.grade-card`, `.advisor-card`, `.service-card`, `.finance-widget`

### Jika TERPAKSA bikin custom style baru

WAJIB sertakan override dark mode langsung di bawahnya:

```css
.my-custom-panel {
    background: white;
    border: 2px solid #e2e8f0;
}

/* WAJIB — adaptasi dark mode */
[data-bs-theme=dark] .my-custom-panel {
    background: rgba(43, 28, 67, 0.85) !important;
    border-color: rgba(167, 139, 255, 0.22) !important;
}
```

## Warna Brand

```
--app-primary: #6842f4 | bright: #8b6cff | deep: #3f249c
--app-page-bg: #f7f3ff | surface: #ffffff
```

## Setelah Mengubah CSS/Aset Frontend

```bash
npm run build   # WAJIB agar bundle CSS terupdate
```
