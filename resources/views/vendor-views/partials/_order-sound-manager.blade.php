@php
    $soundMode = $soundMode ?? 'once'; // 'continuous' for Kitchen, 'once' for Order List pages
@endphp

{{-- Audio Element & 30-Day Notification Sound Manager Partial with Deterministic Per-Order Alerts --}}
<audio id="new-order-sound" preload="auto">
    <source src="{{ asset('sounds/notification.wav') }}" type="audio/wav">
</audio>

{{-- Non-intrusive 30-day sound enable bar --}}
<div id="sound-enable-bar"
    style="position:fixed;top:0;left:0;right:0;z-index:9999;background:linear-gradient(135deg,#ff9800,#f57c00);color:#fff;text-align:center;padding:12px 20px;font-size:15px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.25);display:none;">
    🔔 Click here to enable order notification sounds
</div>

<style>
    /* Continuous pulse animation for alerting cards */
    @keyframes orderUpdatedPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.8), 0 0 15px 3px rgba(255, 193, 7, 0.8);
            border: 2px solid #dc3545 !important;
            transform: scale(1.01);
        }
        50% {
            box-shadow: 0 0 0 8px rgba(220, 53, 69, 0), 0 0 10px 2px rgba(255, 193, 7, 0.5);
            border: 2px solid #ffc107 !important;
            transform: scale(1.005);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0), 0 0 15px 3px rgba(255, 193, 7, 0.8);
            border: 2px solid #dc3545 !important;
            transform: scale(1.01);
        }
    }

    /* Continuous row pulse animation for alerting table rows */
    @keyframes orderRowUpdatedPulse {
        0% {
            box-shadow: inset 0 0 12px rgba(220, 53, 69, 0.6);
        }
        50% {
            box-shadow: inset 0 0 6px rgba(220, 53, 69, 0.3);
        }
        100% {
            box-shadow: inset 0 0 12px rgba(220, 53, 69, 0.6);
        }
    }

    /* Badge pulse animation */
    @keyframes orderBadgePulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.08); }
        100% { transform: scale(1); }
    }

    .order-updated-alert {
        animation: orderUpdatedPulse 1.5s infinite ease-in-out !important;
        position: relative;
        z-index: 10;
    }

    tr.order-updated-alert, tr.order-updated-alert td {
        animation: orderRowUpdatedPulse 1.5s infinite ease-in-out !important;
    }

    /* Dedicated Realtime Alert Badges (Distinct from status badges) */
    .order-alert-badge {
        padding: 3px 8px !important;
        border-radius: 4px !important;
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        letter-spacing: 0.5px !important;
        display: inline-block !important;
        animation: orderBadgePulse 1.2s infinite ease-in-out !important;
        vertical-align: middle !important;
        z-index: 20 !important;
    }

    .order-updated-badge {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.4) !important;
    }

    .order-new-badge {
        background-color: #28a745 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.4) !important;
    }

    /* Soft, readable yellow background for Cooking Status (Cards & Rows) */
    .order-card.order-status-cooking,
    .order-card[data-order-status="cooking"],
    #orders-container .order-card[data-order-status="cooking"],
    #orders-container [data-order-status="cooking"] .card,
    .card.order-status-cooking {
        background-color: #fff9db !important;
        border-color: #ffe066 !important;
    }

    tr.status-cooking,
    tr[data-order-status="cooking"],
    tr.order-status-cooking {
        background-color: #fff9db !important;
    }

    tr.status-cooking td,
    tr[data-order-status="cooking"] td,
    tr.order-status-cooking td {
        background-color: #fff9db !important;
    }
</style>

