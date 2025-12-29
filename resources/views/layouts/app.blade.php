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
            :root {
                --bs-primary: #329ad6;
                --bs-primary-rgb: 50, 154, 214;
                --bs-link-color: #329ad6;
                --bs-link-hover-color: #2b86bd;
            }
            .btn-primary {
                background-color: #329ad6;
                border-color: #329ad6;
            }
            .btn-primary:hover,
            .btn-primary:focus {
                background-color: #2b86bd;
                border-color: #2b86bd;
            }
            .btn-outline-primary {
                color: #329ad6;
                border-color: #329ad6;
            }
            .btn-outline-primary:hover,
            .btn-outline-primary:focus {
                background-color: #329ad6;
                border-color: #329ad6;
            }
            .text-primary { color: #329ad6 !important; }
            .bg-primary { background-color: #329ad6 !important; }
            .border-primary { border-color: #329ad6 !important; }
            .list-group-item.active {
                background-color: #329ad6;
                border-color: #329ad6;
                color: #fff;
            }
            .list-group-item.active .text-muted {
                color: rgba(255, 255, 255, 0.75) !important;
            }
            .card {
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
            }
            .card-header {
                background: #fff;
                border-bottom: 1px solid #e2e8f0;
            }
            .table {
                --bs-table-bg: #fff;
            }
            .table thead th {
                background: #f8fafc;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-size: 0.75rem;
                border-bottom: 1px solid #e2e8f0;
            }
            .table-hover tbody tr:hover {
                background: #f8fafc;
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
                    @if (auth()->check())
                        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-white sidebar border-end">
                            @include('layouts.sidebar')
                        </nav>
                    @endif

                    <main class="@if(auth()->check()) col-md-9 ms-sm-auto col-lg-10 px-md-4 @else col-12 px-3 @endif py-4">
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
