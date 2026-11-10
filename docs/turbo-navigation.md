Turbo navigation in the admin and restaurant panels
=================================================

Internal links and GET search forms use Turbo. Explicit `data-turbo="false"`,
downloads, external links and modal/tab controls retain their existing behavior.
POST forms retain their existing submission behavior.

The shared layouts load `panel-page.js` and `panel-navigation.js`. The latter
executes body scripts in their original order and awaits external dependencies.
Shared libraries load once; `js/view-pages/` scripts and `custom.js` execute for
each body. Use `data-panel-reload` for another external page script that must
execute on every visit. Maps callbacks execute after their page functions exist;
the Maps API is shared and additional libraries are imported when needed.

Scripts pushed into `css_or_js` are rendered in the body while their styles stay
in the head, so their lifecycle belongs to the incoming page.

Writing page scripts
--------------------

Keep declarations inside a function and create a unique owner for each script:

```javascript
(() => {
    const page = PanelPage.create('restaurantSettings');
    page.ready(() => {
        // Initialize UI and plugins against the incoming body.
    });
    page.on('click', '.save-settings', saveSettings);
    page.listen(window, 'resize', updateLayout);
    page.setInterval(refreshStatus, 30000);
    page.expose({saveSettings}); // Needed only by inline HTML or other scripts.
    page.cleanup(() => connection.disconnect());
})();
```

`ready` works on the first load and subsequent visits, after dependencies load.
It also captures document/window handlers installed by older UI plugins, without
removing handlers belonging to shared libraries. Use `page.mount(callback)` for
plugin initialization outside a ready callback. Use `page.request(xhr)` for
jQuery requests that should abort when leaving the page, and
`page.resource(instance, 'destroy')` for disposable instances.

`page.globals` publishes live getters/setters for declarations shared by legacy
scripts. Global bindings are restored in reverse order when the body leaves.
Avoid adding new unscoped top-level `let` or `const` declarations. A screen's
timers, handlers and connections must belong to its page owner.

The notification audio elements and internet alert are permanent Turbo elements.
The sound interaction service and internet connection monitor remain shared;
page cleanup must not close their audio context or replace their audio elements.

Verification
------------

Run the JavaScript unit tests with:

```text
node --test tests/Unit/panel-page.test.cjs tests/Unit/sound-interaction-alert.test.cjs tests/Unit/internet-sound-interaction.test.cjs
```

The browser regression uses a local fixture server, the repository's actual
jQuery/vendor plugins and Turbo 8.0.13. Supply a copy of the same Turbo script
loaded by the layouts and a Chrome executable:

```text
node tests/Browser/panel-navigation.cjs <turbo-8.0.13.js> <chrome-executable>
```

It checks repeated link visits, GET searches, history, inline callbacks, plugin
listeners, timer cleanup, dependency ordering, Maps callbacks and enabled audio.
It requires no restaurant login and performs no production or database writes.
