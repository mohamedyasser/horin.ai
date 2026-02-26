<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Models\User;
use App\Models\UserAlertPreference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed users with their preferences, markets, and sectors.
     */
    public function run(): void
    {
        $countries = Country::all();
        $markets = Market::all();
        $sectors = Sector::all();

        if ($countries->isEmpty()) {
            $this->command->warn('No countries found. Run CountrySeeder first.');

            return;
        }

        // Create a demo admin user
        $admin = User::factory()->withoutTwoFactor()->create([
            'name' => 'Mohamed Admin',
            'email' => 'admin@horin.app',
            'password' => Hash::make('password'),
            'country_id' => $countries->where('code', 'SA')->first()?->id ?? $countries->first()->id,
            'language' => 'en',
            'theme' => 'system',
            'experience_level' => 'advanced',
            'investment_goal' => 'capital_growth',
            'risk_level' => 'moderate',
            'trading_style' => 'swing_trading',
            'onboarding_completed_at' => now(),
        ]);

        $this->attachPreferences($admin, $markets, $sectors);
        $this->command->info("Admin user created: admin@horin.app / password");

        // Create demo users across different countries & profiles
        $profiles = [
            ['name' => 'Ahmed Investor', 'email' => 'ahmed@horin.app', 'country' => 'SA', 'lang' => 'ar', 'experience' => 'intermediate', 'goal' => 'fixed_income', 'risk' => 'conservative', 'style' => 'position_trading'],
            ['name' => 'Sarah Trader', 'email' => 'sarah@horin.app', 'country' => 'AE', 'lang' => 'en', 'experience' => 'advanced', 'goal' => 'capital_growth', 'risk' => 'aggressive', 'style' => 'day_trading'],
            ['name' => 'Omar Beginner', 'email' => 'omar@horin.app', 'country' => 'EG', 'lang' => 'ar', 'experience' => 'beginner', 'goal' => 'wealth_preservation', 'risk' => 'conservative', 'style' => 'position_trading'],
            ['name' => 'Fatima Analyst', 'email' => 'fatima@horin.app', 'country' => 'QA', 'lang' => 'ar', 'experience' => 'advanced', 'goal' => 'short_term_speculation', 'risk' => 'moderate', 'style' => 'swing_trading'],
            ['name' => 'Khalid Growth', 'email' => 'khalid@horin.app', 'country' => 'KW', 'lang' => 'en', 'experience' => 'intermediate', 'goal' => 'capital_growth', 'risk' => 'moderate', 'style' => 'position_trading'],
            ['name' => 'Noura Value', 'email' => 'noura@horin.app', 'country' => 'BH', 'lang' => 'ar', 'experience' => 'intermediate', 'goal' => 'fixed_income', 'risk' => 'conservative', 'style' => 'position_trading'],
        ];

        foreach ($profiles as $profile) {
            $countryId = $countries->where('code', $profile['country'])->first()?->id ?? $countries->random()->id;

            $user = User::factory()->withoutTwoFactor()->create([
                'name' => $profile['name'],
                'email' => $profile['email'],
                'password' => Hash::make('password'),
                'country_id' => $countryId,
                'language' => $profile['lang'],
                'theme' => fake()->randomElement(['light', 'dark', 'system']),
                'experience_level' => $profile['experience'],
                'investment_goal' => $profile['goal'],
                'risk_level' => $profile['risk'],
                'trading_style' => $profile['style'],
                'onboarding_completed_at' => now()->subDays(rand(1, 90)),
            ]);

            $this->attachPreferences($user, $markets, $sectors);
        }

        // Create 15 additional random users
        User::factory(15)->withoutTwoFactor()->create()->each(function (User $user) use ($countries, $markets, $sectors) {
            $user->update([
                'country_id' => $countries->random()->id,
                'language' => fake()->randomElement(['en', 'ar']),
                'experience_level' => fake()->randomElement(['beginner', 'intermediate', 'advanced']),
                'investment_goal' => fake()->randomElement(['capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation', 'wealth_preservation']),
                'risk_level' => fake()->randomElement(['conservative', 'moderate', 'aggressive']),
                'trading_style' => fake()->randomElement(['day_trading', 'swing_trading', 'position_trading', 'scalping_trading']),
                'onboarding_completed_at' => fake()->boolean(80) ? now()->subDays(rand(1, 180)) : null,
            ]);

            $this->attachPreferences($user, $markets, $sectors);
        });

        $this->command->info('Seeded '.User::count().' users with preferences.');
    }

    /**
     * Attach markets, sectors, wishlists, and alert preferences to a user.
     */
    private function attachPreferences(User $user, mixed $markets, mixed $sectors): void
    {
        // Attach 1-3 random markets
        if ($markets->isNotEmpty()) {
            $user->markets()->syncWithoutDetaching(
                $markets->random(min(rand(1, 3), $markets->count()))->pluck('id')->toArray()
            );
        }

        // Attach 2-5 random sectors
        if ($sectors->isNotEmpty()) {
            $user->sectors()->syncWithoutDetaching(
                $sectors->random(min(rand(2, 5), $sectors->count()))->pluck('id')->toArray()
            );
        }

        // Create alert preferences
        UserAlertPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'default_channels' => fake()->randomElements(['telegram', 'in_app', 'push', 'email'], rand(1, 3)),
                'quiet_hours_start' => fake()->boolean(60) ? '23:00' : null,
                'quiet_hours_end' => fake()->boolean(60) ? '07:00' : null,
                'timezone' => fake()->randomElement(['Asia/Riyadh', 'Asia/Dubai', 'Africa/Cairo', 'Asia/Qatar', 'Asia/Kuwait', 'Asia/Bahrain']),
                'max_alerts_per_hour' => fake()->randomElement([5, 10, 15, 20]),
                'max_alerts_per_day' => fake()->randomElement([25, 50, 100]),
                'digest_enabled' => fake()->boolean(40),
                'digest_time' => fake()->randomElement(['08:00', '18:00', '20:00']),
                'smart_defaults_enabled' => true,
            ]
        );
    }
}
