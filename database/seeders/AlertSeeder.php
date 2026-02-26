<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AlertBacktestResult;
use App\Models\AlertChain;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\Asset;
use App\Models\FailedNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    /**
     * Seed alerts, alert history, and notifications for users.
     */
    public function run(): void
    {
        $users = User::all();
        $assets = Asset::where('type', 'stock')->get();

        if ($users->isEmpty() || $assets->isEmpty()) {
            $this->command->warn('No users or assets found. Run previous seeders first.');

            return;
        }

        foreach ($users as $user) {
            $this->seedUserAlerts($user, $assets);
        }

        $this->seedAlertChains();
        $this->seedBacktestResults();
        $this->seedFailedNotifications();

        $this->command->info('Seeded alerts for '.$users->count().' users.');
    }

    /**
     * Create a realistic set of alerts for a user.
     */
    private function seedUserAlerts(User $user, mixed $assets): void
    {
        $alertCount = rand(2, 8);
        $userAssets = $assets->random(min($alertCount, $assets->count()));

        foreach ($userAssets as $asset) {
            $alertType = fake()->randomElement(['price', 'signal', 'prediction', 'anomaly', 'pattern', 'recommendation']);
            $alert = $this->createAlert($user, $asset, $alertType);

            // 50% chance of having alert history (triggered alerts)
            if (fake()->boolean(50)) {
                $historyCount = rand(1, 3);
                for ($i = 0; $i < $historyCount; $i++) {
                    $history = $this->createAlertHistory($alert, $user, $asset);

                    // Create notification for this trigger
                    $this->createNotification($alert, $user, $history);
                }
            }
        }

        // Create some additional in_app notifications (unread)
        $extraNotifications = rand(0, 3);
        for ($i = 0; $i < $extraNotifications; $i++) {
            $randomAsset = $assets->random();
            $alert = Alert::where('user_id', $user->id)->inRandomOrder()->first();

            if ($alert) {
                AlertNotification::factory()->inApp()->create([
                    'user_id' => $user->id,
                    'alert_id' => $alert->id,
                    'status' => 'sent',
                    'sent_at' => now()->subMinutes(rand(5, 1440)),
                ]);
            }
        }
    }

    /**
     * Create an alert based on type.
     */
    private function createAlert(User $user, Asset $asset, string $type): Alert
    {
        $baseState = [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
        ];

        $factory = Alert::factory()->state($baseState);

        $factory = match ($type) {
            'price' => $factory->targetPrice(fake()->randomFloat(2, 10, 500)),
            'signal' => $factory->signal(),
            'prediction' => $factory->prediction(),
            'anomaly' => $factory->anomaly(),
            'pattern' => $factory->pattern(),
            'recommendation' => $factory->recommendation(),
            default => $factory,
        };

        // Vary status
        $statusRoll = rand(1, 10);
        if ($statusRoll <= 6) {
            $factory = $factory->active();
        } elseif ($statusRoll <= 8) {
            $factory = $factory->triggered();
        } elseif ($statusRoll === 9) {
            $factory = $factory->paused();
        } else {
            $factory = $factory->expired();
        }

        // Some recurring, some with cooldowns
        if (fake()->boolean(30)) {
            $factory = $factory->recurring();
        }

        if (fake()->boolean(40)) {
            $factory = $factory->withCooldown(fake()->randomElement([15, 30, 60, 240, 1440]));
        }

        if (fake()->boolean(20)) {
            $factory = $factory->expiresIn(rand(7, 90));
        }

        // Vary priority
        $factory = $factory->state([
            'priority' => fake()->randomElement(['low', 'medium', 'medium', 'high', 'critical']),
            'delivery_config' => [
                'channels' => fake()->randomElements(['telegram', 'in_app', 'push', 'email'], rand(1, 3)),
                'sound' => fake()->boolean(60),
            ],
        ]);

        return $factory->create();
    }

    /**
     * Create alert history record.
     */
    private function createAlertHistory(Alert $alert, User $user, Asset $asset): AlertHistory
    {
        return AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'triggered_at' => now()->subHours(rand(1, 720)),
            'trigger_value' => fake()->randomFloat(2, 5, 500),
            'trigger_context' => [
                'price_at_trigger' => fake()->randomFloat(2, 5, 500),
                'change_percent' => fake()->randomFloat(2, -15, 15),
                'volume' => fake()->numberBetween(10000, 5000000),
            ],
            'notification_sent' => true,
            'acknowledged_at' => fake()->boolean(60) ? now()->subHours(rand(0, 24)) : null,
            'escalation_level' => fake()->randomElement([0, 0, 0, 1, 2]),
        ]);
    }

    /**
     * Create alert notification record.
     */
    private function createNotification(Alert $alert, User $user, AlertHistory $history): void
    {
        $channel = fake()->randomElement(['telegram', 'in_app', 'push', 'email']);
        $statusRoll = rand(1, 10);

        $factory = AlertNotification::factory()->state([
            'user_id' => $user->id,
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
            'channel' => $channel,
        ]);

        if ($statusRoll <= 3) {
            $factory = $factory->delivered();
        } elseif ($statusRoll <= 6) {
            $factory = $factory->read();
        } elseif ($statusRoll <= 8) {
            $factory = $factory->sent();
        } elseif ($statusRoll === 9) {
            $factory = $factory->pending();
        } else {
            $factory = $factory->failed('Delivery timeout');
        }

        $factory->create();
    }

    /**
     * Seed alert chains (alerts that trigger other alerts).
     */
    private function seedAlertChains(): void
    {
        $users = User::has('alerts', '>=', 2)->take(5)->get();

        foreach ($users as $user) {
            $alerts = Alert::where('user_id', $user->id)->take(4)->get();

            if ($alerts->count() < 2) {
                continue;
            }

            // Create 1-2 chains per user
            $chainCount = rand(1, min(2, intdiv($alerts->count(), 2)));

            for ($i = 0; $i < $chainCount; $i++) {
                $triggerAlert = $alerts[$i * 2] ?? null;
                $activateAlert = $alerts[($i * 2) + 1] ?? null;

                if ($triggerAlert && $activateAlert) {
                    AlertChain::firstOrCreate(
                        [
                            'user_id' => $user->id,
                            'trigger_alert_id' => $triggerAlert->id,
                            'activate_alert_id' => $activateAlert->id,
                        ],
                        [
                            'name' => fake()->randomElement([
                                'Price then Signal Chain',
                                'Breakout Confirmation',
                                'Dip Buy Strategy',
                                'Momentum Follow-up',
                                'Risk Escalation',
                            ]),
                            'delay_minutes' => fake()->randomElement([0, 5, 15, 30, 60]),
                            'expires_after_minutes' => fake()->boolean(50) ? fake()->randomElement([60, 240, 1440]) : null,
                            'is_active' => fake()->boolean(70),
                        ]
                    );
                }
            }
        }

        $this->command->info('Seeded alert chains.');
    }

    /**
     * Seed alert backtest results for some alerts.
     */
    private function seedBacktestResults(): void
    {
        $alerts = Alert::where('type', 'price')->take(15)->get();

        foreach ($alerts as $alert) {
            // 60% chance of having a backtest result
            if (fake()->boolean(60)) {
                $triggerCount = rand(0, 25);
                $triggers = [];

                for ($i = 0; $i < $triggerCount; $i++) {
                    $triggers[] = [
                        'date' => now()->subDays(rand(1, 365))->toDateString(),
                        'price' => fake()->randomFloat(2, 5, 500),
                        'return_1d' => fake()->randomFloat(4, -0.08, 0.08),
                        'return_1w' => fake()->randomFloat(4, -0.15, 0.15),
                    ];
                }

                $winRate = $triggerCount > 0
                    ? collect($triggers)->where('return_1w', '>', 0)->count() / $triggerCount
                    : 0;

                AlertBacktestResult::create([
                    'alert_id' => $alert->id,
                    'lookback_days' => fake()->randomElement([30, 90, 180, 365]),
                    'trigger_count' => $triggerCount,
                    'triggers' => $triggers,
                    'avg_return_1d' => $triggerCount > 0 ? collect($triggers)->avg('return_1d') : 0,
                    'avg_return_1w' => $triggerCount > 0 ? collect($triggers)->avg('return_1w') : 0,
                    'avg_return_1m' => fake()->randomFloat(4, -0.2, 0.3),
                    'win_rate' => round($winRate, 4),
                    'completed_at' => now()->subHours(rand(1, 168)),
                ]);
            }
        }

        $this->command->info('Seeded alert backtest results.');
    }

    /**
     * Seed a few failed notification records.
     */
    private function seedFailedNotifications(): void
    {
        $failedNotifications = AlertNotification::where('status', 'failed')->take(5)->get();
        $errors = [
            'Telegram API timeout after 30s',
            'Push notification token expired',
            'Email delivery bounced',
            'Rate limit exceeded for channel',
            'User device unreachable',
        ];

        foreach ($failedNotifications as $notification) {
            FailedNotification::firstOrCreate(
                ['notification_id' => $notification->id],
                [
                    'alert_id' => $notification->alert_id,
                    'user_id' => $notification->user_id,
                    'error' => fake()->randomElement($errors),
                    'payload' => [
                        'channel' => $notification->channel,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'attempt' => rand(1, 3),
                    ],
                    'failed_at' => $notification->sent_at ?? now()->subHours(rand(1, 48)),
                    'reviewed_at' => fake()->boolean(30) ? now()->subHours(rand(0, 24)) : null,
                    'should_retry' => fake()->boolean(40),
                ]
            );
        }

        $this->command->info('Seeded failed notification records.');
    }
}
