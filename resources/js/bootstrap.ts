import axios from 'axios';

// Make axios available as window.axios (typed in resources/js/types/global.d.ts).
window.axios = axios;

// ──────────────────────────────────────────────────────────────────────────
// v3.3 — CSRF token: cookie-based, not meta-based.
//
// Previous versions read the CSRF token from <meta name="csrf-token"> ONCE
// at app boot and pinned it to axios as X-CSRF-TOKEN. Laravel rotates the
// session token on every login (and periodically), so the meta value goes
// stale immediately after login → every subsequent POST returned HTTP 419
// "Page Expired" and the user appeared "logged out during navigation".
//
// Axios's built-in xsrfCookieName / xsrfHeaderName support reads the
// XSRF-TOKEN cookie on EVERY request and forwards it as X-XSRF-TOKEN.
// Laravel updates that cookie whenever the session regenerates, so the
// token is always fresh.
// ──────────────────────────────────────────────────────────────────────────
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName  = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName  = 'X-XSRF-TOKEN';