<script>
    // ===== 30-Day Order Notification Sound & Alert Manager =====
    const ORDER_ALERT_SOUND_MODE = '{{ $soundMode }}'; // 'continuous' for Kitchen, 'once' for Order List
    const SOUND_PREF_KEY = 'restaurantOrderSoundPermission';
    const SOUND_PREF_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 days in ms
    const STORAGE_ALERT_KEY = 'restaurantActiveOrderAlerts';
    const BEEP_CYCLE_INTERVAL = 2500; // repeating beep interval in ms for Kitchen

    let audioUnlocked = false;
    let isUnlocking = false;

    // Per-Order Alerts Map: orderId (string) => { type: 'new'|'updated', addedAt: number }
    const activeOrderAlerts = new Map();
    let orderAlertSchedulerTimer = null;
    let alertCycleIndex = 0;

    // 1. LocalStorage Management Functions (30-Day User Permission)
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
            console.log('[AUDIO INIT] No saved sound preference');
            return false;
        }
        if (pref.enabled === true && typeof pref.expiresAt === 'number') {
            if (pref.expiresAt > Date.now()) {
                console.log('[AUDIO INIT] Saved preference valid');
                return true;
            } else {
                console.log('[AUDIO INIT] Sound preference expired');
                clearSoundPreference();
                return false;
            }
        }
        clearSoundPreference();
        console.log('[AUDIO INIT] No saved sound preference');
        return false;
    }

    // 2. Storage Persistence for Active Unacknowledged Order Alerts
    function saveActiveAlertsToStorage() {
        try {
            const entries = [];
            activeOrderAlerts.forEach(function(val, key) {
                entries.push({ id: key, type: val.type || 'updated' });
            });
            sessionStorage.setItem(STORAGE_ALERT_KEY, JSON.stringify(entries));
        } catch (e) {}
    }

    function loadActiveAlertsFromStorage() {
        try {
            const raw = sessionStorage.getItem(STORAGE_ALERT_KEY);
            if (!raw) return;
            const items = JSON.parse(raw);
            if (Array.isArray(items)) {
                items.forEach(function(item) {
                    if (item) {
                        const idStr = typeof item === 'object' ? String(item.id) : String(item);
                        const type = (typeof item === 'object' && item.type) ? item.type : 'updated';
                        activeOrderAlerts.set(idStr, { type: type, addedAt: Date.now() });
                        applyOrderAlertVisuals(idStr, type);
                    }
                });
                if (activeOrderAlerts.size > 0) {
                    console.log('[ORDER ALERT DEBUG] Restored activeOrderAlerts from storage:', JSON.stringify(Array.from(activeOrderAlerts.keys())));
                    refreshAlertScheduler();
                }
            }
        } catch (e) {}
    }

    // 3. Audio & UI Elements
    function getAudioElement() {
        let sound = document.getElementById('new-order-sound');
        if (!sound) {
            sound = new Audio("{{ asset('sounds/notification.wav') }}");
            sound.id = 'new-order-sound';
        }
        return sound;
    }

    function showSoundBar() {
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

    // 4. Native Capture-Phase User Gesture Audio Unlock Handler
    function attachUnlockListeners() {
        document.removeEventListener('pointerdown', unlockAudio, true);
        document.removeEventListener('keydown', unlockAudio, true);
        document.removeEventListener('touchstart', unlockAudio, true);
        document.removeEventListener('click', unlockAudio, true);

        document.addEventListener('pointerdown', unlockAudio, true);
        document.addEventListener('keydown', unlockAudio, true);
        document.addEventListener('touchstart', unlockAudio, true);
        document.addEventListener('click', unlockAudio, true);
    }

    function removeUnlockListeners() {
        document.removeEventListener('pointerdown', unlockAudio, true);
        document.removeEventListener('keydown', unlockAudio, true);
        document.removeEventListener('touchstart', unlockAudio, true);
        document.removeEventListener('click', unlockAudio, true);
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
                console.log('[AUDIO INIT] User unlocked audio');

                // If active unacknowledged alerts exist on kitchen, start repeating scheduler
                if (activeOrderAlerts.size > 0) {
                    refreshAlertScheduler();
                    triggerSingleBeep();
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

    // 5. Immediate Page Load Audio Capability Testing (Runs on DOM load without waiting for Pusher)
    function initAudioOnPageLoad() {
        console.log('[AUDIO INIT] Initializing audio manager');
        const sound = getAudioElement();
        const hasValidPreference = isSoundPreferenceValid();

        console.log('[AUDIO INIT] Saved 30-day preference:', hasValidPreference);

        if (!sound) {
            console.error('[AUDIO INIT] Notification audio element not found');
            audioUnlocked = false;
            return;
        }

        sound.muted = false;
        sound.volume = 1.0;
        sound.currentTime = 0;

        // Perform actual unmuted browser playback test
        console.log('[AUDIO INIT] Testing actual unmuted playback capability');
        let testPromise;
        try {
            testPromise = sound.play();
        } catch (error) {
            console.error('[AUDIO INIT] sound.play() threw synchronously:', error);
            audioUnlocked = false;
            showSoundBar();
            attachUnlockListeners();
            return;
        }

        if (!testPromise || typeof testPromise.then !== 'function') {
            console.warn('[AUDIO INIT] Browser did not return a play Promise');
            audioUnlocked = false;
            showSoundBar();
            attachUnlockListeners();
            return;
        }

        testPromise.then(function() {
            sound.pause();
            sound.currentTime = 0;
            audioUnlocked = true;
            window.kitchenAudioEnabled = true;
            hideSoundBar();
            removeUnlockListeners();
            console.log('[AUDIO INIT] Actual unmuted playback SUCCESS');
            console.log('[AUDIO INIT] Browser playback allowed');
        }).catch(function(error) {
            audioUnlocked = false;
            console.error('[AUDIO INIT] Actual playback FAILED:', {
                name: error && error.name,
                message: error && error.message,
                savedPreference: hasValidPreference
            });

            if (error && error.name === 'NotAllowedError') {
                console.log('[AUDIO INIT] Browser autoplay policy blocked sound - showing enable bar');
                showSoundBar();
                attachUnlockListeners();
            } else {
                console.error('[AUDIO INIT] Audio failed for a non-permission reason:', error);
            }
        });
    }

    // 6. Realtime Alert Badge & Visual Handlers
    function removeOrderAlertBadge(orderId) {
        const id = String(orderId);
        $(`[data-alert-badge-for="${id}"]`).remove();
        $(`#order_${id}, #order-row-${id}, #order-card-${id}, [data-order-id="${id}"]`)
            .find('.order-alert-badge, .order-updated-badge, .order-new-badge')
            .remove();
    }

    function applyOrderAlertVisuals(orderId, alertType) {
        const id = String(orderId);
        const type = (alertType === 'new') ? 'new' : 'updated';
        const badgeText = (type === 'new') ? 'NEW' : 'UPDATED';
        const badgeClass = (type === 'new') ? 'order-new-badge' : 'order-updated-badge';
        const badgeHtml = `<span class="order-alert-badge ${badgeClass} ml-1" data-alert-badge-for="${id}">${badgeText}</span>`;

        // 1. Kitchen Card View
        const $kitchenContainer = $('#order_' + id + ', [data-order-id="' + id + '"]');
        if ($kitchenContainer.length) {
            const $card = $kitchenContainer.find('.card').first();
            const $target = $card.length ? $card : $kitchenContainer;

            $target.addClass('order-updated-alert').attr('data-alert-order-id', id);
            $kitchenContainer.addClass('order-updated-alert-wrapper').attr('data-alert-order-id', id);

            // Remove any old alert badges first to prevent duplicate badge accumulation
            removeOrderAlertBadge(id);

            const $heading = $kitchenContainer.find('h4 strong').first();
            if ($heading.length) {
                $heading.after(' ' + badgeHtml);
            } else {
                $kitchenContainer.find('.card-body').first().prepend(`<div class="mb-1">${badgeHtml}</div>`);
            }
            console.log(`[ORDER ALERT] Added ${badgeText} badge for ${id}`);
        }

        // 2. Order List Table Row
        const $orderRow = $('#order-row-' + id + ', tr[data-order-id="' + id + '"]');
        if ($orderRow.length) {
            $orderRow.addClass('order-updated-alert').attr('data-alert-order-id', id);
            removeOrderAlertBadge(id);
            const $serialCol = $orderRow.find('td:nth-child(2)');
            if ($serialCol.length) {
                $serialCol.append(' ' + badgeHtml);
            }
            console.log(`[ORDER ALERT] Added ${badgeText} badge for ${id}`);
        }

        // 3. Order List Card (mobile / grid view)
        const $orderCard = $('#order-card-' + id + ', div[data-order-id="' + id + '"].order-card');
        if ($orderCard.length) {
            $orderCard.addClass('order-updated-alert').attr('data-alert-order-id', id);
            removeOrderAlertBadge(id);
            const $title = $orderCard.find('.order-title').first();
            if ($title.length) {
                $title.append(' ' + badgeHtml);
            } else {
                $orderCard.prepend(`<div class="p-1">${badgeHtml}</div>`);
            }
            console.log(`[ORDER ALERT] Added ${badgeText} badge for ${id}`);
        }
    }

    function removeUpdatedVisuals(orderId) {
        const id = String(orderId);

        // 1. Remove NEW/UPDATED alert badges immediately
        removeOrderAlertBadge(id);

        // 2. Remove alert classes and data-alert-order-id attributes
        const $matchedElements = $(
            `.order-updated-alert[data-alert-order-id="${id}"], ` +
            `.order-updated-alert-wrapper[data-alert-order-id="${id}"], ` +
            `[data-alert-order-id="${id}"], ` +
            `#order_${id}, ` +
            `#order-row-${id}, ` +
            `#order-card-${id}`
        );

        console.log('[ORDER ALERT DEBUG] Removing visuals', id, 'matched elements:', $matchedElements.length);

        $matchedElements.each(function () {
            const $el = $(this);
            $el.removeClass('order-updated-alert order-updated-alert-wrapper');
            $el.removeAttr('data-alert-order-id');
            $el.find('.order-updated-alert').removeClass('order-updated-alert').removeAttr('data-alert-order-id');
            $el.find('.order-updated-alert-wrapper').removeClass('order-updated-alert-wrapper').removeAttr('data-alert-order-id');
        });

        console.log('[ORDER ALERT] Removed realtime badge for', id);
        console.log('[ORDER ALERT] Removed realtime animation for', id);
    }

    // 7. Sound Scheduler (Continuous on Kitchen, One-Shot on Order List)
    function triggerSingleBeep(forOrderId) {
        const sound = getAudioElement();
        if (!sound) return;

        sound.muted = false;
        sound.volume = 1.0;
        sound.currentTime = 0;

        const promise = sound.play();
        if (promise !== undefined) {
            promise.then(function() {
                audioUnlocked = true;
                window.kitchenAudioEnabled = true;
                hideSoundBar();
                if (forOrderId) {
                    console.log('[ORDER ALERT] Beep for ' + forOrderId);
                } else {
                    console.log('[AUDIO] Notification played');
                }
            }).catch(function(error) {
                audioUnlocked = false;
                if (error && error.name === 'NotAllowedError') {
                    showSoundBar();
                    attachUnlockListeners();
                }
            });
        }
    }

    function processAlertCycle() {
        if (ORDER_ALERT_SOUND_MODE !== 'continuous') {
            stopAlertScheduler();
            return;
        }

        if (activeOrderAlerts.size === 0) {
            refreshAlertScheduler();
            return;
        }

        if (!audioUnlocked) {
            showSoundBar();
            attachUnlockListeners();
            return;
        }

        const ids = Array.from(activeOrderAlerts.keys());
        if (ids.length === 0) {
            refreshAlertScheduler();
            return;
        }

        if (alertCycleIndex >= ids.length) {
            alertCycleIndex = 0;
        }

        const currentOrderId = ids[alertCycleIndex];
        alertCycleIndex = (alertCycleIndex + 1) % ids.length;

        triggerSingleBeep(currentOrderId);
    }

    function refreshAlertScheduler() {
        if (ORDER_ALERT_SOUND_MODE !== 'continuous') {
            // In 'once' mode, do not run repeating scheduler
            return;
        }

        if (activeOrderAlerts.size === 0) {
            stopAlertScheduler();

            const sound = document.getElementById('new-order-sound');
            if (sound) {
                sound.pause();
                sound.currentTime = 0;
            }

            console.log('[ORDER ALERT] No active orders — all sound stopped');
            return;
        }

        ensureAlertSchedulerRunning();
    }

    function ensureAlertSchedulerRunning() {
        if (ORDER_ALERT_SOUND_MODE !== 'continuous') return;
        if (!orderAlertSchedulerTimer && activeOrderAlerts.size > 0) {
            orderAlertSchedulerTimer = setInterval(processAlertCycle, BEEP_CYCLE_INTERVAL);
        }
    }

    function stopAlertScheduler() {
        if (orderAlertSchedulerTimer) {
            clearInterval(orderAlertSchedulerTimer);
            orderAlertSchedulerTimer = null;
            alertCycleIndex = 0;
        }
    }

    // 8. Public Per-Order Alert Management Methods
    function markOrderAsNew(orderId) {
        if (!orderId) return;
        const id = String(orderId);
        console.log('[ORDER ALERT] New order', id);
        startOrderAlert(id, 'new');
    }

    function markOrderAsUpdated(orderId) {
        if (!orderId) return;
        const id = String(orderId);
        console.log('[ORDER ALERT] Existing order', id, 'updated');
        startOrderAlert(id, 'updated');
    }

    function startOrderAlert(orderId, alertType = 'updated') {
        if (!orderId) return;
        const id = String(orderId);
        const type = (alertType === 'new') ? 'new' : 'updated';

        console.log('[ORDER ALERT DEBUG]', new Date().toISOString(), 'startOrderAlert', id, type);

        if (activeOrderAlerts.has(id)) {
            console.log('[ORDER ALERT] Order ' + id + ' already alerting - updating visuals');
            applyOrderAlertVisuals(id, type);
            return;
        }

        activeOrderAlerts.set(id, { type: type, addedAt: Date.now() });
        saveActiveAlertsToStorage();
        applyOrderAlertVisuals(id, type);

        console.log('[ORDER ALERT DEBUG] activeOrderAlerts:', JSON.stringify(Array.from(activeOrderAlerts.keys())));

        if (ORDER_ALERT_SOUND_MODE === 'continuous') {
            console.log('[ORDER ALERT] Kitchen continuous alert started');
            refreshAlertScheduler();
            if (audioUnlocked) {
                triggerSingleBeep(id);
            } else {
                showSoundBar();
                attachUnlockListeners();
            }
        } else {
            // 'once' mode: Play sound exactly ONCE on new/update event
            console.log('[ORDER ALERT] Order List single beep for', id);
            if (audioUnlocked) {
                triggerSingleBeep(id);
            } else {
                showSoundBar();
                attachUnlockListeners();
            }
        }
    }

    function stopOrderAlert(orderId) {
        if (!orderId) return;
        const id = String(orderId);

        if (activeOrderAlerts.has(id)) {
            activeOrderAlerts.delete(id);
            saveActiveAlertsToStorage();
            removeUpdatedVisuals(id);
            refreshAlertScheduler();
            console.log('[ORDER ALERT] Alert stopped for ' + id);
        }
    }

    function acknowledgeOrderUpdate(orderId) {
        if (!orderId) return;
        const id = String(orderId);

        console.log('[ORDER ALERT DEBUG]', new Date().toISOString(), 'acknowledgeOrderUpdate', id);
        console.log('[ORDER ALERT] Order', id, 'clicked');

        if (!activeOrderAlerts.has(id)) {
            console.warn(
                '[ORDER ALERT DEBUG] Clicked alert card but ID not found in activeOrderAlerts:',
                id,
                Array.from(activeOrderAlerts.keys())
            );
        }

        // 1. IMPORTANT: State deletion FIRST
        activeOrderAlerts.delete(id);
        saveActiveAlertsToStorage();

        console.log(
            '[ORDER ALERT DEBUG] Active alerts after delete:',
            JSON.stringify(Array.from(activeOrderAlerts.keys()))
        );

        // 2. Visual & Badge removal immediately
        removeUpdatedVisuals(id);

        // 3. Scheduler state refresh (stops repeating sound if last kitchen alert)
        refreshAlertScheduler();

        console.log('[ORDER ALERT] Order acknowledged:', id);
    }

    function stopAllOrderAlerts() {
        const ids = Array.from(activeOrderAlerts.keys());
        ids.forEach(function(id) {
            stopOrderAlert(id);
        });
        refreshAlertScheduler();
    }

    function reapplyOrderAlertState(orderId) {
        if (!orderId) return;
        const id = String(orderId);
        if (activeOrderAlerts.has(id)) {
            const item = activeOrderAlerts.get(id);
            const type = (item && item.type) ? item.type : 'updated';
            applyOrderAlertVisuals(id, type);
            console.log('[ORDER ALERT] Reapplied state after card refresh for ' + id);
        } else {
            removeUpdatedVisuals(id);
        }
    }

    function reapplyAllActiveAlerts() {
        activeOrderAlerts.forEach(function(val, id) {
            reapplyOrderAlertState(id);
        });
    }

    // Backward compatibility for generic playNotificationSound
    function playNotificationSound() {
        triggerSingleBeep();
    }

    // 9. Deterministic Native Capture-Phase Click Detection
    document.addEventListener(
        'click',
        function (event) {
            const card = event.target.closest(
                '.order-updated-alert[data-alert-order-id], [data-alert-order-id]'
            );

            if (!card) {
                return;
            }

            const orderId = String(card.getAttribute('data-alert-order-id'));
            if (!orderId) {
                return;
            }

            console.log(
                '[ORDER ALERT DEBUG] CAPTURE click detected:',
                orderId,
                'target:',
                event.target,
                'card:',
                card
            );

            acknowledgeOrderUpdate(orderId);
        },
        true // Native capture phase: intercepts click BEFORE inner buttons can stop propagation
    );

    // 10. Single-Execution Guard: Initialize immediately when page/DOM loads
    if (!window.orderSoundManagerInitialized) {
        window.orderSoundManagerInitialized = true;

        function runAudioInitWhenReady() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    initAudioOnPageLoad();
                    loadActiveAlertsFromStorage();
                });
            } else {
                initAudioOnPageLoad();
                loadActiveAlertsFromStorage();
            }
        }

        runAudioInitWhenReady();
    }
</script>
