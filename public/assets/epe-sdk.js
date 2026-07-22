(function () {
  "use strict";

  var config = window.ExoticPushEngineConfig || {};
  var state = {
    launcher: null,
    tray: null,
    trayList: null,
    trayItems: [],
    bellOffset: 20,
  };

  var BELL_ICON_SVG =
    '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
    '<path d="M12 4.5a4.5 4.5 0 0 0-4.5 4.5v2.7c0 .9-.32 1.78-.9 2.48L5.4 15.5a1 1 0 0 0 .77 1.64h11.66a1 1 0 0 0 .77-1.64l-1.2-1.32a3.8 3.8 0 0 1-.9-2.48V9a4.5 4.5 0 0 0-4.5-4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>' +
    '<path d="M9.8 19.5a2.3 2.3 0 0 0 4.4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>' +
    "</svg>";

  if (!("serviceWorker" in navigator) || !("PushManager" in window) || !("Notification" in window)) {
    return;
  }

  function decodeBase64Url(value) {
    var normalized = String(value || "").replace(/-/g, "+").replace(/_/g, "/");
    var padding = "=".repeat((4 - (normalized.length % 4)) % 4);
    var raw = atob(normalized + padding);
    var bytes = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) {
      bytes[i] = raw.charCodeAt(i);
    }
    return bytes;
  }

  function detectBrowser() {
    var ua = navigator.userAgent;
    if (/Edg\//.test(ua)) return "Edge";
    if (/Chrome\//.test(ua) && !/Chromium\//.test(ua)) return "Chrome";
    if (/Firefox\//.test(ua)) return "Firefox";
    if (/Safari\//.test(ua)) return "Safari";
    return "Unknown";
  }

  function detectDeviceType() {
    var ua = navigator.userAgent;
    if (/iPad|Tablet/.test(ua)) return "tablet";
    if (/Mobi|Android|iPhone/.test(ua)) return "mobile";
    return "desktop";
  }

  function getSiteKey() {
    return config.siteKey || "site";
  }

  function getDismissKey() {
    return "epe:prompt:dismissed:" + getSiteKey();
  }

  function getUnsubscribedKey() {
    return "epe:subscription:unsubscribed:" + getSiteKey();
  }

  function shouldSuppressPrompt() {
    var delayDays = Number(config.optInPromptRepromptDelayDays || 0);
    if (!delayDays) {
      return false;
    }

    try {
      var raw = window.localStorage.getItem(getDismissKey());
      if (!raw) {
        return false;
      }

      var dismissedAt = Number(raw);
      if (!dismissedAt) {
        return false;
      }

      return Date.now() - dismissedAt < delayDays * 24 * 60 * 60 * 1000;
    } catch {
      return false;
    }
  }

  function markPromptDismissed() {
    try {
      window.localStorage.setItem(getDismissKey(), String(Date.now()));
    } catch {
      return;
    }
  }

  function clearPromptDismissed() {
    try {
      window.localStorage.removeItem(getDismissKey());
    } catch {
      return;
    }
  }

  function getSeenNotificationsKey() {
    return "epe:notifications:seen:" + getSiteKey();
  }

  function readSeenNotificationIds() {
    try {
      var raw = window.localStorage.getItem(getSeenNotificationsKey());
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  function markNotificationsSeen(notifications) {
    try {
      var seen = readSeenNotificationIds();
      notifications.forEach(function (notification) {
        if (notification && notification.id && seen.indexOf(notification.id) === -1) {
          seen.push(notification.id);
        }
      });
      // The public feed only ever serves the 10 most recent, so a short
      // tail is enough to decide what's already been seen.
      window.localStorage.setItem(getSeenNotificationsKey(), JSON.stringify(seen.slice(-50)));
    } catch {
      return;
    }
  }

  function countUnseenNotifications(notifications) {
    var seen = readSeenNotificationIds();
    return notifications.filter(function (notification) {
      return notification && notification.id && seen.indexOf(notification.id) === -1;
    }).length;
  }

  function updateLauncherBadge(count) {
    if (!state.launcher) {
      return;
    }

    var badge = state.launcher.querySelector(".epe-optin-launcher__badge");
    if (!count) {
      if (badge) {
        badge.remove();
      }
      state.launcher.setAttribute("aria-label", "Open recent notifications");
      return;
    }

    if (!badge) {
      badge = document.createElement("span");
      badge.className = "epe-optin-launcher__badge";
      badge.setAttribute("aria-hidden", "true");
      state.launcher.appendChild(badge);
    }
    badge.textContent = count > 9 ? "9+" : String(count);
    state.launcher.setAttribute(
      "aria-label",
      "Open recent notifications (" + count + " new)",
    );
  }

  function refreshLauncherBadge() {
    fetchRecentNotifications().then(function (notifications) {
      updateLauncherBadge(countUnseenNotifications(notifications));
    });
  }

  function isUnsubscribedLocally() {
    try {
      return window.localStorage.getItem(getUnsubscribedKey()) === "1";
    } catch {
      return false;
    }
  }

  function markLocallyUnsubscribed() {
    try {
      window.localStorage.setItem(getUnsubscribedKey(), "1");
    } catch {
      return;
    }
  }

  function clearLocalUnsubscribe() {
    try {
      window.localStorage.removeItem(getUnsubscribedKey());
    } catch {
      return;
    }
  }

  function clampLimit(value) {
    var parsed = Number(value || 3);
    if (!parsed || parsed < 1) {
      return 3;
    }
    return Math.min(parsed, 10);
  }

  function getBellAvoidanceOffset() {
    var offset = 0;
    var nodes = document.body ? document.body.querySelectorAll("*") : [];

    for (var i = 0; i < nodes.length; i++) {
      var element = nodes[i];
      if (!element || !element.getBoundingClientRect) {
        continue;
      }

      if (element.classList && element.classList.contains("epe-optin-launcher")) {
        continue;
      }
      if (element.classList && element.classList.contains("epe-notification-tray")) {
        continue;
      }

      var style = window.getComputedStyle(element);
      if (style.display === "none" || style.visibility === "hidden") {
        continue;
      }

      if (style.position !== "fixed" && style.position !== "sticky") {
        continue;
      }

      var rect = element.getBoundingClientRect();
      if (rect.width <= 0 || rect.height <= 0) {
        continue;
      }

      var viewportHeight = window.innerHeight || document.documentElement.clientHeight;
      var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
      if (rect.right <= 0 || rect.left >= viewportWidth) {
        continue;
      }

      // Only treat an element as a "bottom bar" we need to avoid if it's
      // actually anchored flush against the bottom edge -- not merely
      // overlapping the bottom-left region. Tall fixed elements (off-canvas
      // nav drawers, full-height overlays many themes leave in the DOM at
      // opacity:0 rather than display:none) satisfy the old, looser check
      // and were pushing the bell's offset up by nearly a full viewport
      // height, landing it near the top of the page or off-screen entirely.
      var isFlushToBottom = Math.abs(viewportHeight - rect.bottom) <= 24;
      var intersectsBottomLeft = rect.left <= 140 && isFlushToBottom;
      if (!intersectsBottomLeft) {
        continue;
      }

      offset = Math.max(offset, Math.max(0, viewportHeight - rect.top) + 16);
    }

    // Safety net regardless of the heuristic above: never let a single
    // obstruction push the launcher further than this from the bottom edge.
    return Math.min(offset, 200);
  }

  function updateBellPosition() {
    if (!state.launcher) {
      return;
    }

    state.bellOffset = getBellAvoidanceOffset();
    state.launcher.style.left = "20px";
    state.launcher.style.bottom = 20 + state.bellOffset + "px";

    if (state.tray) {
      state.tray.style.left = "20px";
      state.tray.style.bottom = 92 + state.bellOffset + "px";
    }
  }

  function createStyles() {
    if (document.getElementById("epe-optin-styles")) {
      return;
    }

    var style = document.createElement("style");
    style.id = "epe-optin-styles";
    style.textContent = `
      .epe-optin-backdrop {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        padding: 16px;
        pointer-events: none;
        animation: epeOptinFade 180ms ease-out;
      }
      .epe-optin-backdrop--lightbox-1 {
        align-items: flex-start;
        justify-content: center;
        padding-top: 24px;
      }
      .epe-optin-backdrop--lightbox-2 {
        align-items: center;
        justify-content: center;
      }
      .epe-optin-backdrop--bell-icon {
        align-items: flex-end;
        justify-content: flex-start;
        padding-bottom: 24px;
        padding-left: 24px;
      }
      @media (max-width: 767px) {
        .epe-optin-backdrop--lightbox-1 {
          align-items: flex-end;
          padding-bottom: 24px;
          padding-top: 16px;
        }
        .epe-optin-backdrop--bell-icon {
          padding-left: 16px;
          padding-bottom: 16px;
        }
      }
      .epe-optin-panel {
        position: relative;
        width: min(440px, 100%);
        border-radius: 28px;
        overflow: hidden;
        background: #ffffff;
        box-shadow: 0 30px 90px rgba(15, 23, 42, 0.25);
        animation: epeOptinRise 220ms ease-out;
        pointer-events: auto;
      }
      .epe-optin-panel[data-type="lightbox-2"] {
        width: min(400px, 100%);
      }
      .epe-optin-panel[data-type="bell-icon"] {
        width: min(360px, 100%);
      }
      .epe-optin-shell {
        display: grid;
        gap: 16px;
        padding: 24px;
      }
      .epe-optin-header {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 18px;
        align-items: start;
      }
      .epe-optin-icon {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        overflow: hidden;
        background: rgba(15, 23, 42, 0.04);
        display: grid;
        place-items: center;
      }
      .epe-optin-icon img {
        width: 48px;
        height: 48px;
        object-fit: contain;
      }
      .epe-optin-headline {
        margin: 0;
        font-size: 1.125rem;
        line-height: 1.2;
        font-weight: 700;
      }
      .epe-optin-text {
        margin: 6px 0 0;
        font-size: 0.95rem;
        line-height: 1.5;
      }
      .epe-optin-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
      }
      .epe-optin-button {
        min-height: 46px;
        padding: 0 18px;
        border: 0;
        border-radius: 14px;
        cursor: pointer;
        font-weight: 700;
        letter-spacing: 0.01em;
        font-size: 0.95rem;
        line-height: 1;
      }
      .epe-optin-button:focus-visible,
      .epe-optin-close:focus-visible,
      .epe-optin-launcher:focus-visible,
      .epe-notification-tray__unsubscribe:focus-visible {
        outline: 2px solid #2563eb;
        outline-offset: 2px;
      }
      .epe-optin-close {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 36px;
        height: 36px;
        display: grid;
        place-items: center;
        border: 0;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.45);
        color: #ffffff;
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
      }
      .epe-optin-close:hover {
        background: rgba(15, 23, 42, 0.6);
      }
      .epe-optin-launcher {
        position: fixed;
        z-index: 2147483000;
        width: 40px;
        height: 40px;
        border-radius: 999px;
        border: 0;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.18);
        display: grid;
        place-items: center;
        opacity: 0.85;
        transition: opacity 0.15s ease, transform 0.15s ease;
      }
      .epe-optin-launcher:hover {
        opacity: 1;
        transform: scale(1.05);
      }
      .epe-optin-launcher svg {
        width: 18px;
        height: 18px;
      }
      .epe-optin-launcher__badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #dc2626;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
        box-shadow: 0 0 0 2px #ffffff;
        box-sizing: border-box;
      }
      .epe-notification-tray {
        position: fixed;
        z-index: 2147483000;
        width: min(360px, calc(100vw - 32px));
        max-height: min(460px, calc(100vh - 132px));
        border-radius: 24px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.98);
        color: #0f172a;
        box-shadow: 0 24px 72px rgba(15, 23, 42, 0.22);
        display: grid;
        grid-template-rows: auto minmax(0, 1fr) auto;
        backdrop-filter: blur(10px);
      }
      .epe-notification-tray__header,
      .epe-notification-tray__footer {
        padding: 16px 18px;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
      }
      .epe-notification-tray__footer {
        border-bottom: 0;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
      }
      .epe-notification-tray__title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
      }
      .epe-notification-tray__list {
        overflow: auto;
        padding: 12px;
        display: grid;
        gap: 10px;
      }
      .epe-notification-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 18px;
        background: rgba(15, 23, 42, 0.04);
        border: 1px solid rgba(15, 23, 42, 0.07);
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: background 0.15s ease;
      }
      .epe-notification-card:hover {
        background: rgba(15, 23, 42, 0.08);
      }
      .epe-notification-card__icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
      }
      .epe-notification-card__title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
      }
      .epe-notification-empty {
        padding: 20px;
        color: rgba(15, 23, 42, 0.68);
        font-size: 0.9rem;
      }
      .epe-notification-tray__unsubscribe {
        border: 0;
        background: transparent;
        color: #b91c1c;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        padding: 0;
      }
      @keyframes epeOptinFade { from { opacity: 0; } to { opacity: 1; } }
      @keyframes epeOptinRise { from { transform: translateY(14px) scale(0.98); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
    `;
    document.head.appendChild(style);
  }

  function subscriptionKeyMatches(subscription, desiredKeyBytes) {
    try {
      var existingKey = subscription.options && subscription.options.applicationServerKey;
      if (!existingKey) {
        return false;
      }
      var existingBytes = new Uint8Array(existingKey);
      if (existingBytes.length !== desiredKeyBytes.length) {
        return false;
      }
      for (var i = 0; i < existingBytes.length; i++) {
        if (existingBytes[i] !== desiredKeyBytes[i]) {
          return false;
        }
      }
      return true;
    } catch {
      return false;
    }
  }

  function registerSubscription(registration) {
    if (!config.apiUrl || !getSiteKey() || !config.vapidPublicKey) {
      return Promise.resolve(null);
    }

    var desiredKey = decodeBase64Url(config.vapidPublicKey);

    return registration.pushManager
      .getSubscription()
      .then(function (existing) {
        if (existing && subscriptionKeyMatches(existing, desiredKey)) {
          return existing;
        }

        var subscribeFresh = function () {
          return registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: desiredKey,
          });
        };

        // pushManager.subscribe() returns an already-active subscription
        // as-is, even if it was created under a since-rotated VAPID key --
        // it does NOT renegotiate the key pair. Sends signed with the
        // current private key are then rejected by the push service and
        // never reach the browser, even though registerSubscription still
        // succeeds and a subscriber row gets created. Tearing down a
        // key-mismatched subscription first forces a fresh one under the
        // current key.
        if (existing) {
          return existing.unsubscribe().then(subscribeFresh);
        }

        return subscribeFresh();
      })
      .then(function (subscription) {
        if (!subscription) {
          return null;
        }

        var subJson = subscription.toJSON();
        var keys = subJson.keys || {};

        clearLocalUnsubscribe();

        return fetch(config.apiUrl + "/subscribers/register", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            siteId: getSiteKey(),
            browser: detectBrowser(),
            deviceType: detectDeviceType(),
            language: (navigator.language || "en").slice(0, 10),
            subscriptionEndpoint: subscription.endpoint,
            p256dhKey: keys.p256dh || null,
            authKey: keys.auth || null,
          }),
        }).then(function (response) {
          // fetch() only rejects on network failure, never on HTTP error status --
          // without this check, a 400/404/500 from the API was silently ignored
          // and the UI went on to show the subscriber launcher as if registration
          // had actually succeeded, even though nothing was ever persisted.
          if (!response.ok) {
            throw new Error("Subscriber registration failed with status " + response.status);
          }
          return response.json().then(function (payload) {
            var subscriberId = payload && payload.data && payload.data.id;
            if (subscriberId) {
              // Host apps have no other way to learn which EPE subscriber this
              // browser became -- surface it so they can correlate it to their
              // own signed-in user and target sends at that one subscriber.
              window.dispatchEvent(new CustomEvent("epe:subscribed", { detail: { subscriberId: subscriberId } }));
            }
            return subscription;
          }).catch(function () {
            return subscription;
          });
        });
      });
  }

  function fetchRecentNotifications(limit) {
    if (!config.apiUrl || !getSiteKey()) {
      return Promise.resolve([]);
    }

    var notificationsUrl = config.apiUrl + "/sites/public/" + encodeURIComponent(getSiteKey()) + "/notifications";
    if (typeof limit !== "undefined") {
      notificationsUrl += "?limit=" + clampLimit(limit);
    }

    return fetch(notificationsUrl, {
      headers: { Accept: "application/json" },
    })
      .then(function (response) {
        return response.json().catch(function () {
          return { data: [] };
        });
      })
      .then(function (payload) {
        if (!payload || !Array.isArray(payload.data)) {
          return [];
        }

        return payload.data;
      })
      .catch(function () {
        return [];
      });
  }

  function trackPageVisit() {
    if (!config.apiUrl || !getSiteKey()) {
      return;
    }

    fetch(config.apiUrl + "/workflow/track", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        siteId: getSiteKey(),
        triggerEvent: "page_visit",
        payload: { url: window.location.href, referrer: document.referrer || null },
      }),
    }).catch(function () {
      return undefined;
    });
  }

  function closePrompt(backdrop) {
    markPromptDismissed();
    backdrop.remove();
    document.removeEventListener("keydown", backdrop._epeKeydownHandler);
  }

  function showPrompt(registration) {
    if (shouldSuppressPrompt()) {
      return;
    }

    createStyles();

    var backdrop = document.createElement("div");
    backdrop.className = "epe-optin-backdrop epe-optin-backdrop--" + (config.optInPromptType || "lightbox-1");

    var panel = document.createElement("div");
    panel.className = "epe-optin-panel";
    panel.dataset.type = config.optInPromptType || "lightbox-1";
    panel.setAttribute("role", "dialog");
    panel.setAttribute("aria-modal", "true");
    panel.setAttribute("aria-label", config.optInPromptHeadline || "Notification prompt");
    panel.style.background = config.optInPromptBackgroundColor || "#ffffff";

    var shell = document.createElement("div");
    shell.className = "epe-optin-shell";

    var close = document.createElement("button");
    close.className = "epe-optin-close";
    close.type = "button";
    close.setAttribute("aria-label", "Dismiss");
    close.textContent = "×";
    close.addEventListener("click", function () {
      closePrompt(backdrop);
    });

    var header = document.createElement("div");
    header.className = "epe-optin-header";

    var icon = document.createElement("div");
    icon.className = "epe-optin-icon";
    if (config.optInPromptIconUrl) {
      var img = document.createElement("img");
      img.alt = "";
      img.src = config.optInPromptIconUrl;
      icon.appendChild(img);
    }

    var copy = document.createElement("div");
    var headline = document.createElement("h2");
    headline.className = "epe-optin-headline";
    headline.textContent = config.optInPromptHeadline || "Stay in the loop";
    headline.style.color = config.optInPromptHeadlineTextColor || "#111111";

    var text = document.createElement("p");
    text.className = "epe-optin-text";
    text.textContent = config.optInPromptText || "Get important updates delivered to your browser.";
    text.style.color = config.optInPromptTextColor || "#444444";

    copy.appendChild(headline);
    copy.appendChild(text);
    header.appendChild(icon);
    header.appendChild(copy);

    var actions = document.createElement("div");
    actions.className = "epe-optin-actions";

    var cancel = document.createElement("button");
    cancel.className = "epe-optin-button";
    cancel.type = "button";
    cancel.textContent = config.optInPromptCancelButtonLabel || "Not now";
    cancel.style.color = config.optInPromptCancelButtonTextColor || "#ffffff";
    cancel.style.background = config.optInPromptCancelButtonBackgroundColor || "#111111";
    cancel.addEventListener("click", function () {
      closePrompt(backdrop);
    });

    var approve = document.createElement("button");
    approve.className = "epe-optin-button";
    approve.type = "button";
    approve.textContent = config.optInPromptApproveButtonLabel || "Enable";
    approve.style.color = config.optInPromptApproveButtonTextColor || "#ffffff";
    approve.style.background = config.optInPromptApproveButtonBackgroundColor || "#ea580c";
    approve.addEventListener("click", function () {
      clearPromptDismissed();
      Notification.requestPermission().then(function (permission) {
        backdrop.remove();
        if (permission !== "granted") {
          markPromptDismissed();
          return;
        }

        registerSubscription(registration)
          .then(function (subscription) {
            if (subscription) {
              renderSubscriberLauncher(registration);
            }
          })
          .catch(function () {
            return undefined;
          });
      });
    });

    actions.appendChild(cancel);
    actions.appendChild(approve);

    shell.appendChild(close);
    shell.appendChild(header);
    shell.appendChild(actions);
    panel.appendChild(shell);
    backdrop.appendChild(panel);
    document.body.appendChild(backdrop);

    backdrop._epeKeydownHandler = function (event) {
      if (event.key === "Escape") {
        closePrompt(backdrop);
      }
    };
    document.addEventListener("keydown", backdrop._epeKeydownHandler);
    close.focus();
  }

  function removeLauncher() {
    if (state.launcher) {
      state.launcher.remove();
      state.launcher = null;
    }
  }

  function removeTray() {
    if (state.tray) {
      state.tray.remove();
      state.tray = null;
      state.trayList = null;
    }
  }

  function closeTrayAndLauncher() {
    removeTray();
    removeLauncher();
  }

  function unsubscribeCurrentSubscription(registration) {
    return registration.pushManager.getSubscription().then(function (subscription) {
      if (!subscription) {
        return false;
      }

      return subscription.unsubscribe().then(function () {
        var unsubscribeRequest = config.apiUrl && getSiteKey()
          ? fetch(config.apiUrl + "/subscribers/unsubscribe", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                siteId: getSiteKey(),
                subscriptionEndpoint: subscription.endpoint,
              }),
            })
          : Promise.resolve();

        return Promise.resolve(unsubscribeRequest)
          .catch(function () {
            return undefined;
          })
          .then(function () {
            markLocallyUnsubscribed();
            closeTrayAndLauncher();
            return true;
          });
      });
    });
  }

  function renderNotificationTray(registration) {
    if (state.tray) {
      return;
    }

    createStyles();

    var tray = document.createElement("div");
    tray.className = "epe-notification-tray";
    tray.style.left = "20px";
    tray.style.bottom = 92 + state.bellOffset + "px";

    var header = document.createElement("div");
    header.className = "epe-notification-tray__header";
    header.innerHTML = "<p class=\"epe-notification-tray__title\">Recent notifications</p>";

    var list = document.createElement("div");
    list.className = "epe-notification-tray__list";

    var footer = document.createElement("div");
    footer.className = "epe-notification-tray__footer";

    var unsubscribe = document.createElement("button");
    unsubscribe.className = "epe-notification-tray__unsubscribe";
    unsubscribe.type = "button";
    unsubscribe.textContent = "Unsubscribe";
    unsubscribe.addEventListener("click", function () {
      unsubscribeCurrentSubscription(registration).catch(function () {
        return undefined;
      });
    });

    footer.appendChild(unsubscribe);
    tray.appendChild(header);
    tray.appendChild(list);
    tray.appendChild(footer);
    document.body.appendChild(tray);
    state.tray = tray;
    state.trayList = list;
    updateBellPosition();

    populateTrayList(list);
  }

  function populateTrayList(list) {
    fetchRecentNotifications().then(function (notifications) {
      // The tray may have been closed (or re-rendered) while the fetch was
      // in flight -- don't write into a detached list.
      if (!list.isConnected) {
        return;
      }

      state.trayItems = notifications;
      list.innerHTML = "";

      // An open tray counts as reading everything currently in it.
      markNotificationsSeen(notifications);
      updateLauncherBadge(0);

      if (!notifications.length) {
        var empty = document.createElement("div");
        empty.className = "epe-notification-empty";
        empty.textContent = "No recent notifications yet.";
        list.appendChild(empty);
        return;
      }

      notifications.forEach(function (notification) {
        var card = document.createElement(notification.url ? "a" : "article");
        card.className = "epe-notification-card";

        if (notification.url) {
          card.href = notification.url;
          card.target = "_blank";
          card.rel = "noopener noreferrer";
        }

        if (notification.iconUrl) {
          var icon = document.createElement("img");
          icon.className = "epe-notification-card__icon";
          icon.src = notification.iconUrl;
          icon.alt = "";
          card.appendChild(icon);
        }

        var title = document.createElement("h3");
        title.className = "epe-notification-card__title";
        title.textContent = notification.title || "Notification";
        card.appendChild(title);

        list.appendChild(card);
      });
    });
  }

  function renderSubscriberLauncher(registration) {
    createStyles();
    removeLauncher();

    var bell = document.createElement("button");
    bell.type = "button";
    bell.className = "epe-optin-launcher";
    bell.setAttribute("aria-label", "Open recent notifications");
    bell.style.background = config.optInPromptApproveButtonBackgroundColor || "#ea580c";
    bell.style.color = config.optInPromptApproveButtonTextColor || "#ffffff";
    bell.innerHTML = BELL_ICON_SVG;
    bell.addEventListener("click", function () {
      if (state.tray) {
        removeTray();
        updateBellPosition();
        return;
      }

      renderNotificationTray(registration);
    });

    document.body.appendChild(bell);
    state.launcher = bell;
    updateBellPosition();
    refreshLauncherBadge();
  }

  function renderOptInLauncher(registration) {
    createStyles();
    removeLauncher();

    var bell = document.createElement("button");
    bell.type = "button";
    bell.className = "epe-optin-launcher";
    bell.setAttribute("aria-label", "Open notification prompt");
    bell.style.background = config.optInPromptApproveButtonBackgroundColor || "#ea580c";
    bell.style.color = config.optInPromptApproveButtonTextColor || "#ffffff";
    bell.innerHTML = BELL_ICON_SVG;
    bell.addEventListener("click", function () {
      showPrompt(registration);
      removeLauncher();
    });

    document.body.appendChild(bell);
    state.launcher = bell;
    updateBellPosition();
  }

  function handlePushReceived() {
    if (state.tray && state.trayList) {
      populateTrayList(state.trayList);
    } else {
      refreshLauncherBadge();
    }
  }

  function init() {
    if (!config.apiUrl || !getSiteKey() || !config.vapidPublicKey) {
      return;
    }

    trackPageVisit();

    // The service worker posts this when a push lands, so the badge (and an
    // open tray) update without a reload.
    navigator.serviceWorker.addEventListener("message", function (event) {
      if (!event.data || event.data.type !== "epe:push-received") {
        return;
      }

      // The recents feed only lists a campaign once its whole send job has
      // finished, and an individual device usually gets its push mid-job --
      // refresh now for the fast path and once more for the stragglers.
      handlePushReceived();
      setTimeout(handlePushReceived, 5000);
    });

    navigator.serviceWorker
      .register(config.serviceWorkerUrl || "/push-sw.js", { scope: "/" })
      .then(function (registration) {
        if (isUnsubscribedLocally()) {
          return;
        }

        if (Notification.permission === "granted") {
          return registerSubscription(registration).then(function (subscription) {
            if (subscription) {
              renderSubscriberLauncher(registration);
            }
          });
        }

        if (Notification.permission === "denied") {
          return;
        }

        if ((config.optInPromptType || "lightbox-1") === "bell-icon") {
          renderOptInLauncher(registration);
          return;
        }

        showPrompt(registration);
      })
      .catch(function () {
        return undefined;
      });

    window.addEventListener(
      "resize",
      function () {
        updateBellPosition();
      },
      { passive: true },
    );
  }

  if (document.readyState === "complete" || document.readyState === "interactive") {
    init();
  } else {
    window.addEventListener("DOMContentLoaded", init, { once: true });
  }
})();
