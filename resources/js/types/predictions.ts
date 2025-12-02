export interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export interface MarketPreview {
  id: string;
  code: string;
  name: string;
}

export interface SectorPreview {
  id: string;
  name: string;
}

export interface CountryPreview {
  id: string;
  name: string;
  code: string;
}

export interface AssetPreview {
  id: string;
  symbol: string;
  name: string;
  market?: MarketPreview;
  sector?: SectorPreview | null;
}

export interface PriceData {
  last: number;
  pcp: string;
  high?: number;
  low?: number;
  previousClose?: number;
  volume?: string;
}

export interface PredictionData {
  predictedPrice: number;
  confidence: number;
  horizon: number;
  horizonLabel: string;
  expectedGainPercent?: number;
  timestamp?: string;
}

export interface HorizonOption {
  value: number;
  label: string;
}

export const HORIZONS: HorizonOption[] = [
  { value: 1440, label: '1D' },
  { value: 10080, label: '1W' },
  { value: 43200, label: '1M' },
  { value: 129600, label: '3M' },
];
