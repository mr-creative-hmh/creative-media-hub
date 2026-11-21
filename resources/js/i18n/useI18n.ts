import { ref, computed } from 'vue';
import en from './en.json';
import ar from './ar.json';

const getInitialLocale = (): string => {
    if (typeof window === 'undefined') {
        return 'en';
    }
    try {
        return window.localStorage.getItem('app_locale') || 'en';
    } catch {
        return 'en';
    }
};

const applyDocumentLocale = (localeCode: string): void => {
    if (typeof document === 'undefined') {
        return;
    }
    document.documentElement.dir = localeCode === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = localeCode;
};

const currentLocale = ref<string>(getInitialLocale());
applyDocumentLocale(currentLocale.value);

const translations: Record<string, any> = {
    en,
    ar,
};

export function useI18n() {
    const locale = computed(() => currentLocale.value);
    const isRTL = computed(() => currentLocale.value === 'ar');

    const setLocale = (newLocale: string) => {
        if (newLocale === 'ar' || newLocale === 'en') {
            currentLocale.value = newLocale;
            if (typeof window !== 'undefined') {
                try {
                    window.localStorage.setItem('app_locale', newLocale);
                } catch {}
            }
            applyDocumentLocale(newLocale);
        }
    };

    const t = (
        key: string,
        params: Record<string, string | number> = {},
    ): string => {
        const keys = key.split('.');
        let val: any = translations[currentLocale.value] || translations['en'];

        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                // Fallback to English
                let fallback: any = translations['en'];
                for (const fk of keys) {
                    if (
                        fallback &&
                        typeof fallback === 'object' &&
                        fk in fallback
                    ) {
                        fallback = fallback[fk];
                    } else {
                        return key;
                    }
                }
                val = fallback;
                break;
            }
        }

        if (typeof val !== 'string') {
            return key;
        }

        let result = val;
        for (const [pKey, pVal] of Object.entries(params)) {
            result = result
                .replace(new RegExp(`{${pKey}}`, 'g'), String(pVal))
                .replace(new RegExp(`:${pKey}`, 'g'), String(pVal));
        }

        return result;
    };

    return {
        locale,
        isRTL,
        setLocale,
        t,
    };
}
