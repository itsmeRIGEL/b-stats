import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Tailwind default breakpoints
const BREAKPOINTS = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    '2xl': 1536,
} as const;

export type BreakpointKey = keyof typeof BREAKPOINTS;

/**
 * Reactive breakpoint detection.
 * Returns reactive booleans for each named breakpoint and the current width.
 */
export function useBreakpoint() {
    const width = ref<number>(typeof window !== 'undefined' ? window.innerWidth : 1024);

    const onResize = () => {
        width.value = window.innerWidth;
    };

    onMounted(() => {
        onResize();
        window.addEventListener('resize', onResize, { passive: true });
    });

    onBeforeUnmount(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('resize', onResize);
        }
    });

    const isMobile = computed(() => width.value < BREAKPOINTS.sm); // < 640
    const isTablet = computed(() => width.value >= BREAKPOINTS.sm && width.value < BREAKPOINTS.lg); // 640-1023
    const isDesktop = computed(() => width.value >= BREAKPOINTS.lg); // >= 1024

    // Generic helpers
    const isAtLeast = (bp: BreakpointKey) => computed(() => width.value >= BREAKPOINTS[bp]);
    const isBelow = (bp: BreakpointKey) => computed(() => width.value < BREAKPOINTS[bp]);

    return {
        width,
        isMobile,
        isTablet,
        isDesktop,
        isAtLeast,
        isBelow,
    };
}
