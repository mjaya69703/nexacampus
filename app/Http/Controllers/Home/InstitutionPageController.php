<?php

namespace App\Http\Controllers\Home;

use App\Models\Alumni\EmployerPartner;
use App\Models\Settings\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionPageController extends \App\Http\Controllers\Controller
{
    public function profil(Request $request): Response
    {
        $campus = Campus::first();

        $profile = $campus ? [
            'name' => $campus->name,
            'logoVertikal' => $campus->logo_vertikal,
            'address' => $campus->address,
            'city' => $campus->city,
            'province' => $campus->province,
            'postalCode' => $campus->postal_code,
            'phone' => $campus->phone,
            'whatsapp' => $campus->whatsapp,
            'emailInfo' => $campus->email_info,
        ] : null;

        return $this->render($request, 'Home/Institusi/Profil', ['profile' => $profile]);
    }

    public function visiMisi(Request $request): Response
    {
        return $this->render($request, 'Home/Institusi/VisiMisi');
    }

    public function struktur(Request $request): Response
    {
        return $this->render($request, 'Home/Institusi/Struktur');
    }

    public function fasilitas(Request $request): Response
    {
        return $this->render($request, 'Home/Institusi/Fasilitas');
    }

    public function akreditasi(Request $request): Response
    {
        return $this->render($request, 'Home/Institusi/Akreditasi');
    }

    public function kerjasama(Request $request): Response
    {
        $query = EmployerPartner::where('is_active', true)
            ->whereNotNull('logo_path')
            ->orderBy('name');

        $total = $query->count();

        $partners = $query->limit(24)->get()->map(fn ($p) => [
            'name' => $p->name,
            'industry' => $p->industry,
            'website' => $p->website,
            'logo' => $p->logo_path,
        ])->all();

        return $this->render($request, 'Home/Institusi/Kerjasama', ['total' => $total, 'partners' => $partners]);
    }

    private function render(Request $request, string $component, array $props = []): Response
    {
        $user = $request->user();
        $dashboardRoute = $user ? $user->prefix.'dashboard.index' : null;
        $dashboardUrl = $dashboardRoute && Route::has($dashboardRoute) ? route($dashboardRoute) : route('auth.select-role');

        return Inertia::render($component, array_merge([
            'campus' => [
                'name' => Campus::value('name') ?? config('app.name', 'NexaCampus'),
                'logo' => Campus::value('logo_horizontal') ?? asset('storage/images/default/logo-horizontal.png'),
                'description' => \App\Models\Settings\System::value('app_description') ?? 'Sistem informasi akademik perguruan tinggi terpadu.',
            ],
            'user' => \App\Support\Inertia\PublicUser::make($user, $dashboardUrl),
            'links' => [
                'login' => route('auth.signin-index'),
                'admission' => route('root.admission.apply'),
                'admissionStatus' => route('root.admission.status'),
                'tuition' => route('root.admission.tuition'),
                'requirements' => route('root.admission.requirements'),
                'faq' => route('root.faq'),
                'contact' => route('root.kontak'),
                'announcements' => route('root.publication.announcements'),
            ],
        ], $props));
    }
}
