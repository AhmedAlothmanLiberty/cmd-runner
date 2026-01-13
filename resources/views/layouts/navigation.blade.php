<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <span class="fw-semibold text-primary">{{ config('app.name', 'Dashboard') }}</span>
        </a>

        <div class="flex-grow-1"></div>

        @auth
            @php
                $unreadCount = auth()->user()->unreadNotifications()->count();
                $notifications = auth()->user()->notifications()->latest()->limit(6)->get();
            @endphp
            <div class="d-flex align-items-center gap-3">
                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-notifications-trigger>
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white" style="width: 32px; height: 32px;">
                            <i class="bi bi-bell"></i>
                        </span>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-notifications-count @if ($unreadCount === 0) style="display:none;" @endif>
                            {{ $unreadCount }}
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 320px;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                            <span class="fw-semibold">Notifications</span>
                            <form method="POST" action="{{ route('notifications.readAll') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm text-decoration-none">Mark all read</button>
                            </form>
                        </div>
                        <div class="list-group list-group-flush" data-notifications-list>
                            @forelse ($notifications as $notification)
                                @php $data = $notification->data; @endphp
                                <div class="list-group-item d-flex justify-content-between align-items-start" data-id="{{ $notification->id }}">
                                    <div class="me-2">
                                        <a href="{{ $data['url'] ?? route('admin.tasks.index') }}" class="fw-semibold text-decoration-none">
                                            {{ $data['title'] ?? 'Task update' }}
                                        </a>
                                        <div class="small text-muted">
                                            <div>To: <span class="text-primary fw-semibold">{{ $data['assigned_name'] ?? 'Unassigned' }}</span></div>
                                            <div>Title: {{ $data['task_title'] ?? '—' }}</div>
                                            @if (! empty($data['actor_name']))
                                                <div>By: {{ $data['actor_name'] }}</div>
                                            @endif
                                            @if (! empty($data['comment']))
                                                <div>Comment: {{ $data['comment'] }}</div>
                                            @endif
                                        @if (! empty($data['status']))
                                            <div>Status: {{ \App\Models\Task::statusLabels()[$data['status']] ?? str_replace(['_', '-'], ' ', $data['status']) }}</div>
                                        @endif
                                        </div>
                                        <div class="small text-muted">{{ $notification->created_at?->diffForHumans() ?? '—' }}</div>
                                    </div>
                                    @if (is_null($notification->read_at))
                                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                            @csrf
                                            <input type="hidden" name="redirect" value="{{ $data['url'] ?? route('admin.tasks.index') }}">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Read</button>
                                        </form>
                                    @endif
                                </div>
                            @empty
                                <div class="list-group-item text-center text-muted py-4">No notifications yet.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn p-0 border-0 bg-transparent d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white fw-semibold" style="width: 32px; height: 32px;">
                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                        </span>

                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <div class="fw-semibold">{{ Auth::user()->name }}</div>
                            <div class="text-muted small">{{ Auth::user()->email }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log Out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        @endauth
    </div>
</nav>

<div class="toast-container position-fixed top-0 end-0 p-3" style="margin-right: 12px;">
    <div id="notificationToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">New notification.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.querySelector('[data-notifications-list]');
            const countBadge = document.querySelector('[data-notifications-count]');
            const toastEl = document.getElementById('notificationToast');
            const toast = toastEl ? new bootstrap.Toast(toastEl, { delay: 2500 }) : null;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const statusLabels = @json(\App\Models\Task::statusLabels());

            let lastNotificationId = list?.querySelector('.list-group-item')?.dataset?.id ?? null;
            let lastUnreadCount = Number(countBadge?.textContent ?? 0);

            const escapeHtml = (value) => {
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                return String(value ?? '').replace(/[&<>"']/g, (m) => map[m]);
            };

            const renderList = (notifications) => {
                if (!list) return;
                if (!notifications.length) {
                    list.innerHTML = '<div class="list-group-item text-center text-muted py-4">No notifications yet.</div>';
                    return;
                }

                list.innerHTML = notifications.map((item) => {
                    const readButton = item.read_at
                        ? ''
                        : `<form method="POST" action="{{ route('notifications.read', '::id::') }}">
                                <input type="hidden" name="_token" value="${csrf}">
                                <input type="hidden" name="redirect" value="${escapeHtml(item.url)}">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Read</button>
                           </form>`.replace('::id::', item.id);

                    const statusLabel = item.status ? (statusLabels[item.status] ?? item.status.replace(/[_-]/g, ' ')) : '';
                    const statusLine = statusLabel ? `<div>Status: ${escapeHtml(statusLabel)}</div>` : '';
                    const actorLine = item.actor_name ? `<div>By: ${escapeHtml(item.actor_name)}</div>` : '';
                    const commentLine = item.comment ? `<div>Comment: ${escapeHtml(item.comment)}</div>` : '';

                    return `
                        <div class="list-group-item d-flex justify-content-between align-items-start" data-id="${escapeHtml(item.id)}">
                            <div class="me-2">
                                <a href="${escapeHtml(item.url)}" class="fw-semibold text-decoration-none">${escapeHtml(item.title)}</a>
                                <div class="small text-muted">
                                    <div>To: <span class="text-primary fw-semibold">${escapeHtml(item.assigned_name)}</span></div>
                                    <div>Title: ${escapeHtml(item.task_title)}</div>
                                    ${actorLine}
                                    ${commentLine}
                                    ${statusLine}
                                </div>
                                <div class="small text-muted">${escapeHtml(item.created_human)}</div>
                            </div>
                            ${readButton}
                        </div>
                    `;
                }).join('');
            };

            const updateCount = (count) => {
                if (!countBadge) return;
                if (count > 0) {
                    countBadge.textContent = count;
                    countBadge.style.display = 'inline-block';
                } else {
                    countBadge.style.display = 'none';
                }
            };

            const pollNotifications = async () => {
                try {
                    const response = await fetch('{{ route('notifications.latest') }}', {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    updateCount(data.unread_count);
                    renderList(data.notifications);

                    const newestId = data.notifications[0]?.id;
                    if (newestId && lastNotificationId && newestId !== lastNotificationId) {
                        toast?.show();
                    }
                    if (data.unread_count > lastUnreadCount) {
                        toast?.show();
                    }
                    if (newestId) {
                        lastNotificationId = newestId;
                    }
                    lastUnreadCount = data.unread_count;
                } catch (error) {
                    // ignore polling errors
                }
            };

            pollNotifications();
            setInterval(pollNotifications, 20000);
        });
    </script>
@endpush
