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

export interface Market {
    code: MarketCode;
    name: string;
    country: string;
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
