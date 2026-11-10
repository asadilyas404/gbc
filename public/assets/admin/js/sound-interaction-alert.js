(() => {
    if (window.soundInteractionAlert) return;

    const blockedSounds = new Map();
    const allowedSounds = new WeakSet();
    let checkedBody = null;
    let capabilityCheck = 0;

    function update() {
        const bar = document.getElementById('sound-enable-bar');
        if (bar) bar.hidden = blockedSounds.size === 0;
    }

    function requireInteraction(key, retry) {
        blockedSounds.set(key, retry);
        update();
    }

    function clear(key) {
        blockedSounds.delete(key);
        update();
    }

    function play(audio) {
        // Invoke play synchronously so retries retain the browser's user gesture.
        let result;
        try {
            result = audio.play();
        } catch (error) {
            result = Promise.reject(error);
        }
        return Promise.resolve(result).then(() => {
            allowedSounds.add(audio);
            clear(audio);
        }).catch((error) => {
            if (error && error.name === 'NotAllowedError') {
                allowedSounds.delete(audio);
                requireInteraction(audio, () => {
                    if (audio.isConnected) return play(audio);
                    clear(audio);
                });
            }
            throw error;
        });
    }

    function retryBlockedSounds() {
        // Snapshot: callbacks can remove themselves or add another blocked sound.
        Array.from(blockedSounds.values()).forEach((retry) => {
            try {
                Promise.resolve(retry()).catch(() => {});
            } catch (error) {
                // Keep the prompt available for a later gesture.
            }
        });
    }

    function checkPageSoundPermission() {
        if (checkedBody === document.body) return;
        checkedBody = document.body;

        // Order list and kitchen pages already test their own notification audio.
        if (document.getElementById('order-sound-manager')) return;
        const notification = document.getElementById('myAudio');
        if (!notification) return;
        if (allowedSounds.has(notification)) return;

        // Use a separate element so the check cannot stop a real notification.
        const probe = notification.cloneNode(true);
        probe.removeAttribute('id');
        probe.muted = false;
        probe.volume = 1;
        const check = ++capabilityCheck;

        function tryPlayback() {
            let result;
            try {
                result = probe.play();
            } catch (error) {
                result = Promise.reject(error);
            }
            return Promise.resolve(result).then(() => {
                probe.pause();
                probe.currentTime = 0;
                if (check === capabilityCheck) {
                    allowedSounds.add(notification);
                    clear('page-sound-permission');
                }
            }).catch((error) => {
                if (check === capabilityCheck && error && error.name === 'NotAllowedError') {
                    requireInteraction('page-sound-permission', tryPlayback);
                }
            });
        }

        tryPlayback();
    }

    window.soundInteractionAlert = { requireInteraction, clear, play };
    document.addEventListener('click', retryBlockedSounds, true);
    document.addEventListener('keydown', retryBlockedSounds, true);
    document.addEventListener('turbo:load', () => {
        update();
        checkPageSoundPermission();
    });
    document.addEventListener('turbo:before-cache', () => {
        capabilityCheck++;
        checkedBody = null;
        blockedSounds.clear();
        update();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkPageSoundPermission);
    } else {
        checkPageSoundPermission();
    }
    update();
})();
