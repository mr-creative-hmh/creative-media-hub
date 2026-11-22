/**
 * Centralized formatting utilities for files, data transfer, and time durations.
 */

/**
 * Format bytes into human-readable string (e.g. "12.3 KB", "450.5 MB", "1.75 GB").
 *
 * @param bytes The numeric byte value to format
 * @param decimals Number of decimal points (default: 1 for KB/MB, 2 for GB+)
 */
export function formatBytes(bytes: number, decimals?: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const k = 1024;
    const i = Math.min(
        Math.floor(Math.log(bytes) / Math.log(k)),
        units.length - 1,
    );

    if (i === 0) {
        return `${Math.round(bytes)} B`;
    }

    const precision = decimals !== undefined ? decimals : i >= 3 ? 2 : 1;

    return `${(bytes / Math.pow(k, i)).toFixed(precision)} ${units[i]}`;
}

/**
 * Format bytes per second into human-readable transfer speed (e.g. "850.5 KB/s", "12.4 MB/s").
 *
 * @param bytesPerSec The transfer speed in bytes per second
 */
export function formatSpeed(bytesPerSec: number): string {
    if (!Number.isFinite(bytesPerSec) || bytesPerSec <= 0) {
        return '0.0 MB/s';
    }

    if (bytesPerSec < 1024 * 1024) {
        return `${(bytesPerSec / 1024).toFixed(1)} KB/s`;
    }

    return `${(bytesPerSec / (1024 * 1024)).toFixed(1)} MB/s`;
}

/**
 * Format a duration in seconds to a human-readable string (e.g. "2h 15m", "4m 30s", "15s").
 *
 * @param seconds Duration in seconds
 */
export function formatDuration(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '0s';
    }

    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    if (hrs > 0) {
        return `${hrs}h ${mins}m`;
    }
    if (mins > 0) {
        return `${mins}m ${secs}s`;
    }
    return `${secs}s`;
}
