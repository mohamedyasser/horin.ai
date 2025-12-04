import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

// Market types
export type MarketCode = 'EGX' | 'TASI' | 'ADX' | 'DFM' | 'KW' | 'QA' | 'BH';

export type MarketStatus = 'open' | 'closed';

export interface Market {
    id: number;
    code: MarketCode;
    name: string;
    full_name: string;
    country: string;
    timezone: string;
    trading_hours: {
        open: string;
        close: string;
    };
    status: MarketStatus;
    prediction_count: number;
}

// Prediction types
export type PredictionHorizon = '1D' | '1W' | '1M' | '3M';

export interface Prediction {
    id: number;
    symbol: string;
    name: string;
    market: MarketCode;
    sector: string;
    last_price: number;
    predicted_price: number;
    gain_percent: number;
    horizon: PredictionHorizon;
    confidence: number;
    currency: string;
    created_at: string;
    updated_at: string;
}

export interface PredictionFilters {
    market?: MarketCode;
    sector?: string;
    horizon?: PredictionHorizon;
    min_price?: number;
    max_price?: number;
    search?: string;
    sort_by?: 'gain' | 'confidence' | 'newest';
}

// Asset types
export type AssetType = 'stock' | 'etf';

export interface Asset {
    id: number;
    symbol: string;
    name: string;
    market: MarketCode;
    sector: string;
    country: string;
    asset_type: AssetType;
    currency: string;
    last_updated: string;
}

export interface AssetPrice {
    last: number;
    last_close: number;
    change_percent: number;
    high: number;
    low: number;
    volume?: number;
    updated_at: string;
}

export interface AssetPrediction {
    id: number;
    predicted_price: number;
    horizon: PredictionHorizon;
    confidence: number;
    expected_gain_percent: number;
    upper_bound?: number;
    lower_bound?: number;
    timestamp: string;
}

export interface TechnicalIndicators {
    rsi?: number;
    macd?: number;
    ema?: number;
    sma?: number;
    volume_ma?: number;
    atr?: number;
    stochastic?: number;
}

export interface AssetDetail extends Asset {
    price: AssetPrice;
    predictions: AssetPrediction[];
    indicators?: TechnicalIndicators;
    prediction_history?: AssetPrediction[];
}

// Sector types
export type SectorCode =
    | 'banking'
    | 'energy'
    | 'telecom'
    | 'realEstate'
    | 'healthcare'
    | 'industrial'
    | 'materials'
    | 'consumer'
    | 'technology'
    | 'utilities'
    | 'financialServices'
    | 'insurance'
    | 'retail'
    | 'transportation'
    | 'construction';

export interface SectorMarketDistribution {
    market: MarketCode;
    count: number;
}

export interface Sector {
    id: number;
    code: SectorCode;
    name: string;
    description?: string;
    asset_count: number;
    prediction_count: number;
    markets: SectorMarketDistribution[];
    avg_gain_percent?: number;
    updated_at: string;
}

// Pagination types (matches PaginationHelper output)
export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
}

// Home page types
export interface HomeStats {
    totalMarkets: number;
    totalAssets: number;
    totalPredictions: number;
    totalSectors: number;
}

export interface CountryPreview {
    id: string;
    name: string;
    code: string;
}

export interface MarketPreview {
    id: string;
    name: string;
    code: string;
    country: CountryPreview;
    isOpen: boolean;
    assetCount: number;
    predictionCount: number;
}

export interface SectorPreview {
    id: string;
    name: string;
    assetCount: number;
    predictionCount: number;
}

export interface AssetPreview {
    id: string;
    symbol: string;
    name: string;
    market?: { code: string };
}

export interface FeaturedPrediction {
    id: string;
    asset: AssetPreview;
    currentPrice: number | null;
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    expectedGainPercent: number;
    timestamp: string | null;
    targetTimestamp: string | null;
}

export interface TopMover {
    id: string;
    symbol: string;
    name: string;
    market: { code: string };
    currentPrice: number;
    priceChangePercent: number;
}

export interface RecentPrediction {
    id: string;
    asset: AssetPreview;
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string | null;
    targetTimestamp: string | null;
}

// Market detail types
export interface MarketDetail {
    id: string;
    name: string;
    code: string;
    country: CountryPreview | null;
    isOpen: boolean;
    openAt: string | null;
    closeAt: string | null;
    tvLink: string | null;
    assetCount: number;
    predictionCount: number;
}

export interface AssetListItem {
    id: string;
    symbol: string;
    name: string;
    sector?: { id: string; name: string } | null;
    market?: { id: string; code: string; name: string } | null;
    latestPrice?: { last: number; pcp: string } | null;
    latestPrediction?: {
        predictedPrice: number;
        confidence: number;
        horizon: number;
        horizonLabel: string;
    } | null;
}

// Sector detail types
export interface MarketsBreakdown {
    marketId: string;
    marketCode: string;
    marketName: string;
    count: number;
}

export interface SectorDetail {
    id: string;
    name: string;
    description: string | null;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: MarketsBreakdown[];
}

// Predictions page types
export interface HorizonOption {
    value: number;
    label: string;
}

export interface PredictionFilterOptions {
    markets: { id: string; code: string; name: string }[];
    sectors: { id: string; name: string }[];
    horizons: HorizonOption[];
}

export interface PredictionFiltersState {
    marketId: string | null;
    sectorId: string | null;
    horizon: number | null;
    minConfidence: number;
}

export interface PredictionSortState {
    field: 'confidence' | 'timestamp';
    direction: 'asc' | 'desc';
}

export interface PredictionListItem {
    id: string;
    asset: {
        id: string;
        symbol: string;
        name: string;
        market: { id: string; code: string; name: string } | null;
        sector: { id: string; name: string } | null;
        currentPrice: number | null;
    };
    predictedPrice: number;
    expectedGainPercent: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string;
    targetTimestamp: string | null;
}

// Search page types
export interface SearchResult {
    id: string;
    symbol: string;
    name: string;
    market: { id: string; code: string; name: string } | null;
    sector: { id: string; name: string } | null;
    latestPrice: { last: number; pcp: string } | null;
}

// Asset detail types
export interface AssetDetailData {
    id: string;
    symbol: string;
    name: string;
    type: string;
    currency: string;
    market: { id: string; code: string; name: string } | null;
    sector: { id: string; name: string } | null;
    country: CountryPreview | null;
}

export interface AssetPriceData {
    last: number;
    changePercent: string;
    high: number;
    low: number;
    previousClose: number;
    volume: string;
    updatedAt: string;
}

export interface AssetPredictionData {
    horizon: number;
    horizonLabel: string;
    predictedPrice: number;
    confidence: number;
    expectedGainPercent: number;
    timestamp: string | null;
    targetTimestamp: string | null;
}

export interface AssetIndicatorsData {
    rsi: number | null;
    macd: {
        line: number | null;
        signal: number | null;
        histogram: number | null;
    };
    ema: number | null;
    sma: number | null;
    atr: number | null;
    bollingerBands: {
        upper: number | null;
        middle: number | null;
        lower: number | null;
    };
    updatedAt: string;
}

export interface PriceHistoryPoint {
    timestamp: number;
    close: number;
    high: number;
    low: number;
    open: number;
    volume: number;
}

export interface PredictionHistoryItem {
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string | null;
    targetTimestamp: string | null;
}
