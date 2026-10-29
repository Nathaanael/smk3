/**
 * Fetch a file in the background and save it under the name the server gives
 * (Content-Disposition), so pages can show their own loading UI instead of the browser's.
 *
 * Throws an Error with the server's message (validation error or `message`) when the request fails,
 * and an AbortError when `signal` is aborted.
 */
export async function downloadFile(url, { signal, fallbackName = 'download', errorMessage = 'Download failed' } = {}) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal,
    });

    if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        const validation = data.errors && Object.values(data.errors)[0]?.[0];
        throw new Error(validation ?? data.message ?? errorMessage);
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') ?? '';
    const name = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i)?.[1] ?? fallbackName;

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = decodeURIComponent(name);
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}
