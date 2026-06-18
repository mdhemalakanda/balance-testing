/**
 * Authenticated fetch helper for balance-testing REST API (same origin).
 */
export async function btFetch(path, options = {}) {
  const base = window.btAdmin?.restUrl ?? '/wp-json/balance-testing/v1/';
  const nonce = window.btAdmin?.nonce ?? '';
  const url = `${base.replace(/\/?$/, '/')}${path.replace(/^\//, '')}`;

  const headers = {
    'X-WP-Nonce': nonce,
    ...(options.headers ?? {}),
  };

  if (options.body && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    if (typeof options.body !== 'string') {
      options.body = JSON.stringify(options.body);
    }
  }

  return fetch(url, { ...options, headers, credentials: 'same-origin' });
}
