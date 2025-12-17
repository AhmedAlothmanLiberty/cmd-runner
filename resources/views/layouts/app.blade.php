<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Bootstrap 5 -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">

        <style>
            .sidebar {
                min-height: calc(100vh - 56px);
            }
            .sidebar-sticky {
                position: sticky;
                top: 1rem;
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-light">
        @php
            $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
            $pageText = $routeName
                ? strtoupper(str_replace(['.', '-', 'index'], ' ', $routeName))
                : strtoupper(config('app.name', 'Loading'));
        @endphp

        <x-loader
            id="page-load-loader"
            :blocking="true"
            :duration="800"
            :text="$pageText"
            style="display: none; position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 1100;"
        />

        <div id="app">
            @include('layouts.navigation')

            <div class="container-fluid">
                <div class="row">
                    @if (auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin']))
                        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-white sidebar collapse show border-end">
                            @include('layouts.sidebar')
                        </nav>
                    @endif

                    <main class="@if(auth()->check() && auth()->user()->hasAnyRole(['admin', 'super-admin'])) col-md-9 ms-sm-auto col-lg-10 px-md-4 @else col-12 px-3 @endif py-4">
                        @isset($header)
                            <div class="d-flex flex-wrap flex-md-nowrap align-items-center pb-3 mb-3 border-bottom">
                                {{ $header }}
                            </div>
                        @endisset

                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script>
            // Show a quick inline loader on page load (non-blocking)
            document.addEventListener('DOMContentLoaded', () => {
                const loader = document.getElementById('page-load-loader');
                if (!loader) return;

                const duration = Number(loader.dataset.duration ?? 800) || 800;
                loader.style.display = 'inline-flex';

                setTimeout(() => {
                    loader.style.display = 'none';
                }, duration);
            });
        </script>
        @stack('scripts')
    </body>
</html>
