export type AlertType =
    | 'price'
    | 'prediction'
    | 'signal'
    | 'anomaly'
    | 'pattern'
    | 'recommendation';

export type AlertTriggerType =
    | 'target_price'
    | 'breakout'
    | 'zone'
    | 'gap'
    | '52week'
    | 'daily_change'
    | 'entry_return'
    | 'prediction'
    | 'signal'
    | 'anomaly'
    | 'pattern'
    | 'recommendation'
    | 'compound_intelligence';

export type AlertStatus =
    | 'active'
    | 'triggered'
    | 'paused'
    | 'expired'
    | 'chained'
    | 'deleted';
export type AlertPriority = 'critical' | 'high' | 'medium' | 'low';
export type AlertScope =
    | 'single_asset'
    | 'watchlist'
    | 'portfolio'
    | 'sector'
    | 'market';
export type AlertDirection =
    | 'above'
    | 'below'
    | 'both'
    | 'cross_up'
    | 'cross_down';
export type ConditionLogic = 'single' | 'and' | 'or';

export interface AlertParameters {
    target_price?: number;
    level?: number;
    direction?: AlertDirection;
    zone_low?: number;
    zone_high?: number;
    gap_threshold_percent?: number;
    threshold_percent?: number;
    type?: string;
    entry_price?: number;
    tolerance_percent?: number;
    indicators?: string[];
    signal_types?: string[];
    min_strength?: number;
    min_confidence?: number;
    patterns?: string[];
    anomaly_types?: string[];
    severity?: string[];
    recommendations?: string[];
    conditions?: AlertCondition[];
    from_reference?: string;
    trigger_on?: string;
    cooldown_hours?: number;
    confirmation?: string;
    consecutive_ticks?: number;
    horizon?: string;
    prediction_type?: string;
    any_or_all?: string;
    pattern_status?: string;
    min_score?: number;
}

export interface AlertCondition {
    type: AlertType;
    [key: string]: unknown;
}

export interface DeliveryConfig {
    channels?: ('telegram' | 'push' | 'email' | 'in_app')[];
    sound_enabled?: boolean;
}

export interface EscalationConfig {
    enabled: boolean;
    levels: EscalationLevel[];
    max_escalations: number;
}

export interface EscalationLevel {
    level: number;
    channel: string;
    delay_minutes: number;
    condition?: string;
}

export interface AlertAsset {
    id: string;
    symbol: string;
    name: string;
    name_ar: string;
    last_price: number;
}

export interface Alert {
    id: string;
    user_id: string;
    asset?: AlertAsset;
    template?: { id: string; name: string };
    type: AlertType;
    trigger_type: AlertTriggerType;
    scope: AlertScope;
    direction?: AlertDirection;
    condition_logic: ConditionLogic;
    parameters: AlertParameters;
    status: AlertStatus;
    priority: AlertPriority;
    is_recurring: boolean;
    cooldown_minutes: number;
    max_triggers?: number;
    triggered_count: number;
    delivery_config?: DeliveryConfig;
    escalation_config?: EscalationConfig;
    chain_from_id?: string | null;
    snoozed_until?: string;
    is_snoozed: boolean;
    last_triggered_at?: string;
    expires_at?: string;
    market_hours_only: boolean;
    created_at: string;
    updated_at: string;
}

export interface AlertHistory {
    id: string;
    alert_id: string;
    alert?: {
        type: AlertType;
        trigger_type: AlertTriggerType;
        parameters: AlertParameters;
    };
    asset?: AlertAsset;
    triggered_at: string;
    trigger_value: number;
    trigger_context: Record<string, unknown>;
    notification_sent: boolean;
    acknowledged_at?: string;
    escalation_level: number;
}

export interface AlertNotification {
    id: string;
    type: string;
    channel: string;
    priority: AlertPriority;
    title: string;
    body: string;
    data: {
        type: string;
        alert_id: string;
        asset_id: string;
        history_id: string;
        screen: string;
        params: Record<string, unknown>;
    };
    status: string;
    read_at?: string;
    created_at: string;
}

export interface AlertPreferences {
    user_id: string;
    default_channels: string[];
    quiet_hours_start?: string;
    quiet_hours_end?: string;
    timezone: string;
    max_alerts_per_hour: number;
    max_alerts_per_day: number;
    digest_enabled: boolean;
    digest_time: string;
    smart_defaults_enabled: boolean;
}

export interface BacktestResult {
    id: string;
    alert_id: string;
    lookback_days: number;
    trigger_count: number;
    triggers: BacktestTrigger[];
    avg_return_1d?: number;
    avg_return_1w?: number;
    avg_return_1m?: number;
    win_rate?: number;
    completed_at: string;
}

export interface BacktestTrigger {
    date: string;
    trigger_price: number;
    performance: {
        '1d': number;
        '1w': number;
        '1m': number;
    };
}

export interface AlertStats {
    active: number;
    triggered_today: number;
    total: number;
}

export interface AlertHistoryStats {
    today: number;
    this_week: number;
    unacknowledged: number;
}
