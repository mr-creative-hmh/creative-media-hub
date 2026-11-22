import { ref, readonly } from 'vue';
import { toast } from 'vue-sonner';
import { useLocale } from '@/composables/useLocale';

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

function resolveText(text?: LocalizedText): string {
    if (!text) return '';
    if (typeof text === 'string') return text;
    try {
        const { isRtl } = useLocale();
        return isRtl.value
            ? text.ar || text.en || ''
            : text.en || text.ar || '';
    } catch {
        const isRtl =
            typeof document !== 'undefined'
                ? document.documentElement.dir === 'rtl'
                : false;
        return isRtl ? text.ar || text.en || '' : text.en || text.ar || '';
    }
}

export function useToast() {
    const dismiss = (id: string) => {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index !== -1) {
            const item = toasts.value[index];
            if (item.timer) {
                clearTimeout(item.timer);
            }
            toasts.value.splice(index, 1);
        }
        toast.dismiss(id);
    };

    const show = (
        message: LocalizedText,
        type: ToastType = 'info',
        options?: { title?: LocalizedText; duration?: number },
    ): string => {
        const id =
            'toast_' +
            Math.random().toString(36).substring(2, 9) +
            '_' +
            Date.now();
        const duration = options?.duration ?? (type === 'error' ? 6000 : 4200);

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

        if (toasts.value.length >= 4) {
            const oldest = toasts.value[0];
            if (oldest.timer) clearTimeout(oldest.timer);
            toasts.value.shift();
        }

        toasts.value.push(item);

        // Forward to vue-sonner HUD
        const resolvedMsg = resolveText(message);
        const resolvedTitle = resolveText(options?.title);

        const sonnerOptions = {
            id,
            description: resolvedTitle ? resolvedMsg : undefined,
            duration,
        };
        const titleText = resolvedTitle || resolvedMsg;

        switch (type) {
            case 'success':
                toast.success(titleText, sonnerOptions);
                break;
            case 'error':
                toast.error(titleText, sonnerOptions);
                break;
            case 'warning':
                toast.warning(titleText, sonnerOptions);
                break;
            case 'info':
            default:
                toast.info(titleText, sonnerOptions);
                break;
        }

        return id;
    };

    const success = (
        message: LocalizedText,
        title?: LocalizedText,
        duration?: number,
    ) => {
        return show(message, 'success', { title, duration });
    };

    const error = (
        message: LocalizedText,
        title?: LocalizedText,
        duration?: number,
    ) => {
        return show(message, 'error', { title, duration: duration ?? 6000 });
    };

    const info = (
        message: LocalizedText,
        title?: LocalizedText,
        duration?: number,
    ) => {
        return show(message, 'info', { title, duration });
    };

    const warning = (
        message: LocalizedText,
        title?: LocalizedText,
        duration?: number,
    ) => {
        return show(message, 'warning', { title, duration });
    };

    const clear = () => {
        toasts.value.forEach((t) => {
            if (t.timer) clearTimeout(t.timer);
        });
        toasts.value = [];
        toast.dismiss();
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
