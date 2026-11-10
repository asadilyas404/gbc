<div id="notification-alert-stack">
<div
    id="internet-connection-alert"
    data-turbo-permanent
    class="internet-connection-alert"
    role="alert"
    aria-live="assertive"
    hidden
    data-mute-label="{{ __('messages.Mute_sound', [], 'en') }} / {{ __('messages.Mute_sound', [], 'ar') }}"
    data-unmute-label="{{ __('messages.Unmute_sound', [], 'en') }} / {{ __('messages.Unmute_sound', [], 'ar') }}"
>
    <div class="notification-alert-text">
        <span lang="en" dir="ltr">{{ __('messages.No_internet_connection', [], 'en') }}</span>
        <span lang="ar" dir="rtl">{{ __('messages.No_internet_connection', [], 'ar') }}</span>
    </div>
    <button type="button" id="internet-connection-alert-mute" aria-pressed="false">
        {{ __('messages.Mute_sound', [], 'en') }} / {{ __('messages.Mute_sound', [], 'ar') }}
    </button>
</div>
<div id="sound-enable-bar" role="alert" aria-live="polite" hidden>
    <div class="notification-alert-text">
        <span lang="en" dir="ltr">&#128276; {{ __('messages.Interact_to_enable_notification_sounds', [], 'en') }}</span>
        <span lang="ar" dir="rtl">{{ __('messages.Interact_to_enable_notification_sounds', [], 'ar') }}</span>
    </div>
</div>
</div>
<script src="{{ dynamicAsset('public/assets/admin/js/sound-interaction-alert.js') }}" data-turbo-eval="false"></script>
