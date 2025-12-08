<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    TrendingUp,
    TrendingDown,
    Minus,
    ChevronDown,
    ChevronUp,
    AlertTriangle,
    Activity,
    BarChart3,
    Zap,
} from 'lucide-vue-next';
import { useRecommendationFormatters } from '@/composables/useRecommendationFormatters';
import type { Recommendation, Signal, PatternDetection, Anomaly } from '@/types';

const { t } = useI18n();
const {
    getActionColor,
    getActionIcon,
    formatTimeAgo,
    isStale,
    getStrengthColor,
} = useRecommendationFormatters();

interface Props {
    recommendation: Recommendation | null;
    signals?: Signal[];
    patterns?: PatternDetection | null;
    anomalies?: Anomaly[];
}

const props = withDefaults(defineProps<Props>(), {
    signals: () => [],
    anomalies: () => [],
});

const isExpanded = ref(false);
const signalsExpanded = ref(true);
const patternsExpanded = ref(true);
const anomaliesExpanded = ref(true);

const hasAnalysisData = computed(() => {
    return (props.signals?.length ?? 0) > 0 ||
           (props.patterns?.pattern_count ?? 0) > 0 ||
           (props.anomalies?.length ?? 0) > 0;
});

const detectedPatternNames = computed(() => {
    if (!props.patterns) return [];
    const names: string[] = [];
    if (props.patterns.has_head_shoulder) names.push('head_shoulder');
    if (props.patterns.has_multiple_tops_bottoms) names.push('multiple_tops_bottoms');
    if (props.patterns.has_triangle) names.push('triangle');
    if (props.patterns.has_wedge) names.push('wedge');
    if (props.patterns.has_channel) names.push('channel');
    if (props.patterns.has_double_top_bottom) names.push('double_top_bottom');
    if (props.patterns.has_trendline) names.push('trendline');
    if (props.patterns.has_support_resistance) names.push('support_resistance');
    if (props.patterns.has_pivots) names.push('pivots');
    return names;
});

const iconComponent = computed(() => {
    const icon = getActionIcon(props.recommendation?.recommendation ?? '');
    if (icon === 'TrendingUp') return TrendingUp;
    if (icon === 'TrendingDown') return TrendingDown;
    return Minus;
});

const recommendationIsStale = computed(() => {
    return isStale(props.recommendation?.created_at ?? null);
});
</script>

<template>
    <Card v-if="recommendation" class="border-2" :class="recommendationIsStale ? 'border-yellow-500/50' : 'border-border'">
        <CardHeader class="pb-3">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span
                        :class="getActionColor(recommendation.recommendation)"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-bold"
                    >
                        <component :is="iconComponent" class="size-4" />
                        {{ t(`recommendations.actions.${recommendation.recommendation}`) }}
                    </span>
                    <span v-if="recommendationIsStale" class="inline-flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400">
                        <AlertTriangle class="size-3" />
                        {{ t('recommendations.updated') }}: {{ formatTimeAgo(recommendation.created_at) }}
                    </span>
                </div>
                <div class="text-end">
                    <p class="text-sm text-muted-foreground">{{ t('recommendations.score') }}</p>
                    <p class="text-xl font-bold">{{ recommendation.score.toFixed(1) }}</p>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Summary Row -->
            <div class="flex items-center justify-between text-sm">
                <span class="text-muted-foreground">
                    {{ t('recommendations.updated') }}: {{ formatTimeAgo(recommendation.created_at) }}
                </span>
            </div>

            <!-- Expand/Collapse Button -->
            <Button
                v-if="hasAnalysisData"
                variant="outline"
                size="sm"
                class="w-full"
                @click="isExpanded = !isExpanded"
            >
                <ChevronDown v-if="!isExpanded" class="me-1 size-4" />
                <ChevronUp v-else class="me-1 size-4" />
                {{ isExpanded ? t('recommendations.hideAnalysis') : t('recommendations.viewAnalysis') }}
            </Button>

            <!-- Expanded Analysis -->
            <div v-if="isExpanded && hasAnalysisData" class="space-y-4 pt-4 border-t border-border">
                <!-- Active Signals -->
                <div v-if="signals && signals.length > 0">
                    <button
                        class="flex w-full items-center justify-between py-2 text-sm font-medium"
                        @click="signalsExpanded = !signalsExpanded"
                    >
                        <span class="flex items-center gap-2">
                            <Zap class="size-4 text-yellow-500" />
                            {{ t('recommendations.activeSignals') }} ({{ signals.length }})
                        </span>
                        <ChevronDown v-if="!signalsExpanded" class="size-4" />
                        <ChevronUp v-else class="size-4" />
                    </button>
                    <div v-if="signalsExpanded" class="mt-2 space-y-2 ps-6">
                        <div
                            v-for="signal in signals"
                            :key="signal.id"
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-sm"
                        >
                            <span>{{ signal.indicator }} - {{ signal.signal_type }}</span>
                            <span :class="getStrengthColor(signal.strength * 100)" class="font-medium">
                                {{ (signal.strength * 100).toFixed(0) }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Detected Patterns -->
                <div v-if="patterns && patterns.pattern_count > 0">
                    <button
                        class="flex w-full items-center justify-between py-2 text-sm font-medium"
                        @click="patternsExpanded = !patternsExpanded"
                    >
                        <span class="flex items-center gap-2">
                            <BarChart3 class="size-4 text-blue-500" />
                            {{ t('recommendations.detectedPatterns') }} ({{ patterns.pattern_count }})
                        </span>
                        <ChevronDown v-if="!patternsExpanded" class="size-4" />
                        <ChevronUp v-else class="size-4" />
                    </button>
                    <div v-if="patternsExpanded" class="mt-2 space-y-2 ps-6">
                        <div
                            v-for="pattern in detectedPatternNames"
                            :key="pattern"
                            class="rounded-lg bg-muted/50 px-3 py-2 text-sm"
                        >
                            {{ t(`recommendations.patterns.${pattern}`) }}
                        </div>
                    </div>
                </div>

                <!-- Anomalies -->
                <div v-if="anomalies && anomalies.length > 0">
                    <button
                        class="flex w-full items-center justify-between py-2 text-sm font-medium"
                        @click="anomaliesExpanded = !anomaliesExpanded"
                    >
                        <span class="flex items-center gap-2">
                            <Activity class="size-4 text-orange-500" />
                            {{ t('recommendations.anomalies') }} ({{ anomalies.length }})
                        </span>
                        <ChevronDown v-if="!anomaliesExpanded" class="size-4" />
                        <ChevronUp v-else class="size-4" />
                    </button>
                    <div v-if="anomaliesExpanded" class="mt-2 space-y-2 ps-6">
                        <div
                            v-for="anomaly in anomalies"
                            :key="anomaly.id"
                            class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-sm"
                        >
                            <span>{{ anomaly.anomaly_type }}</span>
                            <span class="text-muted-foreground">
                                {{ (anomaly.confidence_score * 100).toFixed(0) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Empty State -->
    <Card v-else class="border-dashed">
        <CardContent class="flex flex-col items-center justify-center py-8 text-center">
            <Activity class="size-12 text-muted-foreground/50" />
            <p class="mt-4 font-medium text-muted-foreground">
                {{ t('recommendations.noData') }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('recommendations.noDataDescription') }}
            </p>
        </CardContent>
    </Card>
</template>
