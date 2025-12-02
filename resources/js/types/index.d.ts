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
