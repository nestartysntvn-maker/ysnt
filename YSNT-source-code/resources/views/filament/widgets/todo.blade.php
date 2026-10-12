<x-filament-widgets::widget>
  <x-filament::section heading="Việc cần xử lý">
    @php $items = $this->items(); @endphp
    @if(! $items)
      <p style="font-size:14px;opacity:.7">Không có việc tồn. Mọi thứ đã xử lý xong.</p>
    @else
      <ul style="display:grid;gap:2px;margin:0;padding:0;list-style:none">
      @foreach($items as $i)
        <li><a href="{{ $i['url'] }}" style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 4px;border-bottom:1px solid rgba(128,128,128,.15);text-decoration:none">
          <span style="font-size:14px">{{ $i['label'] }}</span><span style="font-weight:700;font-size:16px;min-width:2.5em;text-align:right">{{ number_format($i['n'], 0, ',', '.') }}</span></a></li>
      @endforeach
      </ul>
    @endif
  </x-filament::section>
</x-filament-widgets::widget>
