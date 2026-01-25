// News types

export type NewsSentiment = 'positive' | 'negative' | 'neutral';

export type NewsAction = 'buy' | 'sell' | 'watch';

export interface AssetNewSummary {
    id: string;
    symbol: string;
    name: string;
    name_ar?: string;
}

export interface MarketSummary {
    id: string;
    code: string;
}

export interface SectorSummary {
    id: string;
    name: string;
}

export interface CountrySummary {
    id: string;
    name: string;
    code: string;
}

export interface AssetNew {
    id: string;
    title: string;
    slug: string;
    description: string;
    content: string;
    image_url: string | null;
    score: number | null;
    sentiment: NewsSentiment | null;
    action: NewsAction | null;
    category: string | null;
    date: string | null;
    created_at: string;
    risks: string[];
    opportunities: string[];
    affected_sectors: string[];
    meta_tags: string[];
    meta_description: string | null;
    asset?: AssetNewSummary | null;
    market?: MarketSummary | null;
    sector?: SectorSummary | null;
    country?: CountrySummary | null;
    bookmarked?: boolean;
    user_rating?: boolean | null;
    ratings_summary?: {
        helpful_count: number;
        not_helpful_count: number;
    };
    comments_count?: number;
}

export interface AssetNewListItem {
    id: string;
    title: string;
    slug: string;
    description: string;
    image_url: string | null;
    score: number | null;
    sentiment: NewsSentiment | null;
    action: NewsAction | null;
    category: string | null;
    date: string | null;
    created_at: string;
    asset?: AssetNewSummary | null;
    market?: MarketSummary | null;
    bookmarked?: boolean;
}

export interface NewsFilters {
    search?: string;
    sentiment?: NewsSentiment;
    category?: string;
    action?: NewsAction;
    market_id?: string;
    sector_id?: string;
    country_id?: string;
    asset_id?: string;
    score_min?: number;
    score_max?: number;
    date_from?: string;
    date_to?: string;
}

export interface NewsComment {
    id: string;
    user: {
        id: string;
        name: string;
    };
    content: string;
    created_at: string;
}

export interface NewsFilterOptions {
    markets: { id: string; code: string; name: string }[];
    sectors: { id: string; name: string }[];
    countries: { id: string; name: string; code: string }[];
    categories: string[];
}

export type NewsViewMode = 'grid' | 'list';

export interface NewsPaginationState {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
}
