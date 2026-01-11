<?php

return [
    // Alert types
    'types' => [
        'price' => 'Price',
        'prediction' => 'AI Prediction',
        'signal' => 'Technical Signal',
        'anomaly' => 'Anomaly',
        'pattern' => 'Chart Pattern',
        'recommendation' => 'Recommendation',
    ],

    // Trigger types
    'triggers' => [
        'target_price' => 'Target Price',
        'breakout' => 'Breakout',
        'zone' => 'Support/Resistance Zone',
        'gap' => 'Price Gap',
        '52week' => '52-Week High/Low',
        'daily_change' => 'Daily Change',
        'entry_return' => 'Return to Entry',
        'prediction' => 'New Prediction',
        'signal' => 'Technical Signal',
        'anomaly' => 'Anomaly Detection',
        'pattern' => 'Chart Pattern',
        'recommendation' => 'New Recommendation',
    ],

    // Status messages
    'created' => 'Alert created successfully',
    'updated' => 'Alert updated successfully',
    'deleted' => 'Alert deleted successfully',
    'snoozed' => 'Alert snoozed until :until',
    'unsnoozed' => 'Alert unsnoozed',
    'duplicated' => 'Alert duplicated successfully',
    'backtest_started' => 'Backtest started for alert',
];
