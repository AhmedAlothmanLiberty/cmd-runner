<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <span class="fw-semibold text-primary">{{ config('app.name', 'Dashboard') }}</span>
        </a>

        <div class="d-flex align-items-center">
            <button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            @auth
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">Log Out</button>
                </form>
            @endauth
        </div>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('dashboard')) active fw-semibold text-primary @endif" href="{{ route('dashboard') }}">Dashboard</a>
                </li>
                @if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin']))
                    <li class="nav-item">
                        <a class="nav-link @if(request()->routeIs('admin.users.*')) active fw-semibold text-primary @endif" href="{{ route('admin.users.index') }}">User Management</a>
                    </li>
                    @if (auth()->user()->hasRole('super-admin'))
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('admin.roles.*')) active fw-semibold text-primary @endif" href="{{ route('admin.roles.index') }}">Roles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if(request()->routeIs('admin.permissions.*')) active fw-semibold text-primary @endif" href="{{ route('admin.permissions.index') }}">Permissions</a>
                        </li>
                    @endif
                @endif
            </ul>

            @auth
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 ms-lg-auto w-100 w-lg-auto">
                    <div class="dropdown w-100 w-lg-auto">
                        <button class="btn btn-outline-primary dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ Auth::user()->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end w-100">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            @endauth
        </div>
    </div>
</nav>
