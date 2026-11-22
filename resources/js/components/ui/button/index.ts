import type { VariantProps } from 'class-variance-authority';
import { cva } from 'class-variance-authority';

export { default as Button } from './Button.vue';

export const buttonVariants = cva(
    "inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-150 outline-none select-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default:
                    'border border-white/15 bg-primary text-primary-foreground shadow-xs shadow-primary/25 hover:bg-primary/90 hover:shadow-sm hover:shadow-primary/30 active:brightness-95 dark:border-white/10',
                gradient:
                    'border border-white/20 bg-gradient-to-br from-primary via-primary to-primary/80 text-primary-foreground shadow-sm shadow-primary/30 hover:shadow-md hover:shadow-primary/35 hover:brightness-105 active:brightness-95 dark:border-white/15 dark:shadow-[0_6px_18px_-4px_color-mix(in_oklab,var(--primary)_45%,transparent),inset_0_1px_0_rgba(255,255,255,0.2)]',
                destructive:
                    'border border-white/15 bg-destructive text-white shadow-xs shadow-destructive/25 hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:bg-destructive/85 dark:focus-visible:ring-destructive/40',
                outline:
                    'border border-border/80 bg-background/80 shadow-2xs backdrop-blur-sm hover:border-border hover:bg-accent hover:text-accent-foreground dark:border-input dark:bg-input/30 dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.06)] dark:hover:bg-input/50',
                secondary:
                    'bg-secondary text-secondary-foreground shadow-2xs hover:bg-secondary/85',
                ghost: 'hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4 py-2 has-[>svg]:px-3.5',
                sm: 'h-8 gap-1.5 rounded-lg px-3 text-[11px] has-[>svg]:px-2.5',
                lg: 'h-10 rounded-xl px-6 text-sm font-black has-[>svg]:px-4',
                icon: 'size-10 rounded-xl',
                'icon-sm': 'size-8 rounded-lg',
                'icon-lg': 'size-12 rounded-2xl',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

export type ButtonVariants = VariantProps<typeof buttonVariants>;
