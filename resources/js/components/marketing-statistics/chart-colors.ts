import { useIsDark } from '@/hooks/use-is-dark';

/**
 * Same validated categorical slots as `resources/css/app.css`'s
 * `--viz-series-*` tokens and the CEO dashboard's Traffic Data widget
 * (scripts/validate_palette.js) — assign by series/category identity in
 * this fixed order, never cycle arbitrarily.
 */
const PALETTE = {
    light: {
        series: ['#2a78d6', '#1baf7a', '#eda100', '#008300', '#4a3aa7', '#e34948', '#e87ba4', '#eb6834'],
        gridline: '#e1e0d9',
        muted: '#898781',
    },
    dark: {
        series: ['#3987e5', '#199e70', '#c98500', '#008300', '#9085e9', '#e66767', '#d55181', '#d95926'],
        gridline: '#2c2c2a',
        muted: '#898781',
    },
} as const;

export function useChartColors() {
    const isDark = useIsDark();
    return isDark ? PALETTE.dark : PALETTE.light;
}

export function compactNumber(value: number): string {
    return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}
