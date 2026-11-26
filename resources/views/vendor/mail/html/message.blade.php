<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: the secretariat's details, as on the website's footer. --}}
<x-slot:footer>
<x-mail::footer>
**{{ config('app.name') }}**<br>
{{ collect(config('oasis.address'))->first() }}, {{ collect(config('oasis.address'))->slice(1)->implode(' ') }}<br>
[{{ config('oasis.support_email') }}](mailto:{{ config('oasis.support_email') }}) · [Privacy Policy]({{ route('privacy') }}) · [Terms & Conditions]({{ route('terms') }})

© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
