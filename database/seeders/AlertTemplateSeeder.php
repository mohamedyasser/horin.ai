<?php

namespace Database\Seeders;

use App\Models\AlertTemplate;
use Illuminate\Database\Seeder;

class AlertTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Price alerts
            [
                'name' => 'Target Price Alert',
                'name_ar' => 'تنبيه السعر المستهدف',
                'description' => 'Alert when price reaches a specific target',
                'description_ar' => 'تنبيه عندما يصل السعر إلى هدف محدد',
                'type' => 'price',
                'trigger_type' => 'target_price',
                'default_parameters' => [
                    'direction' => 'above',
                    'auto_direction' => true,
                ],
            ],
            [
                'name' => 'Daily 5% Move',
                'name_ar' => 'تحرك 5% يومي',
                'description' => 'Alert when stock moves 5% in a day',
                'description_ar' => 'تنبيه عندما يتحرك السهم 5% في اليوم',
                'type' => 'price',
                'trigger_type' => 'daily_change',
                'default_parameters' => [
                    'threshold_percent' => 5.0,
                    'direction' => 'both',
                    'from_reference' => 'open',
                ],
            ],
            [
                'name' => '52-Week High Alert',
                'name_ar' => 'تنبيه أعلى 52 أسبوع',
                'description' => 'Alert when stock makes new 52-week high',
                'description_ar' => 'تنبيه عندما يسجل السهم أعلى مستوى في 52 أسبوع',
                'type' => 'price',
                'trigger_type' => '52week',
                'default_parameters' => [
                    'type' => 'high',
                    'cooldown_hours' => 24,
                ],
            ],
            [
                'name' => 'Support/Resistance Zone',
                'name_ar' => 'منطقة الدعم/المقاومة',
                'description' => 'Alert when price enters a support or resistance zone',
                'description_ar' => 'تنبيه عند دخول السعر منطقة دعم أو مقاومة',
                'type' => 'price',
                'trigger_type' => 'zone',
                'default_parameters' => [
                    'trigger_on' => 'enter',
                    'cooldown_hours' => 4,
                ],
            ],

            // Intelligence alerts
            [
                'name' => 'RSI Oversold',
                'name_ar' => 'RSI في منطقة البيع المفرط',
                'description' => 'Alert when RSI indicates oversold conditions',
                'description_ar' => 'تنبيه عندما يشير RSI إلى ظروف بيع مفرط',
                'type' => 'signal',
                'trigger_type' => 'signal',
                'default_parameters' => [
                    'indicators' => ['RSI'],
                    'signal_types' => ['oversold'],
                    'min_strength' => 0.7,
                    'any_or_all' => 'any',
                ],
            ],
            [
                'name' => 'RSI Overbought',
                'name_ar' => 'RSI في منطقة الشراء المفرط',
                'description' => 'Alert when RSI indicates overbought conditions',
                'description_ar' => 'تنبيه عندما يشير RSI إلى ظروف شراء مفرط',
                'type' => 'signal',
                'trigger_type' => 'signal',
                'default_parameters' => [
                    'indicators' => ['RSI'],
                    'signal_types' => ['overbought'],
                    'min_strength' => 0.7,
                    'any_or_all' => 'any',
                ],
            ],
            [
                'name' => 'MACD Bullish Cross',
                'name_ar' => 'تقاطع MACD الصعودي',
                'description' => 'Alert when MACD shows bullish crossover',
                'description_ar' => 'تنبيه عند تقاطع MACD الصعودي',
                'type' => 'signal',
                'trigger_type' => 'signal',
                'default_parameters' => [
                    'indicators' => ['MACD'],
                    'signal_types' => ['bullish_cross'],
                    'min_strength' => 0.6,
                    'any_or_all' => 'any',
                ],
            ],
            [
                'name' => 'Strong Buy Recommendation',
                'name_ar' => 'توصية شراء قوية',
                'description' => 'Alert when stock receives strong buy recommendation',
                'description_ar' => 'تنبيه عندما يحصل السهم على توصية شراء قوية',
                'type' => 'recommendation',
                'trigger_type' => 'recommendation',
                'default_parameters' => [
                    'trigger_on' => 'change',
                    'recommendations' => ['strong_buy'],
                    'min_score' => 0.8,
                ],
            ],
            [
                'name' => 'Anomaly Detection',
                'name_ar' => 'اكتشاف الشذوذ',
                'description' => 'Alert when unusual price or volume activity detected',
                'description_ar' => 'تنبيه عند اكتشاف نشاط غير عادي في السعر أو الحجم',
                'type' => 'anomaly',
                'trigger_type' => 'anomaly',
                'default_parameters' => [
                    'anomaly_types' => ['price_spike', 'volume_surge'],
                    'min_confidence' => 0.8,
                    'severity' => ['high', 'critical'],
                ],
            ],
            [
                'name' => 'Bullish Pattern',
                'name_ar' => 'نمط صعودي',
                'description' => 'Alert when bullish chart pattern confirmed',
                'description_ar' => 'تنبيه عند تأكيد نمط صعودي على الرسم البياني',
                'type' => 'pattern',
                'trigger_type' => 'pattern',
                'default_parameters' => [
                    'patterns' => ['double_bottom', 'inverse_head_shoulders', 'ascending_triangle'],
                    'pattern_status' => 'confirmed',
                    'min_confidence' => 0.7,
                ],
            ],
            [
                'name' => 'Bearish Pattern',
                'name_ar' => 'نمط هبوطي',
                'description' => 'Alert when bearish chart pattern confirmed',
                'description_ar' => 'تنبيه عند تأكيد نمط هبوطي على الرسم البياني',
                'type' => 'pattern',
                'trigger_type' => 'pattern',
                'default_parameters' => [
                    'patterns' => ['double_top', 'head_shoulders', 'descending_triangle'],
                    'pattern_status' => 'confirmed',
                    'min_confidence' => 0.7,
                ],
            ],
            [
                'name' => 'AI Bullish Prediction',
                'name_ar' => 'تنبؤ صعودي بالذكاء الاصطناعي',
                'description' => 'Alert when AI predicts upward price movement',
                'description_ar' => 'تنبيه عندما يتنبأ الذكاء الاصطناعي بحركة سعرية صعودية',
                'type' => 'prediction',
                'trigger_type' => 'prediction',
                'default_parameters' => [
                    'prediction_type' => 'price_direction',
                    'direction' => 'up',
                    'min_confidence' => 0.75,
                    'horizon' => '1day',
                ],
            ],
        ];

        foreach ($templates as $template) {
            AlertTemplate::firstOrCreate(
                ['name' => $template['name'], 'user_id' => null],
                array_merge($template, [
                    'is_public' => false,
                    'usage_count' => 0,
                ])
            );
        }
    }
}
