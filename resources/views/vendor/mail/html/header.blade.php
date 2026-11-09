@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('apple-touch-icon.png') }}" class="logo" width="36" height="36" alt="">
<span class="brand">{!! $slot !!}</span>
</a>
</td>
</tr>
