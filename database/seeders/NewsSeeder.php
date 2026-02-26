<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetNew;
use App\Models\Country;
use App\Models\Market;
use App\Models\NewsBookmark;
use App\Models\NewsComment;
use App\Models\NewsRating;
use App\Models\Sector;
use App\Models\User;
use App\Models\UserNewsInteraction;
use Illuminate\Database\Seeder;

class NewsSeeder extends Seeder
{
    /**
     * Seed news articles with bookmarks, ratings, comments, and interactions.
     */
    public function run(): void
    {
        $users = User::all();
        $assets = Asset::where('type', 'stock')->get();
        $markets = Market::all();
        $countries = Country::all();
        $sectors = Sector::all();

        if ($users->isEmpty() || $assets->isEmpty()) {
            $this->command->warn('No users or assets found. Run previous seeders first.');

            return;
        }

        // Create news articles
        $articles = $this->seedArticles($assets, $markets, $countries, $sectors);

        // Create user interactions
        $this->seedBookmarks($users, $articles);
        $this->seedRatings($users, $articles);
        $this->seedComments($users, $articles);
        $this->seedInteractions($users, $articles);

        $this->command->info('Seeded '.count($articles).' news articles with user interactions.');
    }

    /**
     * Create news articles for various assets.
     *
     * @return \Illuminate\Support\Collection<int, AssetNew>
     */
    private function seedArticles(mixed $assets, mixed $markets, mixed $countries, mixed $sectors): mixed
    {
        $articles = collect();

        // Create 40 articles spread across assets
        $selectedAssets = $assets->random(min(20, $assets->count()));

        foreach ($selectedAssets as $asset) {
            $articleCount = rand(1, 3);

            for ($i = 0; $i < $articleCount; $i++) {
                $article = AssetNew::factory()->published()->create([
                    'asset_id' => $asset->id,
                    'market_id' => $asset->market_id ?? ($markets->isNotEmpty() ? $markets->random()->id : null),
                    'country_id' => $asset->country_id ?? ($countries->isNotEmpty() ? $countries->random()->id : null),
                    'sector_id' => $asset->sector_id ?? ($sectors->isNotEmpty() ? $sectors->random()->id : null),
                    'source' => fake()->randomElement(['reuters', 'bloomberg', 'argaam', 'mubasher', 'cnbc_arabia']),
                    'resource_id' => fake()->unique()->uuid(),
                    'reason' => fake()->sentence(),
                ]);

                $articles->push($article);
            }
        }

        // Create some general market news (no specific asset)
        for ($i = 0; $i < 10; $i++) {
            $country = $countries->random();
            $market = $markets->where('country_id', $country->id)->first();

            $article = AssetNew::factory()->create([
                'asset_id' => null,
                'market_id' => $market?->id,
                'country_id' => $country->id,
                'sector_id' => $sectors->isNotEmpty() ? $sectors->random()->id : null,
                'source' => fake()->randomElement(['reuters', 'bloomberg', 'argaam']),
                'resource_id' => fake()->unique()->uuid(),
                'category' => fake()->randomElement(['Economic Update', 'Market Analysis', 'Regulatory News', 'IPO Watch']),
                'reason' => fake()->sentence(),
                'is_rewritten' => true,
                'image_url' => 'news/'.fake()->uuid().'.jpg',
            ]);

            $articles->push($article);
        }

        return $articles;
    }

    /**
     * Seed news bookmarks for users.
     */
    private function seedBookmarks(mixed $users, mixed $articles): void
    {
        foreach ($users as $user) {
            $bookmarkCount = rand(0, 5);
            $bookmarkArticles = $articles->random(min($bookmarkCount, $articles->count()));

            foreach ($bookmarkArticles as $article) {
                NewsBookmark::firstOrCreate([
                    'user_id' => $user->id,
                    'asset_new_id' => $article->id,
                ]);
            }
        }
    }

    /**
     * Seed news ratings for users.
     */
    private function seedRatings(mixed $users, mixed $articles): void
    {
        foreach ($users as $user) {
            $ratingCount = rand(0, 8);
            $ratingArticles = $articles->random(min($ratingCount, $articles->count()));

            foreach ($ratingArticles as $article) {
                NewsRating::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'asset_new_id' => $article->id,
                    ],
                    [
                        'helpful' => fake()->boolean(70),
                    ]
                );
            }
        }
    }

    /**
     * Seed news comments for users.
     */
    private function seedComments(mixed $users, mixed $articles): void
    {
        $commentTexts = [
            'Great analysis, very helpful for my investment decisions.',
            'I disagree with the sentiment, the fundamentals look strong.',
            'This confirms what I was thinking about the sector.',
            'Interesting perspective, will keep watching this stock.',
            'The market overreacted to this news in my opinion.',
            'Thanks for the detailed breakdown.',
            'How does this compare to last quarter?',
            'Important context for understanding the market trend.',
            'I think the risk assessment is accurate here.',
            'Bullish signal confirmed by the technicals too.',
        ];

        $arabicComments = [
            'تحليل ممتاز، مفيد جداً لقرارات الاستثمار.',
            'أختلف مع التقييم، الأساسيات تبدو قوية.',
            'هذا يؤكد ما كنت أتوقعه بشأن القطاع.',
            'وجهة نظر مثيرة للاهتمام، سأستمر في متابعة هذا السهم.',
            'السوق بالغ في رد فعله تجاه هذا الخبر.',
        ];

        foreach ($users as $user) {
            $commentCount = rand(0, 4);
            $commentArticles = $articles->random(min($commentCount, $articles->count()));

            foreach ($commentArticles as $article) {
                $isArabic = $user->language === 'ar';

                NewsComment::create([
                    'user_id' => $user->id,
                    'asset_new_id' => $article->id,
                    'content' => $isArabic
                        ? fake()->randomElement($arabicComments)
                        : fake()->randomElement($commentTexts),
                ]);
            }
        }
    }

    /**
     * Seed user news interactions (views, clicks, read time).
     */
    private function seedInteractions(mixed $users, mixed $articles): void
    {
        $interactionTypes = ['view', 'click', 'read_time'];

        foreach ($users as $user) {
            $interactionCount = rand(3, 15);
            $interactionArticles = $articles->random(min($interactionCount, $articles->count()));

            foreach ($interactionArticles as $article) {
                $type = fake()->randomElement($interactionTypes);

                $metadata = match ($type) {
                    'view' => ['source' => fake()->randomElement(['feed', 'search', 'notification', 'portfolio'])],
                    'click' => ['source' => fake()->randomElement(['feed', 'notification']), 'position' => rand(1, 20)],
                    'read_time' => ['seconds' => rand(10, 300), 'scroll_percent' => rand(10, 100)],
                };

                UserNewsInteraction::create([
                    'user_id' => $user->id,
                    'asset_new_id' => $article->id,
                    'interaction_type' => $type,
                    'metadata' => $metadata,
                ]);
            }
        }
    }
}
