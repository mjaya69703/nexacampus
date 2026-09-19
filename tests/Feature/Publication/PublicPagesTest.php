<?php

use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementTargetType;
use App\Models\Publication\Announcement;
use App\Models\Publication\Faq;
use App\Models\Publication\GalleryAlbum;
use App\Models\Publication\News;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function publicPagesSetup(): void
{
    App\Models\Settings\System::create([
        'app_name' => 'NexaCampus Test',
        'app_version' => '1.0.0',
        'app_description' => 'Test',
        'app_url' => 'http://localhost',
        'app_email' => 'test@localhost',
    ])->forceFill(['is_installed' => true])->save();
}

function makePublicAnnouncement(array $overrides = []): Announcement
{
    $creatorId = $overrides['created_by'] ?? App\Models\User::factory()->create()->id;
    unset($overrides['created_by']);

    return Announcement::create(array_merge([
        'title' => 'Pengumuman Publik',
        'content' => '<p>Isi.</p>',
        'target_type' => AnnouncementTargetType::GLOBAL->value,
        'target_id' => null,
        'priority' => AnnouncementPriority::NORMAL->value,
        'is_pinned' => false,
        'is_published' => true,
        'published_at' => now()->subHour(),
        'created_by' => $creatorId,
    ], $overrides));
}

it('menampilkan daftar pengumuman publik dengan paginasi', function () {
    publicPagesSetup();
    makePublicAnnouncement();

    test()->get('/pengumuman')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Publikasi/Pengumuman')
            ->has('regulars', 1)
            ->has('page')
            ->where('page.total', 1)
            ->has('priorities'));
});

it('memfilter pengumuman publik berdasarkan prioritas', function () {
    publicPagesSetup();
    makePublicAnnouncement(['title' => 'Normal']);
    makePublicAnnouncement([
        'title' => 'Mendesak', 'priority' => AnnouncementPriority::URGENT->value,
    ]);

    test()->get('/pengumuman?prioritas=urgent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('regulars', 1)
            ->where('regulars.0.title', 'Mendesak'));
});

it('menampilkan detail pengumuman publik beserta lampiran relatif', function () {
    publicPagesSetup();
    $announcement = makePublicAnnouncement();
    $announcement->update([
        'attachment_path' => 'announcements/2026/01/info.pdf',
        'attachment_name' => 'info.pdf',
        'attachment_type' => 'pdf',
    ]);

    test()->get("/pengumuman/{$announcement->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Publikasi/PengumumanShow')
            ->where('announcement.title', 'Pengumuman Publik')
            ->where('announcement.attachmentUrl', '/storage/announcements/2026/01/info.pdf')
            ->has('related'));
});

it('mengembalikan 404 untuk detail pengumuman draft atau non-global', function () {
    publicPagesSetup();
    $draft = makePublicAnnouncement(['title' => 'Draft', 'is_published' => false, 'published_at' => null]);
    $personal = makePublicAnnouncement([
        'title' => 'Personal', 'target_type' => AnnouncementTargetType::FACULTY->value, 'target_id' => 1,
    ]);

    test()->get("/pengumuman/{$draft->id}")->assertNotFound();
    test()->get("/pengumuman/{$personal->id}")->assertNotFound();
    test()->get('/pengumuman/999999')->assertNotFound();
});

it('menyembunyikan pengumuman terjadwal dari publik', function () {
    publicPagesSetup();
    $scheduled = makePublicAnnouncement([
        'title' => 'Terjadwal', 'scheduled_at' => now()->addDay(),
    ]);

    test()->get('/pengumuman')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 0));

    test()->get("/pengumuman/{$scheduled->id}")->assertNotFound();
});

it('menyajikan URL gambar berita dan galeri relatif', function () {
    publicPagesSetup();

    $creatorId = App\Models\User::factory()->create()->id;
    $news = News::create([
        'title' => 'Berita Bergambar', 'slug' => 'berita-bergambar', 'content' => '<p>Isi.</p>',
        'featured_image' => 'news/sampul.png', 'is_published' => true,
        'published_at' => now()->subHour(), 'created_by' => $creatorId,
    ]);
    $album = GalleryAlbum::create([
        'title' => 'Album', 'slug' => 'album', 'cover_image_path' => 'galleries/cover.jpg',
        'is_published' => true, 'created_by' => $creatorId,
    ]);
    $album->images()->create(['image_path' => 'galleries/a.jpg', 'sort_order' => 1, 'created_by' => $creatorId]);

    test()->get('/berita')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Publikasi/Berita')
            ->where('featured.image', '/storage/news/sampul.png'));

    test()->get("/berita/{$news->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('article.image', '/storage/news/sampul.png'));

    test()->get('/galeri')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Publikasi/Galeri')
            ->where('albums.0.cover', '/storage/galleries/cover.jpg')
            ->where('albums.0.images.0.url', '/storage/galleries/a.jpg'));
});

it('menampilkan halaman FAQ publik', function () {
    publicPagesSetup();

    Faq::create([
        'type' => 'general', 'category' => 'Umum',
        'question' => 'Apa itu?', 'answer' => '<p>Ini.</p>',
        'sort_order' => 0, 'is_active' => true,
    ]);

    test()->get('/faq')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home/Publikasi/Faq')
            ->has('faqs', 1));
});
