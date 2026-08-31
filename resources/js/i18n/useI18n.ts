import { ref, computed } from 'vue';
import en from './en.json';
import ar from './ar.json';

const currentLocale = ref<string>(localStorage.getItem('app_locale') || 'en');

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
            localStorage.setItem('app_locale', newLocale);
            document.documentElement.dir = newLocale === 'ar' ? 'rtl' : 'ltr';
            document.documentElement.lang = newLocale;
        }
    };

    // Apply document direction on first load
    if (typeof document !== 'undefined') {
        document.documentElement.dir = currentLocale.value === 'ar' ? 'rtl' : 'ltr';
        document.documentElement.lang = currentLocale.value;
    }

    const t = (key: string, params: Record<string, string | number> = {}): string => {
        const keys = key.split('.');
        let val: any = translations[currentLocale.value] || translations['en'];

        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                // Fallback to English
                let fallback: any = translations['en'];
                for (const fk of keys) {
                    if (fallback && typeof fallback === 'object' && fk in fallback) {
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
            result = result.replace(new RegExp(`{${pKey}}`, 'g'), String(pVal));
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
