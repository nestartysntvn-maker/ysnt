@extends('noi-bo.layout', ['title' => 'Danh bạ'])
@section('content')
<h1>Danh bạ nội bộ</h1>
<form class="search" role="search"><input name="q" value="{{ $q }}" placeholder="Tìm tên, chức danh, số điện thoại" aria-label="Tìm trong danh bạ"><button class="btn">Tìm</button></form>
@forelse($groups as $dept => $list)
  <h2>{{ $dept }} <span class="meta">· {{ $list->count() }} người</span></h2>
  <div class="card" style="padding:4px 12px;overflow-x:auto"><table class="dir"><thead><tr><th>Họ tên</th><th>Chức danh</th><th>Điện thoại</th><th>Zalo</th></tr></thead><tbody>
  @foreach($list as $e)<tr><td><b>{{ $e->full_name }}</b></td><td>{{ $e->job_title }}</td><td><a href="tel:{{ $e->phone }}">{{ $e->phone }}</a></td><td>{{ $e->p('zalo') }}</td></tr>@endforeach
  </tbody></table></div>
@empty
  <div class="card">Không có ai khớp.</div>
@endforelse
@endsection
