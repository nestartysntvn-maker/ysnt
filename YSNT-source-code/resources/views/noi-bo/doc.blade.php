@extends('noi-bo.layout', ['title' => $d->title])
@section('content')
<p class="meta"><a href="/noi-bo/quy-trinh">← Quy trình</a></p>
<h1>{{ $d->title }}</h1>
<p class="meta">{{ $d->category ?: 'Chung' }} · {{ $d->department?->name ?? 'Toàn công ty' }} · cập nhật {{ $d->updated_at->format('d/m/Y') }}@if($d->owner) · phụ trách {{ $d->owner->full_name }}@endif</p>
@if($d->body)<div class="card body">{{ strip_tags($d->body) }}</div>@endif
@if($d->file_name)<div class="card"><b>Tệp đính kèm:</b> <a href="/noi-bo/quy-trinh/{{ $d->id }}/tep">{{ $d->file_name }}</a>
  @if($d->file_text && ! $d->body)<details style="margin-top:8px"><summary>Xem nội dung chữ trong tệp</summary><div class="body" style="margin-top:8px">{{ \Illuminate\Support\Str::limit($d->file_text, 20000) }}</div></details>@endif</div>@endif
@endsection
