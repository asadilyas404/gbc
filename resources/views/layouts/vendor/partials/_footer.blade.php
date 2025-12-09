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
    const lifetimeMinutes = parseInt(
        document.querySelector('meta[name="session-lifetime-minutes"]')?.content
    ) || 60;

    const keepAliveUrl = "{{ route('vendor.session.keep-alive') }}";
    const loginUrl     = "{{ route('login', 'restaurant-employee') }}";

    const lifetimeMs   = lifetimeMinutes * 60 * 1000;
    const warnBeforeMs = 1 * 60 * 1000;
    const STORAGE_KEY  = 'sessionLastActivity';

    let warningShown = false;
    let sessionExpiredShown = false;

    const timerDisplay = document.getElementById('session-timer');

    const formatTime = (ms) => {
        const sec = Math.max(0, Math.floor(ms / 1000));
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return `${m}:${s}`;
    };

    // Initialize last activity if not present
    if (!localStorage.getItem(STORAGE_KEY)) {
        localStorage.setItem(STORAGE_KEY, Date.now().toString());
    }

    const touchActivity = () => {
        localStorage.setItem(STORAGE_KEY, Date.now().toString());
        warningShown = false;
    };

    // Attach activity listeners in ALL tabs
    // const activityEvents = ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'];
    // activityEvents.forEach((evt) => {
    //     document.addEventListener(evt, touchActivity, { passive: true });
    // });

    // React when another tab updates lastActivity
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY) {
            warningShown = false;
        }
    });

    if (timerDisplay) {
        timerDisplay.textContent = formatTime(lifetimeMs);
    }

    const intervalId = setInterval(() => {
        const lastActivity = parseInt(localStorage.getItem(STORAGE_KEY) || Date.now());
        const elapsedSinceActivity = Date.now() - lastActivity;
        const remainingMs = lifetimeMs - elapsedSinceActivity;

        if (timerDisplay) {
            timerDisplay.textContent = formatTime(remainingMs);
        }

        // warn
        if (!warningShown && remainingMs > 0 && remainingMs <= warnBeforeMs) {
            warningShown = true;

            Swal.fire({
                title: 'Session is about to expire',
                text: 'Do you want to stay logged in?',
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Stay logged in',
                cancelButtonText: 'Dismiss'
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        type: 'POST',
                        url: keepAliveUrl,
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                    }).always(() => {
                        touchActivity(); // reset across tabs
                    });
                }
            });
        }

        // expired
        if (remainingMs <= 0 && !sessionExpiredShown) {
            sessionExpiredShown = true;
            clearInterval(intervalId);

            Swal.fire({
                title: 'Session expired',
                text: 'Please log in again to continue.',
                type: 'info',
                confirmButtonText: 'Login'
            }).then(() => {
                window.location.href = loginUrl;
            });
        }

    }, 1000);

    // jQuery part
    $(function () {
        $(document).ajaxError(function (event, xhr) {
            if (xhr.status === 419 && !sessionExpiredShown) {
                sessionExpiredShown = true;
                Swal.fire({
                    title: '{{ translate('Session_Expired') }}',
                    text: '{{ translate('Please_refresh_the_page_and_try_again.') }}',
                    type: 'warning',
                    confirmButtonText: '{{ translate('messages.Ok') }}'
                }).then(() => {
                    $('#loading').show();
                    location.reload();
                });
            }
        });

        $(document).ajaxSuccess(function () {
            touchActivity();
        });
    });
});
</script>

