<?php

use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionPeriod;
use App\Models\Academic\StudyProgram;
use Livewire\Component;

new class extends Component
{
    public string $applicationNumber = '';

    public string $email = '';

    public array $stats = [];

    public function mount(): void
    {
        $this->stats = [
            'open_intakes' => AdmissionPeriod::query()
                ->where('is_active', true)
                ->where('is_published', true)
                ->whereDate('opens_at', '<=', now())
                ->whereDate('closes_at', '>=', now())
                ->count(),
            'study_programs' => StudyProgram::query()
                ->where('is_active', true)
                ->count(),
            'submitted' => AdmissionApplication::query()
                ->where('status', 'submitted')
                ->count(),
            'under_review' => AdmissionApplication::query()
                ->where('status', 'under_review')
                ->count(),
        ];
    }

    public function checkStatus(): void
    {
        $this->validate([
            'applicationNumber' => 'required|string',
            'email' => 'required|email',
        ]);

        $application = AdmissionApplication::query()
            ->where('application_number', $this->applicationNumber)
            ->where('email', $this->email)
            ->first();

        if (! $application) {
            $this->addError('applicationNumber', 'Application not found for that number and email.');

            return;
        }

        $this->redirectRoute('admission.portal', [
            'applicationNumber' => $application->application_number,
            'token' => $application->access_token,
        ]);
    }

    public function render()
    {
        return $this->view()->layout('layouts.home', [
            'menus' => 'Admission',
            'pages' => 'Check Status',
        ]);
    }
};
?>

