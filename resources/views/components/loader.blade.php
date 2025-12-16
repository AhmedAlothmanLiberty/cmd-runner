@props([
    'show' => true,        // Toggle visibility
    'blocking' => true,    // When true, shows a full-page overlay that blocks interaction
    'duration' => 1000,    // Minimum visible time in ms (default 3 seconds)
    'triggerSelector' => null, // Optional CSS selector to auto-bind submit handling
    'text' => 'LIBERTY',   // Text to display/animate instead of balls
])

@if ($show)
    @php
        $loaderId = $attributes->get('id') ?? 'loader-' . uniqid();
    @endphp

    @once
        <style>
            .loader-overlay {
                position: fixed;
                inset: 0;
                background: rgba(255, 255, 255, 0.75);
                backdrop-filter: blur(2px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1050;
            }

            .loader-inline {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .loader-letters {
                display: flex;
                gap: 10px;
                justify-content: center;
                align-items: center;
            }

            .loader-letters .letter {
                display: inline-block;
                font-weight: 800;
                font-size: 1.4rem;
                letter-spacing: 0.08em;
                color: #329AD3;
                animation: loader-bounce 0.3s infinite alternate;
            }

            /* Alternate between #329AD3 and #334155 for clear contrast */
            .loader-letters .letter:nth-child(odd) { color: #329AD3; }
            .loader-letters .letter:nth-child(even) { color: #334155; }

            /* Staggered delays so letters bounce like individual balls */
            .loader-letters .letter:nth-child(1) { animation-delay: 0s; }
            .loader-letters .letter:nth-child(2) { animation-delay: 0.08s; }
            .loader-letters .letter:nth-child(3) { animation-delay: 0.16s; }
            .loader-letters .letter:nth-child(4) { animation-delay: 0.24s; }
            .loader-letters .letter:nth-child(5) { animation-delay: 0.32s; }
            .loader-letters .letter:nth-child(6) { animation-delay: 0.40s; }
            .loader-letters .letter:nth-child(7) { animation-delay: 0.48s; }
            .loader-letters .letter:nth-child(8) { animation-delay: 0.56s; }

            @keyframes loader-bounce {
                from { transform: translateY(0); }
                to   { transform: translateY(-16px); }
            }
        </style>
    @endonce

    <div {{ $attributes->merge(['id' => $loaderId])->class([
        $blocking ? 'loader-overlay' : 'loader-inline',
    ]) }} aria-live="polite" aria-busy="true" role="status" data-duration="{{ (int) $duration }}">
        <div class="loader-letters">
            <span class="visually-hidden">Loading</span>
            @foreach (str_split($text ?? 'LEBRITY') as $char)
                <span class="letter">{{ $char }}</span>
            @endforeach
        </div>
    </div>

    @if ($triggerSelector)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const loader = document.getElementById(@json($loaderId));
                if (!loader) return;

                const minDuration = Number(loader.dataset.duration ?? 1000) || 1000;
                const show = () => loader.style.display = 'flex';
                const hide = () => loader.style.display = 'none';

                document.querySelectorAll(@json($triggerSelector)).forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault();

                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                        }

                        show();

                        // Wait for at least minDuration before proceeding, so loader stays visible
                        setTimeout(() => {
                            form.submit();
                        }, minDuration);
                    });
                });
            });
        </script>
    @endif
@endif
