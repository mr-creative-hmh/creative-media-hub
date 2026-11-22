import { computed, getCurrentScope, onScopeDispose, ref } from 'vue';

export type ActionState = 'idle' | 'loading' | 'success' | 'error';

export type ActionPreset =
    | 'save'
    | 'download'
    | 'generate'
    | 'upload'
    | 'send'
    | 'delete'
    | 'default';

export interface UseActionStateOptions {
    successDurationMs?: number;
    errorDurationMs?: number;
}

export interface RunActionOptions<T = unknown> {
    onSuccess?: (result: T) => void;
    onError?: (error: unknown) => void;
    throwOnError?: boolean;
}

function parseContentDispositionFilename(header: string | null): string | null {
    if (!header) {
        return null;
    }

    const utf8Match = header.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
    if (utf8Match?.[1]) {
        try {
            return decodeURIComponent(
                utf8Match[1].trim().replace(/^["']|["']$/g, ''),
            );
        } catch {
            return utf8Match[1].trim().replace(/^["']|["']$/g, '');
        }
    }

    const quotedMatch = header.match(/filename\s*=\s*"([^"]+)"/i);
    if (quotedMatch?.[1]) {
        return quotedMatch[1].trim();
    }

    const bareMatch = header.match(/filename\s*=\s*([^;]+)/i);
    if (bareMatch?.[1]) {
        return bareMatch[1].trim().replace(/^["']|["']$/g, '');
    }

    return null;
}

function resolveFallbackFilename(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);
        const segment = parsed.pathname.split('/').filter(Boolean).pop();
        return segment ? decodeURIComponent(segment) : 'download';
    } catch {
        return 'download';
    }
}

export function triggerBlobDownload(blob: Blob, filename: string): void {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(() => {
        URL.revokeObjectURL(objectUrl);
    }, 1000);
}

/**
 * Download a file via `fetch` + `Blob` with streaming byte-progress callbacks
 * so ActionButton / Wave 3 views can display live loading and completion states.
 */
export async function downloadWithProgress(
    url: string,
    filename?: string,
    onProgress?: (percent: number) => void,
    requestInit?: RequestInit,
): Promise<void> {
    const headers = new Headers(requestInit?.headers);
    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...requestInit,
        headers,
    });

    if (!response.ok) {
        let message = `Download failed (${response.status})`;
        const contentType = response.headers.get('content-type') ?? '';
        if (contentType.includes('application/json')) {
            try {
                const payload = (await response.json()) as { message?: string };
                if (payload?.message) {
                    message = payload.message;
                }
            } catch {
                // Keep default HTTP status message.
            }
        }
        throw new Error(message);
    }

    const acceptHeader = headers.get('Accept')?.toLowerCase() ?? '';
    const responseContentType = (
        response.headers.get('content-type') ?? ''
    ).toLowerCase();

    if (
        acceptHeader !== '' &&
        !acceptHeader.includes('text/html') &&
        responseContentType.includes('text/html')
    ) {
        throw new Error(
            `Download failed: unexpected HTML response (${response.status}).`,
        );
    }

    const resolvedName =
        filename ??
        parseContentDispositionFilename(
            response.headers.get('content-disposition'),
        ) ??
        resolveFallbackFilename(url);

    const contentLengthHeader = response.headers.get('content-length');
    const totalBytes = contentLengthHeader
        ? Number.parseInt(contentLengthHeader, 10)
        : 0;

    let blob: Blob;

    if (response.body && Number.isFinite(totalBytes) && totalBytes > 0) {
        const reader = response.body.getReader();
        const chunks: BlobPart[] = [];
        let loadedBytes = 0;

        onProgress?.(0);

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }
            if (value) {
                const copy = new Uint8Array(value.byteLength);
                copy.set(value);
                chunks.push(copy);
                loadedBytes += value.byteLength;
                const percent = Math.min(
                    100,
                    Math.round((loadedBytes / totalBytes) * 100),
                );
                onProgress?.(percent);
            }
        }

        const mimeType =
            response.headers.get('content-type') ?? 'application/octet-stream';
        blob = new Blob(chunks, { type: mimeType });
        onProgress?.(100);
    } else {
        onProgress?.(25);
        blob = await response.blob();
        onProgress?.(100);
    }

    triggerBlobDownload(blob, resolvedName);
}

/**
 * Reactive action state machine (`idle` -> `loading` -> `success` | `error` -> `idle`)
 * with automatic timer reset and disposal cleanup.
 */
export function useActionState(options: UseActionStateOptions = {}) {
    const successDurationMs = options.successDurationMs ?? 1800;
    const errorDurationMs = options.errorDurationMs ?? 2400;

    const state = ref<ActionState>('idle');
    const error = ref<string | null>(null);
    const progress = ref<number | null>(null);

    let resetTimer: ReturnType<typeof setTimeout> | null = null;

    const clearResetTimer = (): void => {
        if (resetTimer !== null) {
            clearTimeout(resetTimer);
            resetTimer = null;
        }
    };

    const scheduleReset = (delayMs: number): void => {
        clearResetTimer();
        if (delayMs > 0) {
            resetTimer = setTimeout(() => {
                state.value = 'idle';
                progress.value = null;
                resetTimer = null;
            }, delayMs);
        }
    };

    const setLoading = (initialProgress: number | null = null): void => {
        clearResetTimer();
        error.value = null;
        progress.value = initialProgress;
        state.value = 'loading';
    };

    const setSuccess = (durationMs = successDurationMs): void => {
        error.value = null;
        if (progress.value !== null) {
            progress.value = 100;
        }
        state.value = 'success';
        scheduleReset(durationMs);
    };

    const setError = (
        message: string | null = null,
        durationMs = errorDurationMs,
    ): void => {
        error.value = message;
        progress.value = null;
        state.value = 'error';
        scheduleReset(durationMs);
    };

    const reset = (): void => {
        clearResetTimer();
        error.value = null;
        progress.value = null;
        state.value = 'idle';
    };

    async function run<T>(
        fn: () => Promise<T>,
        runOptions: RunActionOptions<T> = {},
    ): Promise<T | undefined> {
        setLoading();
        try {
            const result = await fn();
            setSuccess();
            runOptions.onSuccess?.(result);
            return result;
        } catch (err) {
            const msg =
                err instanceof Error
                    ? err.message
                    : 'Action failed unexpectedly.';
            setError(msg);
            runOptions.onError?.(err);
            if (runOptions.throwOnError) {
                throw err;
            }
            return undefined;
        }
    }

    async function download(
        url: string,
        filename?: string,
        requestInit?: RequestInit,
    ): Promise<boolean> {
        setLoading(null);
        try {
            await downloadWithProgress(
                url,
                filename,
                (pct) => {
                    progress.value = pct;
                },
                requestInit,
            );
            setSuccess();
            return true;
        } catch (err) {
            const msg = err instanceof Error ? err.message : 'Download failed.';
            setError(msg);
            return false;
        }
    }

    if (getCurrentScope()) {
        onScopeDispose(() => {
            clearResetTimer();
        });
    }

    const isIdle = computed(() => state.value === 'idle');
    const isLoading = computed(() => state.value === 'loading');
    const isSuccess = computed(() => state.value === 'success');
    const isError = computed(() => state.value === 'error');

    return {
        state,
        error,
        progress,
        isIdle,
        isLoading,
        isSuccess,
        isError,
        setLoading,
        setSuccess,
        setError,
        reset,
        run,
        download,
    };
}
