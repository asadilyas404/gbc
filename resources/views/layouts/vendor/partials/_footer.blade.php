<div class="footer">
    <div class="row justify-content-between align-items-center">
        <div class="col">
            <p class="font-size-sm mb-0">
                &copy; {{Str::limit(\App\CentralLogics\Helpers::get_restaurant_data()->name, 50, '...')}}. <span
                    class="d-none d-sm-inline-block"></span>
            </p>
        </div>
        <div class="col">
            <span id="session-timer-box">
                Session expires in: <span id="session-timer">--:--</span>
            </span>
        </div>
        <div class="col-auto">
            <div class="d-flex justify-content-end">
                <!-- List Dot -->
                <ul class="list-inline list-separator">
                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('vendor.business-settings.restaurant-setup')}}">{{translate('messages.restaurant_settings')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <a class="list-separator-link" href="{{route('vendor.shop.view')}}">{{translate('messages.profile')}}</a>
                    </li>

                    <li class="list-inline-item">
                        <!-- Keyboard Shortcuts Toggle -->
                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-icon btn-ghost-secondary rounded-circle"
                               href="{{route('vendor.dashboard')}}">
                                <i class="tio-home-outlined"></i>
                            </a>
                        </div>
                        <!-- End Keyboard Shortcuts Toggle -->
                    </li>
                </ul>
                <!-- End List Dot -->
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Read values from meta tags
    const lifetimeMinutes = parseInt(
        document.querySelector('meta[name="session-lifetime-minutes"]')?.content
    ) || 60;

    const keepAliveUrl = "{{ route('vendor.session.keep-alive') }}";

    const loginUrl = "{{ route('login', 'restaurant-employee') }}";

    const lifetimeMs   = lifetimeMinutes * 60 * 1000;
    const warnBeforeMs = 1 * 60 * 1000; // warn 1 minute before expiry

    let idleTimeMs = 0;
    let warningShown = false;
    let sessionExpiredShown = false;

    const timerDisplay = document.getElementById('session-timer');

    // Helper → format mm:ss
    const formatTime = (ms) => {
        const sec = Math.floor(ms / 1000);
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return `${m}:${s}`;
    };

    // Reset idle timer on activity
    const resetIdleTimer = () => {
        idleTimeMs = 0;
        warningShown = false;

        if (timerDisplay) {
            timerDisplay.textContent = formatTime(lifetimeMs);
        }
    };

    // Attach activity listeners ONCE (not inside ajaxSuccess)
    // const activityEvents = ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'];
    // activityEvents.forEach((evt) => {
    //     document.addEventListener(evt, resetIdleTimer, { passive: true });
    // });

    // Initialize timer display
    if (timerDisplay) {
        timerDisplay.textContent = formatTime(lifetimeMs);
    }

    // Main interval (runs every 1 sec)
    const intervalId = setInterval(() => {
        idleTimeMs += 1000;

        const remainingMs = lifetimeMs - idleTimeMs;

        // Update visible timer
        if (timerDisplay) {
            if (remainingMs > 0) {
                timerDisplay.textContent = formatTime(remainingMs);
            } else {
                timerDisplay.textContent = "00:00";
            }
        }

        // ⚠️ Show warning before session expires
        if (!warningShown && remainingMs <= warnBeforeMs && remainingMs > 0) {
            warningShown = true;

            Swal.fire({
                title: 'Session is about to expire',
                text: 'Do you want to stay logged in?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Stay logged in',
                cancelButtonText: 'Dismiss'
            }).then((result) => {
                if (result.value) {
                    // Hit keep-alive route → refresh Laravel session
                    $.ajax({
                        type: 'POST',
                        url: keepAliveUrl,
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                    });
                }
            });
        }

        // ⛔ Session expired
        if (remainingMs <= 0 && !sessionExpiredShown) {
            sessionExpiredShown = true;
            clearInterval(intervalId);

            Swal.fire({
                title: 'Session expired',
                text: 'Please log in again to continue.',
                icon: 'info',
                confirmButtonText: 'Login'
            }).then(() => {
                window.location.href = loginUrl;
            });
        }

    }, 1000);

    // jQuery part
    $(function () {
        // GLOBAL ERROR HANDLER (419 = Laravel session / CSRF expired)
        $(document).ajaxError(function (event, xhr) {
            if (xhr.status === 419 && !sessionExpiredShown) {
                sessionExpiredShown = true;
                Swal.fire({
                    title: '{{ translate('Session_Expired') }}',
                    text: '{{ translate('Please_refresh_the_page_and_try_again.') }}',
                    icon: 'warning',
                    confirmButtonText: '{{ translate('messages.Ok') }}'
                }).then(() => {
                    $('#loading').show();
                    location.reload();
                });
            }
        });

        // You *can* add a global success handler here if needed,
        // but no need to attach event listeners inside it.
        $(document).ajaxSuccess(function () {
            resetIdleTimer();
        });
    });
});

</script>
