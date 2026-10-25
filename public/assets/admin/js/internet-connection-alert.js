(() => {
    const alertBox = document.getElementById('internet-connection-alert');
    const muteButton = document.getElementById('internet-connection-alert-mute');

    if (!alertBox || !muteButton) {
        return;
    }

    const desktopMedia = window.matchMedia('(min-width: 992px)');
    const muteStorageKey = 'gbc-internet-alert-muted';
    let connected = null;
    let muted = false;
    let audioContext = null;
    let soundInterval = null;
    let checkInProgress = false;

    try {
        muted = window.localStorage.getItem(muteStorageKey) === 'true';
    } catch (error) {
        console.warn('Could not load the internet alert sound preference.', error);
    }

    function updateMuteButton() {
        muteButton.textContent = muted ? alertBox.dataset.unmuteLabel : alertBox.dataset.muteLabel;
        muteButton.setAttribute('aria-pressed', muted ? 'true' : 'false');
    }

    function playSound() {
        if (muted || !desktopMedia.matches || connected !== false
            || !audioContext || audioContext.state !== 'running') {
            return;
        }

        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        const now = audioContext.currentTime;

        oscillator.frequency.setValueAtTime(880, now);
        gain.gain.setValueAtTime(0.001, now);
        gain.gain.exponentialRampToValueAtTime(0.2, now + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start(now);
        oscillator.stop(now + 0.5);
    }

    function startSoundNotification() {
        if (muted || !desktopMedia.matches || connected !== false || soundInterval) {
            return;
        }

        playSound();
        soundInterval = window.setInterval(playSound, 5000);
    }

    function stopSoundNotification() {
        if (soundInterval) {
            window.clearInterval(soundInterval);
            soundInterval = null;
        }
    }

    function setConnected(isConnected) {
        if (connected === isConnected) {
            return;
        }

        connected = isConnected;
        alertBox.hidden = isConnected || !desktopMedia.matches;

        if (isConnected) {
            stopSoundNotification();
        } else {
            startSoundNotification();
        }
    }

    function enableSoundAfterUserGesture() {
        const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
        if (!audioContext && !AudioContextConstructor) {
            console.warn('Audio notifications are not supported in this browser.');
            return;
        }

        try {
            audioContext = audioContext || new AudioContextConstructor();
            audioContext.resume().then(() => {
                if (connected === false) {
                    if (soundInterval) {
                        playSound();
                    } else {
                        startSoundNotification();
                    }
                }
            }).catch((error) => {
                console.warn('Could not enable the internet alert sound.', error);
            });
        } catch (error) {
            console.warn('Could not enable the internet alert sound.', error);
        }
    }

    async function checkInternetConnection() {
        if (!desktopMedia.matches || document.hidden || checkInProgress) {
            return;
        }

        if (!navigator.onLine) {
            setConnected(false);
            return;
        }

        checkInProgress = true;
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 5000);

        try {
            await fetch('https://connectivitycheck.gstatic.com/generate_204', {
                cache: 'no-store',
                mode: 'no-cors',
                signal: controller.signal,
            });
            setConnected(true);
        } catch {
            setConnected(false);
        } finally {
            window.clearTimeout(timeout);
            checkInProgress = false;
        }
    }

    function updateDesktopVisibility() {
        alertBox.hidden = connected !== false || !desktopMedia.matches;

        if (desktopMedia.matches) {
            startSoundNotification();
            checkInternetConnection();
        } else {
            stopSoundNotification();
        }
    }

    muteButton.addEventListener('click', () => {
        muted = !muted;
        updateMuteButton();

        try {
            window.localStorage.setItem(muteStorageKey, muted ? 'true' : 'false');
        } catch (error) {
            console.warn('Could not save the internet alert sound preference.', error);
        }

        if (muted) {
            stopSoundNotification();
        } else {
            enableSoundAfterUserGesture();
        }
    });

    window.addEventListener('offline', () => setConnected(false));
    window.addEventListener('online', checkInternetConnection);
    window.addEventListener('pointerdown', enableSoundAfterUserGesture, { once: true, capture: true });
    window.addEventListener('keydown', enableSoundAfterUserGesture, { once: true, capture: true });
    window.addEventListener('resize', updateDesktopVisibility);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            checkInternetConnection();
        }
    });

    updateMuteButton();
    updateDesktopVisibility();
    window.setInterval(checkInternetConnection, 15000);
})();
