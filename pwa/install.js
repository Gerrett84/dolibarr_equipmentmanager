/* Add-to-home-screen helper shared by the PWA pages.
 * Android/Chrome/Edge: one-click install through the beforeinstallprompt event.
 * iOS/Safari has no install API - the only way is the Share menu, so we show a short guide. */
(function () {
    let deferred = null;
    const listeners = [];
    const notify = () => listeners.forEach(fn => { try { fn(); } catch (e) { /* ignore */ } });

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferred = e;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        notify();
    });

    const api = {
        isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        },
        isIOS() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        },
        isIOSSafari() {
            return api.isIOS() && !/CriOS|FxiOS|EdgiOS|OPiOS|GSA\//.test(navigator.userAgent);
        },
        canPrompt() {
            return !!deferred;
        },
        async prompt() {
            if (!deferred) return null;
            deferred.prompt();
            const choice = await deferred.userChoice;
            deferred = null;
            notify();
            return choice.outcome;
        },
        onChange(fn) {
            listeners.push(fn);
        },
        showIosHelp(iconUrl) {
            if (document.getElementById('emIosHelp')) return;
            const overlay = document.createElement('div');
            overlay.id = 'emIosHelp';
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:100000;display:flex;align-items:flex-end;justify-content:center;';
            overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
            const safari = api.isIOSSafari();
            const icon = iconUrl ? `<img src="${iconUrl}" alt="" style="width:64px;height:64px;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.25);flex-shrink:0;">` : '';
            overlay.innerHTML = `
                <div style="background:var(--bg-card,#fff);color:var(--text-primary,#333);border-radius:16px 16px 0 0;padding:20px;width:100%;max-width:480px;box-shadow:0 -4px 24px rgba(0,0,0,.25);font-family:-apple-system,BlinkMacSystemFont,sans-serif;">
                    <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px;">
                        ${icon}
                        <div><div style="font-size:18px;font-weight:600;">Auf den Startbildschirm</div>
                        <div style="font-size:13px;opacity:.7;">So erscheint die App mit dem Firmenlogo wie jede andere App.</div></div>
                    </div>
                    ${safari ? '' : '<div style="background:#fff3e0;color:#e65100;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:14px;">Bitte diese Seite in <strong>Safari</strong> öffnen – nur dort gibt es „Zum Home-Bildschirm“.</div>'}
                    <ol style="margin:0 0 14px 18px;padding:0;line-height:1.7;font-size:15px;">
                        <li>Unten in Safari auf das <strong>Teilen-Symbol</strong> tippen (Quadrat mit Pfeil nach oben)</li>
                        <li>Nach unten scrollen und <strong>„Zum Home-Bildschirm“</strong> wählen</li>
                        <li>Oben rechts auf <strong>„Hinzufügen“</strong> tippen</li>
                    </ol>
                    <button type="button" id="emIosHelpClose" style="width:100%;padding:12px;border:none;border-radius:8px;background:#1a3f6e;color:#fff;font-size:15px;cursor:pointer;">Verstanden</button>
                </div>`;
            document.body.appendChild(overlay);
            document.getElementById('emIosHelpClose').addEventListener('click', () => overlay.remove());
        }
    };
    window.emInstall = api;
})();