<div class="admission-public">
    <div class="container-xl py-4 py-lg-5">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="admission-hero mb-4">
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="admission-kicker">Admission Status</div>
                            <h1 class="admission-title">Continue from where your application left off.</h1>
                            <p class="admission-subtitle">
                                Use your application number and registered email to reopen the applicant portal securely.
                            </p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('admission.apply') }}" class="btn btn-light">
                                    <i class="fas fa-paper-plane me-2"></i> Submit New Application
                                </a>
                                <a href="{{ route('auth.signin-index') }}" class="btn btn-outline-light">
                                    <i class="fas fa-right-to-bracket me-2"></i> Login
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="admission-hero-panel">
                                <div class="text-white-50 small">Portal Access</div>
                                <div class="h3 text-white mb-3">Application number + email</div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="hero-mini-stat">
                                            <span>{{ $stats['open_intakes'] }}</span>
                                            <small>Open Intakes</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="hero-mini-stat">
                                            <span>{{ $stats['study_programs'] }}</span>
                                            <small>Programs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="admission-stat">
                            <i class="fas fa-calendar-check"></i>
                            <span>{{ $stats['open_intakes'] }}</span>
                            <small>Open Intakes</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat">
                            <i class="fas fa-graduation-cap"></i>
                            <span>{{ $stats['study_programs'] }}</span>
                            <small>Study Programs</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat">
                            <i class="fas fa-inbox"></i>
                            <span>{{ $stats['submitted'] }}</span>
                            <small>Submitted</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="admission-stat">
                            <i class="fas fa-user-clock"></i>
                            <span>{{ $stats['under_review'] }}</span>
                            <small>Under Review</small>
                        </div>
                    </div>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="admission-card">
                            <div class="admission-card-header">
                                <div>
                                    <div class="section-kicker">Secure Lookup</div>
                                    <h3>Check Admission Status</h3>
                                </div>
                                <span>Applicant portal</span>
                            </div>
                            <div class="admission-card-body">
                                <p class="text-muted">Enter the application number from your submission receipt and the email used during registration.</p>
                                <div class="mb-3">
                                    <label>Application Number</label>
                                    <input type="text" class="form-control form-control-lg" placeholder="ADM2026W1-0001" wire:model.defer="applicationNumber">
                                    @error('applicationNumber') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="mb-3">
                                    <label>Email</label>
                                    <input type="email" class="form-control form-control-lg" placeholder="name@example.com" wire:model.defer="email">
                                    @error('email') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                                <button class="btn btn-primary btn-lg w-100" wire:click="checkStatus">
                                    <i class="fas fa-magnifying-glass me-2"></i> Check Status
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="admission-card mb-3">
                            <div class="admission-card-header">
                                <div>
                                    <div class="section-kicker">Shortcuts</div>
                                    <h3>Quick Actions</h3>
                                </div>
                            </div>
                            <div class="admission-link-list">
                                <a href="{{ route('admission.apply') }}">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Submit a new application</span>
                                </a>
                                <a href="{{ route('auth.signin-index') }}">
                                    <i class="fas fa-right-to-bracket"></i>
                                    <span>Login to NexaCampus</span>
                                </a>
                            </div>
                        </div>
                        <div class="admission-card">
                            <div class="admission-card-header">
                                <div>
                                    <div class="section-kicker">What You Need</div>
                                    <h3>Before Lookup</h3>
                                </div>
                            </div>
                            <div class="admission-card-body">
                                <div class="check-item"><i class="fas fa-check"></i><span>Application number from your submission receipt</span></div>
                                <div class="check-item"><i class="fas fa-check"></i><span>Registered email address</span></div>
                                <div class="check-item"><i class="fas fa-check"></i><span>Updated documents if your previous upload was rejected</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .admission-public {
        background: linear-gradient(180deg, #f7f3ff 0%, #ffffff 42%, #f8fafc 100%);
        min-height: calc(100vh - 120px);
    }

    .admission-hero {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 58%, #16a3b8 100%);
        border-radius: 20px;
        color: #fff;
        padding: 2rem;
        box-shadow: 0 22px 55px rgba(102, 126, 234, 0.16);
    }

    .admission-kicker,
    .section-kicker {
        font-size: .76rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .admission-kicker {
        color: rgba(255, 255, 255, .72);
    }

    .admission-title {
        font-size: clamp(2rem, 4vw, 3.4rem);
        line-height: 1.05;
        margin: .5rem 0 1rem;
        letter-spacing: 0;
    }

    .admission-subtitle {
        color: rgba(255, 255, 255, .82);
        max-width: 42rem;
    }

    .admission-hero-panel,
    .hero-mini-stat {
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 16px;
        backdrop-filter: blur(10px);
    }

    .admission-hero-panel {
        padding: 1.25rem;
    }

    .hero-mini-stat {
        padding: 1rem;
    }

    .hero-mini-stat span {
        display: block;
        font-size: 1.8rem;
        font-weight: 800;
    }

    .hero-mini-stat small {
        color: rgba(255, 255, 255, .76);
    }

    .admission-stat,
    .admission-card {
        background: #fff;
        border: 1px solid rgba(102, 126, 234, .12);
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .06);
    }

    .admission-stat {
        padding: 1rem;
        min-height: 116px;
    }

    .admission-stat i {
        color: #667eea;
        font-size: 1.15rem;
        margin-bottom: .8rem;
    }

    .admission-stat span {
        display: block;
        font-size: 1.7rem;
        font-weight: 800;
        color: #1f2937;
    }

    .admission-stat small {
        color: #64748b;
        font-weight: 600;
    }

    .admission-card-header {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem;
        border-bottom: 1px solid #edf2f7;
    }

    .admission-card-header h3 {
        margin: 0;
        font-size: 1.12rem;
    }

    .admission-card-header > span {
        color: #64748b;
        font-weight: 600;
        font-size: .875rem;
    }

    .section-kicker,
    .admission-link-list i,
    .check-item i {
        color: #667eea;
    }

    .admission-card-body {
        padding: 1.25rem;
    }

    .admission-link-list a {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1rem 1.25rem;
        color: #334155;
        text-decoration: none;
        border-bottom: 1px solid #edf2f7;
        font-weight: 700;
    }

    .admission-link-list a:last-child {
        border-bottom: 0;
    }

    .check-item {
        display: flex;
        gap: .75rem;
        align-items: flex-start;
        padding: .55rem 0;
        color: #334155;
        font-weight: 600;
    }

    @media (max-width: 767.98px) {
        .admission-hero {
            padding: 1.5rem;
        }

        .admission-card-header {
            flex-direction: column;
        }
    }
</style>
