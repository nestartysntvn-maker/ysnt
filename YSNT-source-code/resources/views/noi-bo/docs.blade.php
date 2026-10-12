@extends('noi-bo.layout', ['title' => 'Quy trình'])
@section('content')
<h1>Quy trình, tài liệu</h1>
<form class="search" role="search"><input name="q" value="{{ $q }}" placeholder="Tìm quy trình: đổi trả, chốt ca, giao hàng…" aria-label="Tìm quy trình"><button class="btn">Tìm</button></form>
<p class="meta">Không tìm thấy? <a href="/noi-bo/hoi-ai">Hỏi AI</a>, AI đọc toàn bộ tài liệu và chỉ ra đoạn liên quan.</p>
@forelse($docs as $cat => $list)
  <h2>{{ $cat }}</h2>
  @foreach($list as $d)<a class="card" style="display:block;text-decoration:none;color:inherit" href="/noi-bo/quy-trinh/{{ $d->id }}"><b>{{ $d->title }}</b><div class="meta">{{ $d->department?->name ?? 'Toàn công ty' }} · cập nhật {{ $d->updated_at->format('d/m/Y') }}@if($d->file_name) · có tệp đính kèm @endif</div></a>@endforeach
@empty
  <div class="card">{{ $q !== '' ? 'Không có tài liệu khớp "' . $q . '".' : 'Chưa có tài liệu nào.' }}</div>
@endforelse
@endsection
