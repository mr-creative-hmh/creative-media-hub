import { ref, computed, onMounted, type Ref, type ComputedRef } from 'vue';
import { router } from '@inertiajs/vue3';

export type AccentColor =
    | 'cyan'
    | 'red'
    | 'amber'
    | 'emerald'
    | 'violet'
    | 'rose'
    | 'orange'
    | 'blue';

export interface AccentPalette {
    key: AccentColor;
    nameKey: string;
    descKey: string;
    color: string;
    secondaryColor: string;
    glowColor: string;
}

export const ACCENT_PALETTES: AccentPalette[] = [
    {
        key: 'cyan',
        nameKey: 'accent_theme.palettes.cyan_name',
        descKey: 'accent_theme.palettes.cyan_desc',
        color: '#06B6D4',
        secondaryColor: '#3B82F6',
        glowColor: 'rgba(6, 182, 212, 0.45)',
    },
    {
        key: 'red',
        nameKey: 'accent_theme.palettes.red_name',
        descKey: 'accent_theme.palettes.red_desc',
        color: '#EF4444',
        secondaryColor: '#E11D48',
        glowColor: 'rgba(239, 68, 68, 0.45)',
    },
    {
        key: 'amber',
        nameKey: 'accent_theme.palettes.amber_name',
        descKey: 'accent_theme.palettes.amber_desc',
        color: '#F59E0B',
        secondaryColor: '#EA580C',
        glowColor: 'rgba(245, 158, 11, 0.45)',
    },
    {
        key: 'emerald',
        nameKey: 'accent_theme.palettes.emerald_name',
        descKey: 'accent_theme.palettes.emerald_desc',
        color: '#10B981',
        secondaryColor: '#0D9488',
        glowColor: 'rgba(16, 185, 129, 0.45)',
    },
    {
        key: 'violet',
        nameKey: 'accent_theme.palettes.violet_name',
        descKey: 'accent_theme.palettes.violet_desc',
        color: '#8B5CF6',
        secondaryColor: '#7C3AED',
        glowColor: 'rgba(139, 92, 246, 0.45)',
    },
    {
        key: 'rose',
        nameKey: 'accent_theme.palettes.rose_name',
        descKey: 'accent_theme.palettes.rose_desc',
        color: '#F43F5E',
        secondaryColor: '#DB2777',
        glowColor: 'rgba(244, 63, 94, 0.45)',
    },
    {
        key: 'orange',
        nameKey: 'accent_theme.palettes.orange_name',
        descKey: 'accent_theme.palettes.orange_desc',
        color: '#F97316',
        secondaryColor: '#D97706',
        glowColor: 'rgba(249, 115, 22, 0.45)',
    },
    {
        key: 'blue',
        nameKey: 'accent_theme.palettes.blue_name',
        descKey: 'accent_theme.palettes.blue_desc',
        color: '#3B82F6',
        secondaryColor: '#6366F1',
        glowColor: 'rgba(59, 130, 246, 0.45)',
    },
];

const STORAGE_KEY = 'cmh_accent_color';

// Global shared state
const currentAccent = ref<AccentColor>('cyan');
let isInitialized = false;

const setDocumentAccent = (accent: AccentColor) => {
    if (typeof document === 'undefined') return;
    document.documentElement.setAttribute('data-accent', accent);
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') return;
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${encodeURIComponent(value)};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredAccent = (): AccentColor => {
    if (typeof window === 'undefined') return 'cyan';
    try {
        const stored = localStorage.getItem(STORAGE_KEY) as AccentColor | null;
        if (stored && ACCENT_PALETTES.some((p) => p.key === stored)) {
            return stored;
        }
    } catch (e) {}
    return 'cyan';
};

export function initializeThemeAccent(): void {
    if (typeof window === 'undefined' || isInitialized) return;

    const saved = getStoredAccent();
    currentAccent.value = saved;
    setDocumentAccent(saved);

    // Cross-tab synchronization
    window.addEventListener('storage', (event) => {
        if (event.key === STORAGE_KEY && event.newValue) {
            const newAccent = event.newValue as AccentColor;
            if (ACCENT_PALETTES.some((p) => p.key === newAccent)) {
                currentAccent.value = newAccent;
                setDocumentAccent(newAccent);
            }
        }
    });

    // Inertia page navigation persistence
    try {
        router.on('navigate', () => {
            setDocumentAccent(currentAccent.value);
        });
    } catch (e) {}

    isInitialized = true;
}

export function useThemeAccent() {
    if (typeof window !== 'undefined' && !isInitialized) {
        initializeThemeAccent();
    }

    onMounted(() => {
        if (!isInitialized) {
            initializeThemeAccent();
        } else {
            setDocumentAccent(currentAccent.value);
        }
    });

    const activePalette: ComputedRef<AccentPalette> = computed(() => {
        return (
            ACCENT_PALETTES.find((p) => p.key === currentAccent.value) ||
            ACCENT_PALETTES[0]
        );
    });

    const setAccent = (accent: AccentColor) => {
        currentAccent.value = accent;
        setDocumentAccent(accent);
        try {
            localStorage.setItem(STORAGE_KEY, accent);
            setCookie(STORAGE_KEY, accent);
        } catch (e) {}
    };

    return {
        currentAccent: currentAccent as Ref<AccentColor>,
        activePalette,
        setAccent,
        ACCENT_PALETTES,
    };
}
