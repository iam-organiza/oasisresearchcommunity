{{-- The header's account buttons: staff go to the dashboard, members to their account, visitors can sign in or join. --}}
@auth
    @if (auth()->user()->role->isStaff())
        <a href="{{ route('admin') }}" class="btn btn-ink min-h-11">Dashboard</a>
    @else
        <a href="{{ route('membership.show') }}" class="btn btn-ink min-h-11">My account</a>
    @endif
@else
    <a href="{{ route('login') }}" class="btn btn-quiet min-h-11">Sign in</a>
    <a href="{{ route('register') }}" class="btn btn-primary min-h-11">Join Us</a>
@endauth
