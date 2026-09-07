/**
 * Sub-path the app is served under — "" at a domain root, or "/manage-server"
 * when mounted at https://services.src.ku.ac.th/manage-server. Injected by
 * app.blade.php from Laravel's Request::getBaseUrl().
 */
export const BASE_PATH: string = (() => {
    if (typeof document === 'undefined') {
        return '';
    }

    const meta = document
        .querySelector('meta[name="base-path"]')
        ?.getAttribute('content');

    return (meta ?? '').replace(/\/+$/, '');
})();

/**
 * Prefix an app-absolute path ("/foo") with BASE_PATH. Idempotent, and a
 * no-op for full URLs, hashes, and already-prefixed paths — safe to wrap
 * around any navigation target.
 */
export function bp(path: string): string {
    if (!BASE_PATH || !path.startsWith('/')) {
        return path;
    }

    if (path === BASE_PATH || path.startsWith(BASE_PATH + '/')) {
        return path;
    }

    return BASE_PATH + path;
}

/**
 * Installs global shims so hand-written absolute paths (fetch('/api/...'),
 * router.visit('/x'), history.pushState) resolve under BASE_PATH without
 * every call site having to know about it. No-op when BASE_PATH is empty.
 */
export function installBasePathShims(): void {
    if (!BASE_PATH || typeof window === 'undefined') {
        return;
    }

    // fetch() — covers hand-written fetch('/api/...') calls.
    const nativeFetch = window.fetch.bind(window);

    window.fetch = (input, init) => {
        if (typeof input === 'string') {
            return nativeFetch(bp(input), init);
        }

        if (
            input instanceof Request &&
            input.url.startsWith(window.location.origin)
        ) {
            const u = new URL(input.url);
            const next = bp(u.pathname);

            if (next !== u.pathname) {
                return nativeFetch(
                    new Request(u.origin + next + u.search + u.hash, input),
                    init,
                );
            }
        }

        return nativeFetch(input, init);
    };

    // XMLHttpRequest — Inertia/axios navigation requests go through this.
    const nativeOpen = XMLHttpRequest.prototype.open;

    XMLHttpRequest.prototype.open = function (
        this: XMLHttpRequest,
        method: string,
        url: string | URL,
        ...rest: unknown[]
    ) {
        const next = typeof url === 'string' ? bp(url) : url;

        // Overloaded DOM signature — forwarding the remaining args verbatim
        // is safe; TS can't line the overloads up through .call().
        // @ts-expect-error passthrough of the native variadic signature
        return nativeOpen.call(this, method, next, ...rest);
    };

    // history — keep the address bar under the sub-path even if something
    // pushes a bare "/x" (Inertia's own pushes already carry the prefixed
    // page.url from the server, so those are a no-op here).
    (['pushState', 'replaceState'] as const).forEach((name) => {
        const native = history[name].bind(history);

        history[name] = (
            data: unknown,
            unused: string,
            url?: string | URL | null,
        ) => {
            const next =
                typeof url === 'string' && url.startsWith('/') ? bp(url) : url;

            return native(data, unused, next ?? null);
        };
    });
}
