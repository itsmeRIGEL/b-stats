<script setup lang="ts">
import { Cloud, CloudDrizzle, CloudFog, CloudLightning, CloudRain, CloudSnow, CloudSun, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    weather: Record<string, any>;
    date?: string;
}>();

const targetDate = computed(() => props.date || new Date().toISOString().split('T')[0]);
const dayWeather = computed(() => props.weather[targetDate.value]);
const currentPayload = computed(() => props.weather.__current ?? null);
const hourlyPayload = computed(() => (Array.isArray(props.weather.__hourly) ? props.weather.__hourly.slice(0, 4) : []));

const weatherMeta = (code: number) => {
    if (code === 0) return { icon: Sun, label: 'Clear Sky', color: 'text-amber-500', bg: 'bg-amber-50 dark:bg-amber-900/30' };
    if (code <= 3) return { icon: CloudSun, label: 'Partly Cloudy', color: 'text-blue-400', bg: 'bg-blue-50 dark:bg-blue-900/30' };
    if (code <= 48) return { icon: CloudFog, label: 'Foggy', color: 'text-slate-400', bg: 'bg-slate-50 dark:bg-slate-800' };
    if (code <= 55) return { icon: CloudDrizzle, label: 'Drizzle', color: 'text-sky-400', bg: 'bg-sky-50 dark:bg-sky-900/30' };
    if (code <= 65) return { icon: CloudRain, label: 'Rainy', color: 'text-blue-500', bg: 'bg-blue-50 dark:bg-blue-900/30' };
    if (code <= 77) return { icon: CloudSnow, label: 'Snowy', color: 'text-sky-300', bg: 'bg-sky-50 dark:bg-sky-900/30' };
    if (code <= 82) return { icon: CloudRain, label: 'Showers', color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/30' };
    if (code <= 99) return { icon: CloudLightning, label: 'Thunderstorm', color: 'text-violet-500', bg: 'bg-violet-50 dark:bg-violet-900/30' };
    return { icon: Cloud, label: 'Cloudy', color: 'text-slate-400', bg: 'bg-slate-50 dark:bg-slate-800' };
};

const current = computed(() => {
    const code = currentPayload.value?.code ?? dayWeather.value?.code;
    if (code === undefined || code === null) return null;
    return weatherMeta(code);
});

const formatHour = (iso: string) => {
    if (!iso) return '--';
    const dt = new Date(iso);
    return dt.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
};
</script>

<template>
    <div v-if="current" class="flex items-center gap-3 bg-transparent p-2">
        <div :class="['rounded-lg p-2', current.bg]">
            <component :is="current.icon" :class="['h-5 w-5', current.color]" />
        </div>
        <div class="min-w-0">
            <p class="mb-1 text-sm font-medium leading-none text-gray-700 dark:text-slate-400">Local Weather</p>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900 dark:text-white"
                    >{{ currentPayload?.temperature ?? dayWeather?.temp_max ?? '--' }}°C</span
                >
                <span class="text-sm text-gray-600 dark:text-slate-400">{{ current.label }}</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                Feels {{ currentPayload?.feels_like ?? dayWeather?.temp_max ?? '--' }}° • H{{ dayWeather?.temp_max ?? '--' }} L{{
                    dayWeather?.temp_min ?? '--'
                }}
            </div>
            <div v-if="hourlyPayload.length" class="mt-2 flex flex-wrap items-center gap-1.5">
                <span
                    v-for="h in hourlyPayload"
                    :key="h.time"
                    class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ formatHour(h.time) }} {{ h.temperature }}°
                </span>
            </div>
        </div>
    </div>
    <div v-else class="flex items-center gap-3 bg-transparent p-2">
        <div class="rounded-lg bg-gray-100 p-2 dark:bg-slate-800">
            <Cloud class="h-5 w-5 text-gray-400 dark:text-slate-500" />
        </div>
        <div>
            <p class="mb-1 text-sm font-medium leading-none text-gray-700 dark:text-slate-400">Local Weather</p>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900 dark:text-white">--°C</span>
                <span class="text-sm text-gray-600 dark:text-slate-400">No data</span>
            </div>
        </div>
    </div>
</template>
