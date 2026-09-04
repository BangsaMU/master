@php
    $reverbHost = env('REVERB_HOST', config('MasterConfig.senada.reverb_host', '192.168.20.187'));
    $reverbPort = env('REVERB_PORT', config('MasterConfig.senada.reverb_port', 9029));
    $reverbScheme = env('REVERB_SCHEME', 'http');
    $reverbAppKey = env('REVERB_APP_KEY', config('MasterConfig.senada.app_key', 'senada_hub_key'));
    $userEmail = auth()->check() ? auth()->user()->email : null;
    $authEndpoint = route('master.sync.channel-auth');
    $syncApiUrl = \Illuminate\Support\Facades\Route::has('master.sync.sync') ? route('master.sync.sync') : route('master.sync.items');
@endphp

<!-- Master Data Broadcast & Realtime Sync Listener -->
<div id="master-broadcast-toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;"></div>

<script>
    (function () {
        // Dynamic loader for Pusher JS if not already available
        function loadPusher(callback) {
            if (window.Pusher) {
                callback();
                return;
            }
            const script = document.createElement('script');
            script.src = 'https://js.pusher.com/8.2.0/pusher.min.js';
            script.onload = callback;
            document.head.appendChild(script);
        }

        function showBroadcastToast(type, title, message) {
            const container = document.getElementById('master-broadcast-toast-container');
            if (!container) return;

            const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'error' ? 'bg-danger text-white' : 'bg-primary text-white');
            const toastId = 'toast-' + Date.now();

            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <strong>${title}</strong><br>
                            <small>${message}</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', toastHtml);
            const el = document.getElementById(toastId);

            if (window.bootstrap && bootstrap.Toast) {
                const toast = new bootstrap.Toast(el, { delay: 5000 });
                toast.show();
                el.addEventListener('hidden.bs.toast', () => el.remove());
            } else {
                el.style.display = 'block';
                setTimeout(() => el.remove(), 5000);
            }
        }

        loadPusher(function () {
            const csrfToken = '{{ csrf_token() }}';
            const authEndpoint = '{{ $authEndpoint }}';
            const syncApiUrl = '{{ $syncApiUrl }}';

            const pusher = new Pusher('{{ $reverbAppKey }}', {
                wsHost: '{{ $reverbHost }}',
                wsPort: {{ $reverbPort }},
                wssPort: {{ $reverbPort }},
                forceTLS: {{ $reverbScheme === 'https' ? 'true' : 'false' }},
                enabledTransports: ['ws', 'wss'],
                channelAuthorization: {
                    endpoint: authEndpoint,
                    transport: 'ajax',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                },
                cluster: 'mt1'
            });

            pusher.connection.bind('state_change', function (states) {
                console.log('[MasterBroadcast] WebSocket state:', states.current);
            });

            // 1. PUBLIC CHANNEL: Master Data Updates (masterdata.items)
            const masterChannel = pusher.subscribe('masterdata.items');

            const handleMasterUpdate = function (payload) {
                console.log('[MasterBroadcast] Master update received:', payload);

                const table = payload.table || 'master_item_code';
                const label = payload.label || 'Data Master';
                const identifier = payload.identifier || payload.item_code || ('ID #' + (payload.id || payload.max_id));
                const actionText = payload.action === 'created' ? 'ditambahkan' : (payload.action === 'deleted' ? 'dihapus' : 'diperbarui');

                showBroadcastToast('info', `${label} ${actionText}`, `${label} <b>${identifier}</b> sedang disinkronkan ke database lokal...`);

                // Automatically trigger backend sync
                fetch(syncApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        table: table,
                        id: payload.id,
                        item_id: payload.id,
                        max_id: payload.max_id,
                        target_max_id: payload.max_id || payload.id,
                        action: payload.action
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const count = data.synced_count || 1;
                        showBroadcastToast('success', 'Sinkronisasi Selesai', `${count} data ${label.toLowerCase()} berhasil disinkronkan.`);

                        // Dispatch window custom event for reactive UI
                        window.dispatchEvent(new CustomEvent('master-data-synced', { detail: data }));
                        window.dispatchEvent(new CustomEvent('master-item-synced', { detail: data }));

                        // Refresh active DataTables if present
                        if (window.jQuery && $.fn.dataTable) {
                            try {
                                $('.dataTable').DataTable().ajax.reload(null, false);
                            } catch (err) {
                                // DataTable might not be using server-side ajax, ignore
                            }
                        }

                        // If user is currently viewing/editing this entity, auto reload
                        const currentPath = window.location.pathname;
                        const slug = table.replace('master_', '').replace('_', '-');
                        if (payload.id && currentPath.indexOf('/' + slug + '/' + payload.id) !== -1) {
                            showBroadcastToast('info', 'Memuat Ulang Halaman', `Data ${label} telah diperbarui dari Master Data...`);
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        console.error('[MasterBroadcast] Sync error:', data.message);
                    }
                })
                .catch(err => {
                    console.error('[MasterBroadcast] Auto-sync request failed:', err);
                });
            };

            masterChannel.bind('MasterDataUpdated', handleMasterUpdate);
            masterChannel.bind('MasterItemUpdated', handleMasterUpdate);
            masterChannel.bind('master-data-created', handleMasterUpdate);

            // 2. PRIVATE CHANNEL: User-Specific Notifications
            @if ($userEmail)
                const userEmailChannel = 'private-user.{{ strtolower($userEmail) }}';
                const privateChannel = pusher.subscribe(userEmailChannel);

                privateChannel.bind('pusher:subscription_succeeded', function () {
                    console.log('[MasterBroadcast] Subscribed to private channel:', userEmailChannel);
                });

                privateChannel.bind('pusher:subscription_error', function (err) {
                    console.warn('[MasterBroadcast] Subscription error for private channel:', err);
                });

                privateChannel.bind('UserNotification', function (payload) {
                    showBroadcastToast('success', payload.title || 'Notifikasi Baru', payload.message || '');
                });

                privateChannel.bind('broadcast.message', function (payload) {
                    showBroadcastToast('info', payload.title || 'Pesan Baru', payload.message || '');
                });
            @endif
        });
    })();
</script>
