@extends('noi-bo.layout', ['title' => 'Hỏi AI'])
@section('content')
<h1>Hỏi AI về quy trình</h1>
<p class="meta" style="margin-top:-8px">AI chỉ trả lời theo tài liệu quy trình của công ty và ghi rõ nguồn. Câu hỏi được lưu để công ty bổ sung quy trình còn thiếu.</p>
<div class="chat">
@foreach($hist as $h)
  <div class="bub me">{{ $h->question }}</div>
  <div class="bub">{{ $h->answer }}
    @if($h->doc_ids)<div class="src">Nguồn: @foreach($h->doc_ids as $id)@if(isset($docs[$id]))<a href="/noi-bo/quy-trinh/{{ $id }}">{{ $docs[$id] }}</a>@endif @endforeach</div>@endif
    @if($h->status === 'answered')<form method="post" action="/noi-bo/hoi-ai/{{ $h->id }}/danh-gia" class="src">@csrf
      @if($h->helpful === null)<button name="ok" value="1" class="btn line" style="padding:4px 10px">Hữu ích</button> <button name="ok" value="0" class="btn line" style="padding:4px 10px">Chưa đúng</button>
      @else<span class="meta">{{ $h->helpful ? 'Bạn thấy hữu ích' : 'Bạn báo chưa đúng, nhân sự sẽ xem lại' }}</span>@endif</form>@endif
  </div>
@endforeach
</div>
<form method="post" action="/noi-bo/hoi-ai" class="card" style="margin-top:14px" id="cuoi">@csrf
  <div class="field"><label for="q">Câu hỏi</label><textarea id="q" name="q" rows="3" placeholder="Ví dụ: Khách đổi trả hộp yến bị móp thì làm thế nào?" required>{{ old('q') }}</textarea></div>
  @error('q')<p class="err">{{ $message }}</p>@enderror
  <button class="btn">Hỏi</button>
</form>
@endsection
