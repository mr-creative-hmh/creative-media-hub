<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    origin: string;
    size?: 'sm' | 'md' | 'lg';
}>(), {
    size: 'md',
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'w-4 h-2.5';
        case 'lg':
            return 'w-6 h-4';
        case 'md':
        default:
            return 'w-5 h-3.5';
    }
});
</script>

<template>
    <span
        class="inline-flex items-center justify-center shrink-0 rounded-[3px] overflow-hidden border border-white/25 shadow-sm select-none"
        :class="sizeClasses"
    >
        <!-- 1. All Regions / Global -->
        <svg
            v-if="!origin || origin === 'all'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#0c4a6e" />
            <circle cx="12" cy="8" r="6" fill="#0284c7" />
            <path d="M6 8h12M12 2a9 9 0 0 0 0 12M12 2a9 9 0 0 1 0 12" stroke="#bae6fd" stroke-width="0.8" fill="none" />
            <circle cx="12" cy="8" r="6" stroke="#38bdf8" stroke-width="0.7" fill="none" />
        </svg>

        <!-- 2. Arabic Cinema / Saudi Arabia -->
        <svg
            v-else-if="origin === 'arabic'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#065f46" />
            <!-- Arabic Calligraphy Impression -->
            <path
                d="M5 5.5h14M7 4.2v2.5M10 3.8v3M13 4.2v2.5M16 4.5v2M8.5 5c0 1 1.5 1.2 3 1.2s3.5-.2 3.5-1.2"
                stroke="#ecfdf5"
                stroke-width="0.9"
                stroke-linecap="round"
                fill="none"
            />
            <path
                d="M6 7.2c1.5.5 3 .7 5 .7s4-.2 6-.8"
                stroke="#ecfdf5"
                stroke-width="0.7"
                stroke-linecap="round"
                fill="none"
            />
            <!-- Traditional Scimitar / Sword -->
            <path
                d="M6.5 11h11M16 11l-1.2-1.2M7.5 10v2"
                stroke="#ffffff"
                stroke-width="0.8"
                stroke-linecap="round"
            />
        </svg>

        <!-- 3. Bollywood & Indian / India -->
        <svg
            v-else-if="origin === 'indian'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <!-- Saffron -->
            <rect width="24" height="5.33" y="0" fill="#ff9933" />
            <!-- White -->
            <rect width="24" height="5.33" y="5.33" fill="#ffffff" />
            <!-- Green -->
            <rect width="24" height="5.33" y="10.67" fill="#138808" />
            <!-- Ashoka Chakra -->
            <circle cx="12" cy="8" r="2.1" fill="none" stroke="#000080" stroke-width="0.5" />
            <circle cx="12" cy="8" r="0.6" fill="#000080" />
            <g stroke="#000080" stroke-width="0.3">
                <line x1="12" y1="5.9" x2="12" y2="10.1" />
                <line x1="9.9" y1="8" x2="14.1" y2="8" />
                <line x1="10.5" y1="6.5" x2="13.5" y2="9.5" />
                <line x1="10.5" y1="9.5" x2="13.5" y2="6.5" />
            </g>
        </svg>

        <!-- 4. Anime & Asian / Japan -->
        <svg
            v-else-if="origin === 'asian'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#ffffff" />
            <circle cx="12" cy="8" r="4.2" fill="#bc002d" />
        </svg>

        <!-- 5. Turkish Cinema / Turkey -->
        <svg
            v-else-if="origin === 'turkish'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#e30a17" />
            <!-- White Crescent -->
            <circle cx="10" cy="8" r="4" fill="#ffffff" />
            <circle cx="11.2" cy="8" r="3.2" fill="#e30a17" />
            <!-- 5-pointed star -->
            <polygon
                points="14.8,6.2 15.5,7.7 17.1,7.7 15.8,8.7 16.3,10.2 15,9.2 13.7,10.2 14.2,8.7 12.9,7.7 14.5,7.7"
                fill="#ffffff"
            />
        </svg>

        <!-- 6. Hollywood & Western / USA -->
        <svg
            v-else-if="origin === 'hollywood'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <!-- 13 Stripes -->
            <rect width="24" height="16" fill="#ffffff" />
            <rect width="24" height="1.23" y="0" fill="#b22234" />
            <rect width="24" height="1.23" y="2.46" fill="#b22234" />
            <rect width="24" height="1.23" y="4.92" fill="#b22234" />
            <rect width="24" height="1.23" y="7.38" fill="#b22234" />
            <rect width="24" height="1.23" y="9.85" fill="#b22234" />
            <rect width="24" height="1.23" y="12.31" fill="#b22234" />
            <rect width="24" height="1.23" y="14.77" fill="#b22234" />
            <!-- Blue Canton -->
            <rect width="10" height="8.62" fill="#3c3b6e" />
            <!-- Stars Pattern -->
            <g fill="#ffffff">
                <circle cx="2" cy="1.8" r="0.5" />
                <circle cx="5" cy="1.8" r="0.5" />
                <circle cx="8" cy="1.8" r="0.5" />
                <circle cx="3.5" cy="3.5" r="0.5" />
                <circle cx="6.5" cy="3.5" r="0.5" />
                <circle cx="2" cy="5.2" r="0.5" />
                <circle cx="5" cy="5.2" r="0.5" />
                <circle cx="8" cy="5.2" r="0.5" />
                <circle cx="3.5" cy="6.9" r="0.5" />
                <circle cx="6.5" cy="6.9" r="0.5" />
            </g>
        </svg>

        <!-- 7. European Cinema / European Union -->
        <svg
            v-else-if="origin === 'european'"
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#003399" />
            <!-- Circle of 12 gold stars -->
            <g fill="#ffcc00">
                <circle cx="12" cy="3.5" r="0.6" />
                <circle cx="14.2" cy="4.1" r="0.6" />
                <circle cx="15.9" cy="5.8" r="0.6" />
                <circle cx="16.5" cy="8" r="0.6" />
                <circle cx="15.9" cy="10.2" r="0.6" />
                <circle cx="14.2" cy="11.9" r="0.6" />
                <circle cx="12" cy="12.5" r="0.6" />
                <circle cx="9.8" cy="11.9" r="0.6" />
                <circle cx="8.1" cy="10.2" r="0.6" />
                <circle cx="7.5" cy="8" r="0.6" />
                <circle cx="8.1" cy="5.8" r="0.6" />
                <circle cx="9.8" cy="4.1" r="0.6" />
            </g>
        </svg>

        <!-- Fallback Globe -->
        <svg
            v-else
            viewBox="0 0 24 16"
            class="w-full h-full"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect width="24" height="16" fill="#1e293b" />
            <circle cx="12" cy="8" r="5" stroke="#94a3b8" stroke-width="1" fill="none" />
        </svg>
    </span>
</template>
