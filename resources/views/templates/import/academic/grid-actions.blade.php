@php($resource = $resource ?? request()->segment(3))

<div class="btn-list justify-content-end">
    <a href="{{ route('admin.academic.exports.pdf', ['resource' => $resource]) }}" class="btn btn-outline-danger">
        <i class="fa fa-file-pdf me-2"></i>Export PDF
    </a>
    <a href="{{ route('admin.academic.import-template', ['resource' => $resource]) }}" class="btn btn-outline-secondary">
        <i class="fa fa-file-import me-2"></i>Template Import
    </a>
</div>
