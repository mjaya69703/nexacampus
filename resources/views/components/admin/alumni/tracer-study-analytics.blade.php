@extends('layouts.app')

@section('content')
<div class="container-xl">
    <x-alert />

    <a href="{{ route('admin.alumni.tracer-study.show', ['id' => $campaign->id]) }}" class="btn btn-outline-secondary mb-4">
        <i class="fas fa-arrow-left me-1"></i> Kembali ke Detail Kampanye
    </a>

    {{-- Header --}}
    <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 16px;">
        <div class="card-body p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="d-flex align-items-start gap-3">
                    <div style="width: 72px; height: 72px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 2rem; backdrop-filter: blur(10px);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.9rem; opacity: 0.88; margin-bottom: 0.35rem;">Analytics Tracer Study</div>
                        <h1 class="h2 mb-2" style="font-weight: 800;">{{ $campaign->title }}</h1>
                        <div style="opacity: 0.9;">
                            {{ $campaign->start_date->format('d M Y') }} - {{ $campaign->end_date->format('d M Y') }}
                            @if ($campaign->academicYear)
                                &middot; {{ $campaign->academicYear->name }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="{{ route('admin.alumni.tracer-study.export', ['id' => $campaign->id, 'format' => 'csv']) }}">
                            <i class="fas fa-file-csv me-2"></i> CSV
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.alumni.tracer-study.export', ['id' => $campaign->id, 'format' => 'xlsx']) }}">
                            <i class="fas fa-file-excel me-2"></i> Excel
                        </a>
                        <a class="dropdown-item" href="{{ route('admin.alumni.tracer-study.export', ['id' => $campaign->id, 'format' => 'pdf']) }}">
                            <i class="fas fa-file-pdf me-2"></i> PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Overview Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span style="width: 48px; height: 48px; background: #e0e7ff; color: #4f46e5; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-paper-plane"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Terkirim</div>
                        <div class="h3 mb-0 fw-bold">{{ $campaign->total_sent }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span style="width: 48px; height: 48px; background: #dcfce7; color: #16a34a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-check-circle"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Direspon</div>
                        <div class="h3 mb-0 fw-bold">{{ $campaign->total_responded }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span style="width: 48px; height: 48px; background: #fef3c7; color: #d97706; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-percentage"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Response Rate</div>
                        <div class="h3 mb-0 fw-bold">{{ $campaign->responseRate() }}%</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span style="width: 48px; height: 48px; background: #fce7f3; color: #db2777; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="fas fa-clock"></i>
                    </span>
                    <div>
                        <div class="text-muted small">Rata-rata Waktu Kerja</div>
                        <div class="h5 mb-0 fw-bold">{{ $analytics['avg_time_to_employment'] ?? '-' }} bulan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Employment Distribution --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Distribusi Status Pekerjaan</h3>
                </div>
                <div class="card-body p-4">
                    @if (empty($analytics['employment_distribution'] ?? []))
                        <div class="text-center text-muted py-4">Belum ada data.</div>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analytics['employment_distribution'] as $status => $data)
                                    <tr>
                                        <td>{{ $data['label'] ?? $status }}</td>
                                        <td class="text-end fw-semibold">{{ $data['count'] ?? 0 }}</td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px; max-width: 100px;">
                                                    <div class="progress-bar bg-primary" style="width: {{ $data['percentage'] ?? 0 }}%;"></div>
                                                </div>
                                                {{ $data['percentage'] ?? 0 }}%
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Job Relevance --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-bullseye me-2 text-primary"></i>Kesesuaian Pekerjaan dengan Studi</h3>
                </div>
                <div class="card-body p-4">
                    @if (empty($analytics['job_relevance_breakdown'] ?? []))
                        <div class="text-center text-muted py-4">Belum ada data.</div>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Kesesuaian</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analytics['job_relevance_breakdown'] as $rel => $data)
                                    <tr>
                                        <td>{{ $data['label'] ?? $rel }}</td>
                                        <td class="text-end fw-semibold">{{ $data['count'] ?? 0 }}</td>
                                        <td class="text-end">{{ $data['percentage'] ?? 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Salary Distribution --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Distribusi Range Gaji</h3>
                </div>
                <div class="card-body p-4">
                    @if (empty($analytics['salary_distribution'] ?? []))
                        <div class="text-center text-muted py-4">Belum ada data.</div>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Range Gaji</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analytics['salary_distribution'] as $range => $count)
                                    <tr>
                                        <td>{{ $range ?: '(Tidak diisi)' }}</td>
                                        <td class="text-end fw-semibold">{{ $count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- By Study Program --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-building-columns me-2 text-primary"></i>Per Program Studi</h3>
                </div>
                <div class="card-body p-4">
                    @if (empty($analytics['by_study_program'] ?? []))
                        <div class="text-center text-muted py-4">Belum ada data.</div>
                    @else
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Program Studi</th>
                                    <th class="text-end">Respon</th>
                                    <th class="text-end">Bekerja</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analytics['by_study_program'] as $program => $data)
                                    <tr>
                                        <td>{{ $program }}</td>
                                        <td class="text-end fw-semibold">{{ $data['responses'] ?? 0 }}</td>
                                        <td class="text-end">{{ $data['working'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- By Graduation Year --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 2px solid #e2e8f0;">
                    <h3 class="card-title mb-0"><i class="fas fa-calendar me-2 text-primary"></i>Per Tahun Lulus</h3>
                </div>
                <div class="card-body p-4">
                    @if (empty($analytics['by_graduation_year'] ?? []))
                        <div class="text-center text-muted py-4">Belum ada data.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tahun Lulus</th>
                                        <th class="text-end">Respon</th>
                                        <th class="text-end">Bekerja</th>
                                        <th class="text-end">Wirausaha</th>
                                        <th class="text-end">Studi Lanjut</th>
                                        <th class="text-end">Belum Bekerja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($analytics['by_graduation_year'] as $year => $data)
                                        <tr>
                                            <td class="fw-semibold">{{ $year }}</td>
                                            <td class="text-end">{{ $data['responses'] ?? 0 }}</td>
                                            <td class="text-end">{{ $data['working'] ?? 0 }}</td>
                                            <td class="text-end">{{ $data['entrepreneur'] ?? 0 }}</td>
                                            <td class="text-end">{{ $data['studying'] ?? 0 }}</td>
                                            <td class="text-end">{{ $data['unemployed'] ?? 0 }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
