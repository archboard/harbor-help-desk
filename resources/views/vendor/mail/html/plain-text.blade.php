@props(['text'])
{{-- Plain text kept as written: escaped, line breaks kept, and on one line so CommonMark passes it through as an HTML block instead of formatting it. --}}
<div {{ $attributes->class('plain-text') }}>{!! preg_replace('/\R/', '<br>', e($text)) !!}</div>
