import { ref, readonly } from 'vue';

export type ToastType = 'success' | 'error' | 'info' | 'warning';
export type LocalizedText = string | { en: string; ar: string };

export interface ToastItem {
    id: string;
    message: LocalizedText;
    title?: LocalizedText;
    type: ToastType;
    duration: number;
    timer?: ReturnType<typeof setTimeout>;
}

const toasts = ref<ToastItem[]>([]);

export function useToast() {
    const dismiss = (id: string) => {
        const index = toasts.value.findIndex(t => t.id === id);
        if (index !== -1) {
            const item = toasts.value[index];
            if (item.timer) {
                clearTimeout(item.timer);
            }
            toasts.value.splice(index, 1);
        }
    };

    const show = (
        message: LocalizedText,
        type: ToastType = 'info',
        options?: { title?: LocalizedText; duration?: number }
    ) => {
        const id = 'toast_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now();
        const duration = options?.duration ?? (type === 'error' ? 6000 : 4000);

        const timer = setTimeout(() => {
            dismiss(id);
        }, duration);

        const item: ToastItem = {
            id,
            message,
            title: options?.title,
            type,
            duration,
            timer,
        };

        // Keep maximum 4 visible toasts at once
        if (toasts.value.length >= 4) {
            const oldest = toasts.value[0];
            if (oldest.timer) clearTimeout(oldest.timer);
            toasts.value.shift();
        }

        toasts.value.push(item);
        return id;
    };

    const success = (message: LocalizedText, title?: LocalizedText, duration?: number) => {
        return show(message, 'success', { title, duration });
    };

    const error = (message: LocalizedText, title?: LocalizedText, duration?: number) => {
        return show(message, 'error', { title, duration: duration ?? 6000 });
    };

    const info = (message: LocalizedText, title?: LocalizedText, duration?: number) => {
        return show(message, 'info', { title, duration });
    };

    const warning = (message: LocalizedText, title?: LocalizedText, duration?: number) => {
        return show(message, 'warning', { title, duration });
    };

    const clear = () => {
        toasts.value.forEach(t => {
            if (t.timer) clearTimeout(t.timer);
        });
        toasts.value = [];
    };

    return {
        toasts: readonly(toasts),
        show,
        success,
        error,
        info,
        warning,
        dismiss,
        clear,
    };
}
