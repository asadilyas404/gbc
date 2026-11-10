<div
    id="internet-connection-alert"
    class="internet-connection-alert"
    role="alert"
    aria-live="assertive"
    hidden
    data-mute-label="{{ translate('messages.Mute_sound') }}"
    data-unmute-label="{{ translate('messages.Unmute_sound') }}"
>
    <span>{{ translate('messages.No_internet_connection') }}</span>
    <button type="button" id="internet-connection-alert-mute" aria-pressed="false">
        {{ translate('messages.Mute_sound') }}
    </button>
</div>
