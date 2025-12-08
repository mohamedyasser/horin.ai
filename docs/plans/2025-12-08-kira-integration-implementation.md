# Kira Services Integration - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Integrate Kira trading intelligence services (recommendations, signals, patterns, anomalies) into the Horin frontend.

**Architecture:** Add database views for latest data, create Eloquent models, update controllers with deferred props, and build Vue components for recommendations display with expandable analysis.

**Tech Stack:** Laravel 12, Inertia v2, Vue 3, TypeScript, Tailwind v4, PostgreSQL views

---

## Task 1: Create Database Migration for Views

**Files:**
- Create: `database/migrations/2025_12_08_000001_create_recommendation_views.php`

**Step 1: Create the migration file**

```bash
cd /Users/mohamedyasser/Desktop/Work/horin/frontend/.worktrees/kira-integration
php artisan make:migration create_recommendation_views --no-interaction
```

**Step 2: Write the migration content**

Edit the created migration file with this content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Latest recommendation per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_recommendations AS
            SELECT DISTINCT ON (pid)
                id,
                pid,
                score,
                recommendation,
                created_at
            FROM instant_recommendations
            ORDER BY pid, created_at DESC
        ");

        // Active signals per asset (last 30 minutes)
        DB::statement("
            CREATE OR REPLACE VIEW latest_detected_signals AS
            SELECT
                id,
                pid,
                timestamp,
                indicator,
                signal_type,
                value,
                strength,
                created_at
            FROM instant_detected_signals
            WHERE created_at >= NOW() - INTERVAL '30 minutes'
            ORDER BY pid, strength DESC
        ");

        // Latest pattern detection per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_pattern_detections AS
            SELECT DISTINCT ON (pid)
                pid,
                timestamp,
                patterns,
                has_head_shoulder,
                has_multiple_tops_bottoms,
                has_triangle,
                has_wedge,
                has_channel,
                has_double_top_bottom,
                has_trendline,
                has_support_resistance,
                has_pivots,
                pattern_count,
                created_at
            FROM instant_pattern_detections
            ORDER BY pid, timestamp DESC
        ");

        // Active anomalies (last 30 minutes)
        DB::statement("
            CREATE OR REPLACE VIEW latest_anomalies AS
            SELECT
                id,
                symbol,
                anomaly_type,
                confidence_score,
                detected_at,
                window,
                price,
                volume,
                extra
            FROM instant_anomalies
            WHERE detected_at >= NOW() - INTERVAL '30 minutes'
            ORDER BY symbol, detected_at DESC
        ");

        // Latest signal classification per asset
        DB::statement("
            CREATE OR REPLACE VIEW latest_signal_classifications AS
            SELECT DISTINCT ON (pid)
                id,
                pid,
                signal_id,
                classification,
                confidence,
                metadata,
                created_at
            FROM instant_signal_classifications
            ORDER BY pid, created_at DESC
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS latest_recommendations');
        DB::statement('DROP VIEW IF EXISTS latest_detected_signals');
        DB::statement('DROP VIEW IF EXISTS latest_pattern_detections');
        DB::statement('DROP VIEW IF EXISTS latest_anomalies');
        DB::statement('DROP VIEW IF EXISTS latest_signal_classifications');
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/
git commit -m "feat: add database views for recommendation data"
```

---

## Task 2: Create LatestRecommendation Model

**Files:**
- Create: `app/Models/LatestRecommendation.php`

**Step 1: Create model using artisan**

```bash
php artisan make:model LatestRecommendation --no-interaction
```

**Step 2: Write the model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestRecommendation extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_recommendations';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'pid';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this recommendation.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Check if the recommendation is stale (older than 30 minutes).
     */
    public function isStale(): bool
    {
        return $this->created_at?->diffInMinutes(now()) > 30;
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/LatestRecommendation.php
git commit -m "feat: add LatestRecommendation model"
```

---

## Task 3: Create LatestDetectedSignal Model

**Files:**
- Create: `app/Models/LatestDetectedSignal.php`

**Step 1: Create model using artisan**

```bash
php artisan make:model LatestDetectedSignal --no-interaction
```

**Step 2: Write the model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestDetectedSignal extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_detected_signals';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timestamp' => 'integer',
            'value' => 'array',
            'strength' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this signal.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/LatestDetectedSignal.php
git commit -m "feat: add LatestDetectedSignal model"
```

---

## Task 4: Create LatestPatternDetection Model

**Files:**
- Create: `app/Models/LatestPatternDetection.php`

**Step 1: Create model using artisan**

```bash
php artisan make:model LatestPatternDetection --no-interaction
```

**Step 2: Write the model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestPatternDetection extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_pattern_detections';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'pid';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timestamp' => 'integer',
            'patterns' => 'array',
            'has_head_shoulder' => 'boolean',
            'has_multiple_tops_bottoms' => 'boolean',
            'has_triangle' => 'boolean',
            'has_wedge' => 'boolean',
            'has_channel' => 'boolean',
            'has_double_top_bottom' => 'boolean',
            'has_trendline' => 'boolean',
            'has_support_resistance' => 'boolean',
            'has_pivots' => 'boolean',
            'pattern_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this pattern detection.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Get a list of detected pattern names.
     *
     * @return array<string>
     */
    public function getDetectedPatternNames(): array
    {
        $patterns = [];

        if ($this->has_head_shoulder) {
            $patterns[] = 'head_shoulder';
        }
        if ($this->has_multiple_tops_bottoms) {
            $patterns[] = 'multiple_tops_bottoms';
        }
        if ($this->has_triangle) {
            $patterns[] = 'triangle';
        }
        if ($this->has_wedge) {
            $patterns[] = 'wedge';
        }
        if ($this->has_channel) {
            $patterns[] = 'channel';
        }
        if ($this->has_double_top_bottom) {
            $patterns[] = 'double_top_bottom';
        }
        if ($this->has_trendline) {
            $patterns[] = 'trendline';
        }
        if ($this->has_support_resistance) {
            $patterns[] = 'support_resistance';
        }
        if ($this->has_pivots) {
            $patterns[] = 'pivots';
        }

        return $patterns;
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/LatestPatternDetection.php
git commit -m "feat: add LatestPatternDetection model"
```

---

## Task 5: Create LatestAnomaly Model

**Files:**
- Create: `app/Models/LatestAnomaly.php`

**Step 1: Create model using artisan**

```bash
php artisan make:model LatestAnomaly --no-interaction
```

**Step 2: Write the model content**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestAnomaly extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_anomalies';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'detected_at' => 'datetime',
            'price' => 'float',
            'volume' => 'integer',
            'extra' => 'array',
        ];
    }

    /**
     * Get the asset by symbol.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'symbol', 'symbol');
    }
}
```

**Step 3: Commit**

```bash
git add app/Models/LatestAnomaly.php
git commit -m "feat: add LatestAnomaly model"
```

---

## Task 6: Add Relationships to Asset Model

**Files:**
- Modify: `app/Models/Asset.php`

**Step 1: Add the new relationship methods to Asset model**

Add after the existing `latestIndicator()` method (around line 171):

```php
    /**
     * Get the latest recommendation for this asset.
     */
    public function latestRecommendation(): HasOne
    {
        return $this->hasOne(LatestRecommendation::class, 'pid', 'inv_id');
    }

    /**
     * Get the active signals for this asset (last 30 minutes).
     */
    public function latestSignals(): HasMany
    {
        return $this->hasMany(LatestDetectedSignal::class, 'pid', 'inv_id');
    }

    /**
     * Get the latest pattern detection for this asset.
     */
    public function latestPatternDetection(): HasOne
    {
        return $this->hasOne(LatestPatternDetection::class, 'pid', 'inv_id');
    }

    /**
     * Get the active anomalies for this asset (last 30 minutes).
     */
    public function latestAnomalies(): HasMany
    {
        return $this->hasMany(LatestAnomaly::class, 'symbol', 'symbol');
    }
```

**Step 2: Commit**

```bash
git add app/Models/Asset.php
git commit -m "feat: add recommendation relationships to Asset model"
```

---

## Task 7: Create useRecommendationFormatters Composable

**Files:**
- Create: `resources/js/composables/useRecommendationFormatters.ts`

**Step 1: Create the composable file**

```typescript
// resources/js/composables/useRecommendationFormatters.ts

/**
 * Shared formatting utilities for recommendation data display.
 */
export function useRecommendationFormatters() {
    /**
     * Get Tailwind color classes based on recommendation action
     */
    const getActionColor = (action: string): string => {
        switch (action?.toUpperCase()) {
            case 'STRONG_BUY':
                return 'bg-green-600 text-white';
            case 'BUY':
                return 'bg-green-500 text-white';
            case 'HOLD':
                return 'bg-gray-500 text-white';
            case 'SELL':
                return 'bg-red-500 text-white';
            case 'STRONG_SELL':
                return 'bg-red-600 text-white';
            default:
                return 'bg-gray-400 text-white';
        }
    };

    /**
     * Get Tailwind text color classes based on recommendation action
     */
    const getActionTextColor = (action: string): string => {
        switch (action?.toUpperCase()) {
            case 'STRONG_BUY':
            case 'BUY':
                return 'text-green-600 dark:text-green-400';
            case 'SELL':
            case 'STRONG_SELL':
                return 'text-red-600 dark:text-red-400';
            default:
                return 'text-gray-600 dark:text-gray-400';
        }
    };

    /**
     * Get icon name based on recommendation action
     */
    const getActionIcon = (action: string): 'TrendingUp' | 'TrendingDown' | 'Minus' => {
        const upper = action?.toUpperCase();
        if (upper?.includes('BUY')) return 'TrendingUp';
        if (upper?.includes('SELL')) return 'TrendingDown';
        return 'Minus';
    };

    /**
     * Get risk/reward label from ratio
     */
    const getRiskRewardLabel = (ratio: number | null): string => {
        if (ratio === null) return '-';
        if (ratio >= 3) return 'excellent';
        if (ratio >= 2) return 'good';
        if (ratio >= 1) return 'fair';
        return 'poor';
    };

    /**
     * Get Tailwind color classes for risk/reward ratio
     */
    const getRiskRewardColor = (ratio: number | null): string => {
        if (ratio === null) return 'text-gray-500';
        if (ratio >= 3) return 'text-green-600 dark:text-green-400';
        if (ratio >= 2) return 'text-green-500 dark:text-green-400';
        if (ratio >= 1) return 'text-yellow-500 dark:text-yellow-400';
        return 'text-red-500 dark:text-red-400';
    };

    /**
     * Format time ago string
     */
    const formatTimeAgo = (date: Date | string | null): string => {
        if (!date) return '-';
        const d = typeof date === 'string' ? new Date(date) : date;
        const minutes = Math.floor((Date.now() - d.getTime()) / 60000);

        if (minutes < 1) return 'just_now';
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h`;
        return `${Math.floor(hours / 24)}d`;
    };

    /**
     * Check if recommendation is stale (older than threshold)
     */
    const isStale = (date: Date | string | null, thresholdMinutes: number = 30): boolean => {
        if (!date) return true;
        const d = typeof date === 'string' ? new Date(date) : date;
        return (Date.now() - d.getTime()) > thresholdMinutes * 60 * 1000;
    };

    /**
     * Get strength color based on percentage
     */
    const getStrengthColor = (strength: number): string => {
        if (strength >= 80) return 'text-green-600 dark:text-green-400';
        if (strength >= 60) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    };

    return {
        getActionColor,
        getActionTextColor,
        getActionIcon,
        getRiskRewardLabel,
        getRiskRewardColor,
        formatTimeAgo,
        isStale,
        getStrengthColor,
    };
}
```

**Step 2: Commit**

```bash
git add resources/js/composables/useRecommendationFormatters.ts
git commit -m "feat: add useRecommendationFormatters composable"
```

---

## Task 8: Add TypeScript Types

**Files:**
- Modify: `resources/js/types/index.d.ts`

**Step 1: Add new types at the end of the file**

```typescript
// Recommendation Types
export interface Recommendation {
    id: string;
    pid: string;
    score: number;
    recommendation: 'STRONG_BUY' | 'BUY' | 'HOLD' | 'SELL' | 'STRONG_SELL';
    created_at: string;
    asset?: {
        id: string;
        symbol: string;
        name: string;
        market?: { code: string };
        sector?: { name: string };
    };
}

export interface Signal {
    id: string;
    pid: string;
    timestamp: number;
    indicator: string;
    signal_type: string;
    value: Record<string, unknown>;
    strength: number;
    created_at: string;
}

export interface PatternDetection {
    pid: string;
    timestamp: number;
    patterns: string[];
    has_head_shoulder: boolean;
    has_multiple_tops_bottoms: boolean;
    has_triangle: boolean;
    has_wedge: boolean;
    has_channel: boolean;
    has_double_top_bottom: boolean;
    has_trendline: boolean;
    has_support_resistance: boolean;
    has_pivots: boolean;
    pattern_count: number;
    created_at: string;
}

export interface Anomaly {
    id: string;
    symbol: string;
    anomaly_type: string;
    confidence_score: number;
    detected_at: string;
    window: string;
    price: number;
    volume: number;
    extra: Record<string, unknown>;
}
```

**Step 2: Commit**

```bash
git add resources/js/types/index.d.ts
git commit -m "feat: add TypeScript types for recommendations"
```

---

## Task 9: Add i18n Translations (English)

**Files:**
- Modify: `resources/js/i18n/en.json`

**Step 1: Add recommendations translations**

Add this object inside the root JSON object:

```json
    "recommendations": {
        "title": "Recommendations",
        "noData": "No recommendation available",
        "noDataDescription": "Waiting for sufficient market data to generate a recommendation.",
        "viewAnalysis": "View Analysis",
        "hideAnalysis": "Hide Analysis",
        "whyThisRecommendation": "Why This Recommendation",
        "activeSignals": "Active Signals",
        "detectedPatterns": "Detected Patterns",
        "anomalies": "Anomalies",
        "entry": "Entry",
        "stopLoss": "Stop-Loss",
        "target": "Target",
        "riskReward": "Risk/Reward",
        "confidence": "Confidence",
        "updated": "Updated",
        "score": "Score",
        "strength": "Strength",
        "actions": {
            "STRONG_BUY": "Strong Buy",
            "BUY": "Buy",
            "HOLD": "Hold",
            "SELL": "Sell",
            "STRONG_SELL": "Strong Sell"
        },
        "riskRewardLabels": {
            "excellent": "Excellent",
            "good": "Good",
            "fair": "Fair",
            "poor": "Poor"
        },
        "patterns": {
            "head_shoulder": "Head & Shoulders",
            "multiple_tops_bottoms": "Multiple Tops/Bottoms",
            "triangle": "Triangle",
            "wedge": "Wedge",
            "channel": "Channel",
            "double_top_bottom": "Double Top/Bottom",
            "trendline": "Trendline",
            "support_resistance": "Support/Resistance",
            "pivots": "Pivot Points"
        },
        "timeAgo": {
            "just_now": "Just now"
        }
    },
    "sidebar": {
        "topBuySignals": "Top Buy Signals",
        "topSellSignals": "Top Sell Signals",
        "recentRecommendations": "Recent Recommendations"
    }
```

**Step 2: Commit**

```bash
git add resources/js/i18n/en.json
git commit -m "feat: add English i18n translations for recommendations"
```

---

## Task 10: Add i18n Translations (Arabic)

**Files:**
- Modify: `resources/js/i18n/ar.json`

**Step 1: Add recommendations translations**

Add this object inside the root JSON object:

```json
    "recommendations": {
        "title": "التوصيات",
        "noData": "لا توجد توصية متاحة",
        "noDataDescription": "في انتظار بيانات كافية لتوليد توصية.",
        "viewAnalysis": "عرض التحليل",
        "hideAnalysis": "إخفاء التحليل",
        "whyThisRecommendation": "لماذا هذه التوصية",
        "activeSignals": "الإشارات النشطة",
        "detectedPatterns": "الأنماط المكتشفة",
        "anomalies": "الشذوذات",
        "entry": "الدخول",
        "stopLoss": "وقف الخسارة",
        "target": "الهدف",
        "riskReward": "المخاطرة/العائد",
        "confidence": "الثقة",
        "updated": "آخر تحديث",
        "score": "النتيجة",
        "strength": "القوة",
        "actions": {
            "STRONG_BUY": "شراء قوي",
            "BUY": "شراء",
            "HOLD": "احتفاظ",
            "SELL": "بيع",
            "STRONG_SELL": "بيع قوي"
        },
        "riskRewardLabels": {
            "excellent": "ممتاز",
            "good": "جيد",
            "fair": "مقبول",
            "poor": "ضعيف"
        },
        "patterns": {
            "head_shoulder": "الرأس والكتفين",
            "multiple_tops_bottoms": "قمم/قيعان متعددة",
            "triangle": "مثلث",
            "wedge": "إسفين",
            "channel": "قناة",
            "double_top_bottom": "قمة/قاع مزدوج",
            "trendline": "خط الاتجاه",
            "support_resistance": "الدعم/المقاومة",
            "pivots": "نقاط المحور"
        },
        "timeAgo": {
            "just_now": "الآن"
        }
    },
    "sidebar": {
        "topBuySignals": "أقوى إشارات الشراء",
        "topSellSignals": "أقوى إشارات البيع",
        "recentRecommendations": "أحدث التوصيات"
    }
```

**Step 2: Commit**

```bash
git add resources/js/i18n/ar.json
git commit -m "feat: add Arabic i18n translations for recommendations"
```

---

## Task 11: Create RecommendationCard Vue Component

**Files:**
- Create: `resources/js/components/RecommendationCard.vue`

**Step 1: Create the component file**

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
```

**Step 2: Commit**

```bash
git add resources/js/components/RecommendationCard.vue
git commit -m "feat: add RecommendationCard Vue component"
```

---

## Task 12: Update AssetController with Recommendation Data

**Files:**
- Modify: `app/Http/Controllers/AssetController.php`

**Step 1: Add new imports at the top**

After the existing imports:

```php
use App\Models\LatestAnomaly;
use App\Models\LatestDetectedSignal;
use App\Models\LatestPatternDetection;
use App\Models\LatestRecommendation;
```

**Step 2: Add new deferred props to the show method**

In the `show()` method, add these new deferred props after `'predictionHistory'`:

```php
            'recommendation' => Inertia::defer(fn () => $this->getRecommendation($asset)),
            'activeSignals' => Inertia::defer(fn () => $this->getActiveSignals($asset)),
            'detectedPatterns' => Inertia::defer(fn () => $this->getDetectedPatterns($asset)),
            'anomalies' => Inertia::defer(fn () => $this->getAnomalies($asset)),
```

**Step 3: Add the new private methods**

Add these methods at the end of the class:

```php
    private function getRecommendation(Asset $asset): ?array
    {
        $recommendation = LatestRecommendation::where('pid', $asset->inv_id)->first();

        if (! $recommendation) {
            return null;
        }

        return [
            'id' => $recommendation->id,
            'pid' => $recommendation->pid,
            'score' => $recommendation->score,
            'recommendation' => $recommendation->recommendation,
            'created_at' => $recommendation->created_at?->toISOString(),
        ];
    }

    private function getActiveSignals(Asset $asset): array
    {
        return LatestDetectedSignal::where('pid', $asset->inv_id)
            ->orderByDesc('strength')
            ->limit(10)
            ->get()
            ->map(fn ($signal) => [
                'id' => $signal->id,
                'pid' => $signal->pid,
                'timestamp' => $signal->timestamp,
                'indicator' => $signal->indicator,
                'signal_type' => $signal->signal_type,
                'value' => $signal->value,
                'strength' => $signal->strength,
                'created_at' => $signal->created_at?->toISOString(),
            ])
            ->toArray();
    }

    private function getDetectedPatterns(Asset $asset): ?array
    {
        $patterns = LatestPatternDetection::where('pid', $asset->inv_id)->first();

        if (! $patterns) {
            return null;
        }

        return [
            'pid' => $patterns->pid,
            'timestamp' => $patterns->timestamp,
            'patterns' => $patterns->patterns,
            'has_head_shoulder' => $patterns->has_head_shoulder,
            'has_multiple_tops_bottoms' => $patterns->has_multiple_tops_bottoms,
            'has_triangle' => $patterns->has_triangle,
            'has_wedge' => $patterns->has_wedge,
            'has_channel' => $patterns->has_channel,
            'has_double_top_bottom' => $patterns->has_double_top_bottom,
            'has_trendline' => $patterns->has_trendline,
            'has_support_resistance' => $patterns->has_support_resistance,
            'has_pivots' => $patterns->has_pivots,
            'pattern_count' => $patterns->pattern_count,
            'created_at' => $patterns->created_at?->toISOString(),
        ];
    }

    private function getAnomalies(Asset $asset): array
    {
        return LatestAnomaly::where('symbol', $asset->symbol)
            ->orderByDesc('detected_at')
            ->limit(5)
            ->get()
            ->map(fn ($anomaly) => [
                'id' => $anomaly->id,
                'symbol' => $anomaly->symbol,
                'anomaly_type' => $anomaly->anomaly_type,
                'confidence_score' => $anomaly->confidence_score,
                'detected_at' => $anomaly->detected_at?->toISOString(),
                'window' => $anomaly->window,
                'price' => $anomaly->price,
                'volume' => $anomaly->volume,
                'extra' => $anomaly->extra,
            ])
            ->toArray();
    }
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/AssetController.php
git commit -m "feat: add recommendation data to AssetController"
```

---

## Task 13: Update Asset Show Page with RecommendationCard

**Files:**
- Modify: `resources/js/pages/assets/Show.vue`

**Step 1: Add new imports**

After the existing component imports:

```typescript
import RecommendationCard from '@/components/RecommendationCard.vue';
import type {
    // ... existing types
    Recommendation,
    Signal,
    PatternDetection,
    Anomaly,
} from '@/types';
```

**Step 2: Update Props interface**

Add to the Props interface:

```typescript
    recommendation?: Recommendation | null;
    activeSignals?: Signal[];
    detectedPatterns?: PatternDetection | null;
    anomalies?: Anomaly[];
```

**Step 3: Add computed properties**

After existing computed properties:

```typescript
const recommendation = computed(() => props.recommendation ?? null);
const activeSignals = computed(() => props.activeSignals ?? []);
const detectedPatterns = computed(() => props.detectedPatterns ?? null);
const anomalies = computed(() => props.anomalies ?? []);
```

**Step 4: Add RecommendationCard in template**

In the template, add this right after the closing `</section>` of the "Asset Header Section" and before `<!-- Main Content -->`:

```vue
        <!-- Recommendation Section -->
        <section class="border-b border-border/40 bg-background">
            <div class="mx-auto max-w-7xl px-4 py-6">
                <Deferred data="recommendation">
                    <template #fallback>
                        <div class="animate-pulse">
                            <div class="h-32 rounded-lg bg-muted" />
                        </div>
                    </template>
                    <RecommendationCard
                        :recommendation="recommendation"
                        :signals="activeSignals"
                        :patterns="detectedPatterns"
                        :anomalies="anomalies"
                    />
                </Deferred>
            </div>
        </section>
```

**Step 5: Commit**

```bash
git add resources/js/pages/assets/Show.vue
git commit -m "feat: integrate RecommendationCard into asset detail page"
```

---

## Task 14: Create RecommendationsTable Component

**Files:**
- Create: `resources/js/components/RecommendationsTable.vue`

**Step 1: Create the component file**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
import { TrendingUp, TrendingDown, Minus, Search } from 'lucide-vue-next';
import { useRecommendationFormatters } from '@/composables/useRecommendationFormatters';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import type { Recommendation } from '@/types';

const { t, locale } = useI18n();
const { getActionColor, getActionIcon, formatTimeAgo } = useRecommendationFormatters();
const { getConfidenceColor } = usePredictionFormatters();

interface Props {
    recommendations: Recommendation[];
}

const props = defineProps<Props>();

const getIconComponent = (action: string) => {
    const icon = getActionIcon(action);
    if (icon === 'TrendingUp') return TrendingUp;
    if (icon === 'TrendingDown') return TrendingDown;
    return Minus;
};
</script>

<template>
    <div class="rounded-lg border border-border">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                            {{ t('home.table.symbol') }}
                        </th>
                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                            {{ t('home.table.name') }}
                        </th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.title') }}
                        </th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.score') }}
                        </th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.updated') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <ClickableTableRow
                        v-for="rec in recommendations"
                        :key="rec.id"
                        :aria-label="`View details for ${rec.asset?.symbol}`"
                        @click="router.visit(`/${locale}/assets/${rec.asset?.symbol}`)"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ rec.asset?.symbol }}</span>
                                <span v-if="rec.asset?.market" class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                    {{ rec.asset.market.code }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted-foreground">
                            {{ rec.asset?.name }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                :class="getActionColor(rec.recommendation)"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold"
                            >
                                <component :is="getIconComponent(rec.recommendation)" class="size-3" />
                                {{ t(`recommendations.actions.${rec.recommendation}`) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <span class="font-medium">{{ rec.score.toFixed(1) }}</span>
                        </td>
                        <td class="px-4 py-3 text-end text-sm text-muted-foreground">
                            {{ formatTimeAgo(rec.created_at) }}
                        </td>
                    </ClickableTableRow>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div
            v-if="recommendations.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <Search class="size-12 text-muted-foreground/50" />
            <p class="mt-4 text-muted-foreground">
                {{ t('recommendations.noData') }}
            </p>
        </div>
    </div>
</template>
```

**Step 2: Commit**

```bash
git add resources/js/components/RecommendationsTable.vue
git commit -m "feat: add RecommendationsTable Vue component"
```

---

## Task 15: Update HomeController with Recommendations Data

**Files:**
- Modify: `app/Http/Controllers/HomeController.php`

**Step 1: Add new import**

```php
use App\Models\LatestRecommendation;
```

**Step 2: Add new deferred props in __invoke method**

Add after `'recentPredictions'`:

```php
            'featuredRecommendations' => Inertia::defer(fn () => $this->getFeaturedRecommendations($request)),
            'topBuySignals' => Inertia::defer(fn () => $this->getTopBuySignals()),
            'topSellSignals' => Inertia::defer(fn () => $this->getTopSellSignals()),
            'recentRecommendations' => Inertia::defer(fn () => $this->getRecentRecommendations()),
```

**Step 3: Add the new private methods**

```php
    private function getFeaturedRecommendations(Request $request): array
    {
        $marketFilter = $request->input('market');
        $sectorFilter = $request->input('sector');
        $searchFilter = $request->input('search');

        $searchAssetIds = null;
        if ($searchFilter) {
            $searchAssetIds = Asset::search($searchFilter)
                ->take(100)
                ->get()
                ->pluck('inv_id')
                ->toArray();

            if (empty($searchAssetIds)) {
                return ['data' => []];
            }
        }

        $query = LatestRecommendation::with(['asset.market', 'asset.sector']);

        if ($marketFilter) {
            $market = Market::where('code', $marketFilter)->first();
            if ($market) {
                $query->whereHas('asset', fn ($q) => $q->where('market_id', $market->id));
            }
        }

        if ($sectorFilter) {
            $sector = Sector::find($sectorFilter);
            if ($sector) {
                $query->whereHas('asset', fn ($q) => $q->where('sector_id', $sector->id));
            }
        }

        if ($searchAssetIds !== null) {
            $query->whereIn('pid', $searchAssetIds);
        }

        $recommendations = $query->orderByDesc('score')
            ->limit(20)
            ->get()
            ->filter(fn ($r) => $r->asset !== null);

        return [
            'data' => $recommendations->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => $r->score,
                'recommendation' => $r->recommendation,
                'created_at' => $r->created_at?->toISOString(),
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                    'market' => $r->asset->market ? ['code' => $r->asset->market->code] : null,
                    'sector' => $r->asset->sector ? ['name' => $r->asset->sector->name] : null,
                ],
            ])->values()->toArray(),
        ];
    }

    private function getTopBuySignals(): array
    {
        return LatestRecommendation::with('asset')
            ->whereIn('recommendation', ['STRONG_BUY', 'BUY'])
            ->orderByDesc('score')
            ->limit(5)
            ->get()
            ->filter(fn ($r) => $r->asset !== null)
            ->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => $r->score,
                'recommendation' => $r->recommendation,
                'created_at' => $r->created_at?->toISOString(),
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                ],
            ])
            ->values()
            ->toArray();
    }

    private function getTopSellSignals(): array
    {
        return LatestRecommendation::with('asset')
            ->whereIn('recommendation', ['STRONG_SELL', 'SELL'])
            ->orderByDesc('score')
            ->limit(5)
            ->get()
            ->filter(fn ($r) => $r->asset !== null)
            ->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => $r->score,
                'recommendation' => $r->recommendation,
                'created_at' => $r->created_at?->toISOString(),
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                ],
            ])
            ->values()
            ->toArray();
    }

    private function getRecentRecommendations(): array
    {
        return LatestRecommendation::with('asset')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->filter(fn ($r) => $r->asset !== null)
            ->map(fn ($r) => [
                'id' => $r->id,
                'pid' => $r->pid,
                'score' => $r->score,
                'recommendation' => $r->recommendation,
                'created_at' => $r->created_at?->toISOString(),
                'asset' => [
                    'id' => $r->asset->id,
                    'symbol' => $r->asset->symbol,
                    'name' => $r->asset->name,
                ],
            ])
            ->values()
            ->toArray();
    }
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/HomeController.php
git commit -m "feat: add recommendations data to HomeController"
```

---

## Task 16: Update Welcome Page with Tabbed Interface

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

**Step 1: Add new imports**

```typescript
import RecommendationsTable from '@/components/RecommendationsTable.vue';
import type { Recommendation } from '@/types';
```

**Step 2: Update Props interface**

Add to existing Props interface:

```typescript
    featuredRecommendations?: {
        data: Recommendation[];
    };
    topBuySignals?: Recommendation[];
    topSellSignals?: Recommendation[];
    recentRecommendations?: Recommendation[];
```

**Step 3: Add state for active tab**

After existing state:

```typescript
const activeTab = ref<'recommendations' | 'predictions'>('predictions');
```

**Step 4: Add computed properties for new data**

```typescript
const featuredRecommendations = computed(() => props.featuredRecommendations?.data ?? []);
const topBuySignals = computed(() => props.topBuySignals ?? []);
const topSellSignals = computed(() => props.topSellSignals ?? []);
const recentRecommendationsData = computed(() => props.recentRecommendations ?? []);
```

**Step 5: Update template with tabs**

Replace the section starting with `<!-- Controls -->` through the predictions table with:

```vue
                    <!-- Tab Buttons -->
                    <div class="mb-4 flex items-center gap-4 border-b border-border">
                        <button
                            class="relative px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'recommendations' ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
                            @click="activeTab = 'recommendations'"
                        >
                            {{ t('recommendations.title') }}
                            <span v-if="activeTab === 'recommendations'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                        </button>
                        <button
                            class="relative px-4 py-2 text-sm font-medium transition-colors"
                            :class="activeTab === 'predictions' ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
                            @click="activeTab = 'predictions'"
                        >
                            {{ t('home.predictions') }}
                            <span v-if="activeTab === 'predictions'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
                        </button>

                        <div class="ms-auto flex items-center gap-2">
                            <!-- Existing filter and sort buttons -->
```

**Step 6: Add conditional rendering for tabs**

Wrap the existing predictions table in a `v-if="activeTab === 'predictions'"` and add recommendations table:

```vue
                    <!-- Recommendations Table -->
                    <Deferred v-if="activeTab === 'recommendations'" data="featuredRecommendations">
                        <template #fallback>
                            <div class="rounded-lg border border-border">
                                <div class="space-y-4 p-4">
                                    <div v-for="i in 6" :key="i" class="animate-pulse">
                                        <div class="h-16 bg-muted rounded-lg"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <RecommendationsTable :recommendations="featuredRecommendations" />
                    </Deferred>

                    <!-- Predictions Table (existing, wrap with v-if) -->
                    <Deferred v-if="activeTab === 'predictions'" data="featuredPredictions">
                        <!-- existing content -->
                    </Deferred>
```

**Step 7: Update sidebar to be dynamic**

Replace the sidebar section with conditional content based on active tab.

**Step 8: Commit**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "feat: add tabbed interface for recommendations and predictions"
```

---

## Task 17: Run Laravel Pint

**Step 1: Run Pint to fix PHP formatting**

```bash
vendor/bin/pint --dirty
```

**Step 2: Commit if changes**

```bash
git add -A
git commit -m "style: apply Laravel Pint formatting"
```

---

## Task 18: Run npm build

**Step 1: Build frontend assets**

```bash
npm run build
```

**Step 2: Verify no TypeScript errors**

Check the build output for any errors.

---

## Task 19: Final Verification

**Step 1: Start dev server**

```bash
composer run dev
```

**Step 2: Manual testing checklist**

- [ ] Homepage loads with tabs (Recommendations | Predictions)
- [ ] Recommendations tab shows data (or empty state)
- [ ] Predictions tab shows existing data
- [ ] Sidebar updates when switching tabs
- [ ] Asset detail page shows RecommendationCard
- [ ] RecommendationCard expands to show analysis
- [ ] Signals, patterns, anomalies display correctly
- [ ] Empty states display properly
- [ ] Arabic translations work
- [ ] Dark mode works

---

## Summary

This plan creates 15+ tasks to integrate Kira services:

1. **Database**: 1 migration for 5 views
2. **Models**: 4 new Eloquent models + Asset relationships
3. **Frontend**: 3 new Vue components + 1 composable
4. **Controllers**: 2 controller updates
5. **i18n**: English + Arabic translations
6. **Types**: TypeScript interfaces

Estimated total: ~2-3 hours of implementation time.
