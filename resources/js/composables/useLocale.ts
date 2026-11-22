import { computed } from 'vue';
import { useI18n } from '@/i18n/useI18n';

export type SupportedLocale = 'ar' | 'en';
export type TextDirection = 'rtl' | 'ltr';

export type AvailableLocaleOption = {
    code: SupportedLocale;
    label: string;
    nativeLabel: string;
    dir: TextDirection;
};

export function useLocale() {
    const { locale, isRTL, setLocale, t } = useI18n();

    const currentSupportedLocale = computed<SupportedLocale>(() =>
        locale.value === 'ar' ? 'ar' : 'en',
    );

    const isRtl = computed<boolean>(() => isRTL.value);

    const direction = computed<TextDirection>(() =>
        isRtl.value ? 'rtl' : 'ltr',
    );

    const availableLocales = computed<AvailableLocaleOption[]>(() => [
        {
            code: 'en',
            label: 'English',
            nativeLabel: 'English',
            dir: 'ltr',
        },
        {
            code: 'ar',
            label: 'Arabic',
            nativeLabel: 'العربية',
            dir: 'rtl',
        },
    ]);

    const switchLocale = (targetLocale?: SupportedLocale): void => {
        const nextLocale: SupportedLocale =
            targetLocale ?? (locale.value === 'ar' ? 'en' : 'ar');
        setLocale(nextLocale);
    };

    return {
        locale: currentSupportedLocale,
        isRtl,
        direction,
        availableLocales,
        t,
        switchLocale,
        setLocale,
    };
}
