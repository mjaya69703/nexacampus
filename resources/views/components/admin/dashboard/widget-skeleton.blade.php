<div class="card rounded-4 mb-4">
    <div class="card-body p-3 p-md-4" aria-busy="true" aria-live="polite">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="app-skeleton app-skeleton-icon rounded-3"></div>
            <div class="flex-grow-1">
                <div class="app-skeleton app-skeleton-line w-25 mb-2"></div>
                <div class="app-skeleton app-skeleton-line w-50"></div>
            </div>
        </div>
        <div class="row g-3 mb-4">
            @foreach(range(1, 4) as $i)
                <div class="col-6 col-xl-3">
                    <div class="app-skeleton app-skeleton-card rounded-3"></div>
                </div>
            @endforeach
        </div>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="app-skeleton app-skeleton-chart rounded-3"></div>
            </div>
            <div class="col-lg-4">
                <div class="app-skeleton app-skeleton-chart rounded-3"></div>
            </div>
        </div>
    </div>
</div>
