@props(['url' => null, 'logo' => true])
<tr>
<td class="header">
<a @if ($url) href="{{ $url }}" @endif>
@if ($logo)
<img src="{{ asset('apple-touch-icon.png') }}" class="logo" width="36" height="36" alt="">
@endif
<span class="brand">{!! $slot !!}</span>
</a>
</td>
</tr>
