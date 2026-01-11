<?php

return [
    // Alert types
    'types' => [
        'price' => 'السعر',
        'prediction' => 'تنبؤ الذكاء الاصطناعي',
        'signal' => 'إشارة تقنية',
        'anomaly' => 'شذوذ',
        'pattern' => 'نمط الرسم البياني',
        'recommendation' => 'توصية',
    ],

    // Trigger types
    'triggers' => [
        'target_price' => 'السعر المستهدف',
        'breakout' => 'الاختراق',
        'zone' => 'منطقة الدعم/المقاومة',
        'gap' => 'فجوة السعر',
        '52week' => 'أعلى/أدنى 52 أسبوع',
        'daily_change' => 'التغير اليومي',
        'entry_return' => 'العودة لسعر الدخول',
        'prediction' => 'تنبؤ جديد',
        'signal' => 'إشارة تقنية',
        'anomaly' => 'كشف شذوذ',
        'pattern' => 'نمط رسم بياني',
        'recommendation' => 'توصية جديدة',
    ],

    // Status messages
    'created' => 'تم إنشاء التنبيه بنجاح',
    'updated' => 'تم تحديث التنبيه بنجاح',
    'deleted' => 'تم حذف التنبيه بنجاح',
    'snoozed' => 'تم تأجيل التنبيه حتى :until',
    'unsnoozed' => 'تم إلغاء تأجيل التنبيه',
    'duplicated' => 'تم نسخ التنبيه بنجاح',
    'backtest_started' => 'بدأ اختبار التنبيه على البيانات التاريخية',
];
