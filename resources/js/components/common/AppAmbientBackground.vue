<script setup lang="ts">
// Pure Cinema GPU-composited ambient backdrop tinted by active var(--primary)
</script>

<template>
    <div
        class="pointer-events-none fixed inset-0 z-0 overflow-hidden select-none"
        aria-hidden="true"
    >
        <!-- Soft Top-Edge Light Sheen Tinted by var(--primary) -->
        <div
            class="absolute inset-x-0 top-0 h-96 opacity-90 dark:opacity-100"
            style="
                background: radial-gradient(
                    85% 68% at 50% -10%,
                    color-mix(in oklab, var(--primary) 18%, transparent),
                    transparent 72%
                );
            "
        />
        <div
            class="absolute inset-x-0 top-0 h-px opacity-60"
            style="
                background: linear-gradient(
                    90deg,
                    transparent 0%,
                    color-mix(in oklab, var(--primary) 50%, transparent) 50%,
                    transparent 100%
                );
            "
        />

        <!-- Precision Blueprint Coordinate Dot & Crosshair Grid with Calm Center Falloff -->
        <div
            class="text-foreground absolute inset-0 opacity-[0.14] dark:opacity-[0.12]"
            style="
                mask-image: radial-gradient(
                    ellipse 84% 76% at 50% 42%,
                    rgba(0, 0, 0, 0.2) 0%,
                    rgba(0, 0, 0, 0.65) 54%,
                    rgba(0, 0, 0, 1) 100%
                );
                -webkit-mask-image: radial-gradient(
                    ellipse 84% 76% at 50% 42%,
                    rgba(0, 0, 0, 0.2) 0%,
                    rgba(0, 0, 0, 0.65) 54%,
                    rgba(0, 0, 0, 1) 100%
                );
            "
        >
            <!-- 24px Coordinate Dot Matrix -->
            <div
                class="absolute inset-0"
                style="
                    background-image: radial-gradient(
                        color-mix(in oklab, var(--primary) 34%, currentColor)
                            1.1px,
                        transparent 1.1px
                    );
                    background-size: 24px 24px;
                "
            />
            <!-- 96px Precision Blueprint Crosshair Anchors -->
            <svg
                class="h-full w-full"
                xmlns="http://www.w3.org/2000/svg"
                width="100%"
                height="100%"
            >
                <defs>
                    <pattern
                        id="cmh-ambient-crosshair-grid"
                        width="96"
                        height="96"
                        patternUnits="userSpaceOnUse"
                    >
                        <path
                            d="M 96 0 L 0 0 0 96"
                            fill="none"
                            stroke="currentColor"
                            stroke-opacity="0.18"
                            stroke-width="0.6"
                        />
                        <path
                            d="M -4 0 H 4 M 0 -4 V 4"
                            fill="none"
                            stroke="var(--primary)"
                            stroke-opacity="0.6"
                            stroke-width="1"
                        />
                    </pattern>
                </defs>
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#cmh-ambient-crosshair-grid)"
                />
            </svg>
        </div>

        <!-- GPU-Composited Drifting Accent Aurora Orbs (Primary + Cinema Ambient Depth) -->
        <div
            class="cf-aurora-orb-a bg-primary/14 dark:bg-primary/20 absolute -start-36 -top-36 size-[480px] rounded-full blur-[130px] md:size-[640px]"
        />
        <div
            class="cf-aurora-orb-b bg-primary/10 dark:bg-primary/14 absolute -end-36 top-1/4 size-[440px] rounded-full blur-[140px] md:size-[600px]"
        />
        <div
            class="cf-aurora-orb-c bg-primary/10 absolute start-1/4 -bottom-36 size-[400px] rounded-full blur-[150px] md:size-[540px] dark:bg-slate-500/10"
        />
    </div>
</template>
