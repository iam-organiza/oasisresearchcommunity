@props(['url'])
{{-- The navy band at the top of the sheet. If images are blocked, the alt text shows the name in white on navy. --}}
<tr>
<td class="header-cell">
<a href="{{ $url }}" class="header-link">
<img src="{{ asset('images/oasis-logo-white.png') }}" class="logo" width="150" height="56" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
