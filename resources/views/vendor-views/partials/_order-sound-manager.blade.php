{{-- Audio Element & 30-Day Notification Sound Manager Partial --}}
<audio id="new-order-sound" preload="auto">
    <source src="{{ asset('sounds/notification.wav') }}" type="audio/wav">
</audio>

{{-- Non-intrusive 30-day sound enable bar --}}
<div id="sound-enable-bar"
    style="position:fixed;top:0;left:0;right:0;z-index:9999;background:linear-gradient(135deg,#ff9800,#f57c00);color:#fff;text-align:center;padding:12px 20px;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.25);display:none;">
    🔔 Click here to enable order notification sounds
</div>

<script>
    // ===== 30-Day Order Notification Sound Manager =====
    const SOUND_PREF_KEY = 'restaurantOrderSoundPermission';
    const SOUND_PREF_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 days in ms

    let audioUnlocked = false;
    let pendingPlay = false;
    let isUnlocking = false;

    // 1. LocalStorage Management Functions
    function saveSoundPreference() {
        try {
            const data = {
                enabled: true,
                expiresAt: Date.now() + SOUND_PREF_DURATION
            };
            localStorage.setItem(SOUND_PREF_KEY, JSON.stringify(data));
            console.log('[AUDIO] 30-day preference saved');
        } catch (e) {
            console.warn('[AUDIO] Error saving preference to localStorage:', e);
        }
    }

    function getSoundPreference() {
        try {
            const item = localStorage.getItem(SOUND_PREF_KEY);
            if (!item) return null;
            return JSON.parse(item);
        } catch (e) {
            return null;
        }
    }

    function clearSoundPreference() {
        try {
            localStorage.removeItem(SOUND_PREF_KEY);
        } catch (e) {}
    }

    function isSoundPreferenceValid() {
        const pref = getSoundPreference();
        if (!pref || typeof pref !== 'object') {
            console.log('[AUDIO] No saved sound preference');
            return false;
        }
        if (pref.enabled === true && typeof pref.expiresAt === 'number') {
            if (pref.expiresAt > Date.now()) {
                console.log('[AUDIO] Saved sound preference valid');
                return true;
            } else {
                console.log('[AUDIO] Sound preference expired');
                clearSoundPreference();
                return false;
            }
        }
        clearSoundPreference();
        console.log('[AUDIO] No saved sound preference');
        return false;
    }

    // 2. Audio & UI Elements
    function getAudioElement() {
        let sound = document.getElementById('new-order-sound');
        if (!sound) {
            sound = new Audio("{{ asset('sounds/notification.wav') }}");
            sound.id = 'new-order-sound';
        }
        return sound;
    }

    function showSoundBar() {
        console.trace('[AUDIO DEBUG] showSoundBar() called');
        const bar = document.getElementById('sound-enable-bar');
        if (bar && bar.style.display !== 'block') {
            bar.style.display = 'block';
            bar.style.opacity = '1';
            console.log('[AUDIO] Enable sound bar shown');
        }
    }

    function hideSoundBar() {
        const bar = document.getElementById('sound-enable-bar');
        if (bar && bar.style.display !== 'none') {
            bar.style.transition = 'opacity 0.4s ease';
            bar.style.opacity = '0';
            setTimeout(function() {
                bar.style.display = 'none';
            }, 400);
        }
    }

    // 3. User Gesture Unlock Handler
    function attachUnlockListeners() {
        $(document)
            .off('.orderSoundUnlock')
            .on('pointerdown.orderSoundUnlock keydown.orderSoundUnlock touchstart.orderSoundUnlock click.orderSoundUnlock', unlockAudio);
    }

    function removeUnlockListeners() {
        $(document).off('.orderSoundUnlock');
    }

    function unlockAudio() {
        if (audioUnlocked || isUnlocking) return;
        isUnlocking = true;
        console.log('[AUDIO] User interaction detected');

        const sound = getAudioElement();
        if (!sound) {
            isUnlocking = false;
            return;
        }

        sound.muted = false;
        sound.volume = 1.0;
        sound.currentTime = 0;

        const promise = sound.play();
        if (promise !== undefined) {
            promise.then(function() {
                audioUnlocked = true;
                window.kitchenAudioEnabled = true;
                isUnlocking = false;

                saveSoundPreference();
                hideSoundBar();
                removeUnlockListeners();
                console.log('[AUDIO] Audio successfully unlocked');

                if (pendingPlay) {
                    pendingPlay = false;
                    playNotificationSound();
                } else {
                    sound.pause();
                    sound.currentTime = 0;
                }
            }).catch(function(error) {
                isUnlocking = false;
                console.warn('[AUDIO] Unlock attempt failed by browser:', error);
                showSoundBar();
            });
        } else {
            isUnlocking = false;
        }
    }

    // 4. Page Load Initialization
    function initAudioOnPageLoad() {
        const sound = getAudioElement();
        const hasValidPreference = isSoundPreferenceValid();

        if (!hasValidPreference) {
            audioUnlocked = false;
            showSoundBar();
            attachUnlockListeners();
            return;
        }

        // Valid preference exists in localStorage -> test actual browser playback
        console.log('[AUDIO] Testing browser playback');
        if (!sound) {
            showSoundBar();
            attachUnlockListeners();
            return;
        }

        sound.muted = false;
        sound.volume = 1.0;
        sound.currentTime = 0;

        const testPromise = sound.play();
        if (testPromise !== undefined) {
            testPromise.then(function() {
                sound.pause();
                sound.currentTime = 0;
                audioUnlocked = true;
                window.kitchenAudioEnabled = true;
                hideSoundBar();
                removeUnlockListeners();
                console.log('[AUDIO] Browser playback allowed');
                console.log('[AUDIO] Audio ready');
            }).catch(function(error) {
                audioUnlocked = false;
                console.log('[AUDIO] Browser autoplay blocked');
                console.log('[AUDIO] Saved preference exists but browser blocked autoplay');
                console.log('[AUDIO] Waiting for user interaction');
                showSoundBar();
                attachUnlockListeners();
            });
        } else {
            showSoundBar();
            attachUnlockListeners();
        }
    }

    // 5. Notification Sound Playback
    function playNotificationSound() {
        console.log('[AUDIO DEBUG] notification requested', {
            audioUnlocked: audioUnlocked,
            pendingPlay: pendingPlay,
            preferenceValid: isSoundPreferenceValid()
        });

        const sound = getAudioElement();
        if (!sound) return;

        sound.muted = false;
        sound.volume = 1.0;
        sound.currentTime = 0;

        const promise = sound.play();
        if (promise !== undefined) {
            promise.then(function() {
                // Actual browser playback succeeded
                audioUnlocked = true;
                window.kitchenAudioEnabled = true;
                pendingPlay = false;

                hideSoundBar();
                removeUnlockListeners();

                console.log('[AUDIO DEBUG] actual notification playback SUCCESS - hiding bar');
                console.log('[AUDIO] Notification played');
            }).catch(function(error) {
                // Browser actually rejected playback
                audioUnlocked = false;
                pendingPlay = true;

                console.warn('[AUDIO] Notification playback blocked:', error.name, error.message);
                console.trace('[AUDIO DEBUG] showSoundBar triggered after playback failure');

                showSoundBar();
                attachUnlockListeners();
            });
        }
    }

    // Execute initialization once on DOM ready
    $(document).ready(function() {
        initAudioOnPageLoad();
    });
</script>
