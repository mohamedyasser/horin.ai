<?php

namespace Tests\Feature;

use App\Models\AssetNew;
use App\Models\NewsBookmark;
use App\Models\NewsRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_index_page_renders(): void
    {
        AssetNew::factory()->published()->count(5)->create();

        $response = $this->get('/en/news');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('news/Index'));
    }

    public function test_news_show_page_renders(): void
    {
        $news = AssetNew::factory()->published()->create();

        $response = $this->get("/en/news/{$news->slug}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('news/Show')
            ->has('news')
        );
    }

    public function test_news_can_be_filtered_by_sentiment(): void
    {
        AssetNew::factory()->published()->positive()->count(3)->create();
        AssetNew::factory()->published()->negative()->count(2)->create();

        $response = $this->get('/en/news?sentiment=positive');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('news/Index')
            ->has('news.data', 3)
        );
    }

    public function test_authenticated_user_can_bookmark_news(): void
    {
        $user = User::factory()->create();
        $news = AssetNew::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/news/{$news->id}/bookmark");

        $response->assertStatus(200);
        $response->assertJson(['bookmarked' => true]);

        $this->assertDatabaseHas('news_bookmarks', [
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
        ]);
    }

    public function test_user_can_remove_bookmark(): void
    {
        $user = User::factory()->create();
        $news = AssetNew::factory()->create();

        NewsBookmark::create([
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
        ]);

        $response = $this->actingAs($user)->postJson("/api/news/{$news->id}/bookmark");

        $response->assertStatus(200);
        $response->assertJson(['bookmarked' => false]);

        $this->assertDatabaseMissing('news_bookmarks', [
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
        ]);
    }

    public function test_authenticated_user_can_rate_news_as_helpful(): void
    {
        $user = User::factory()->create();
        $news = AssetNew::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/news/{$news->id}/rate", [
            'helpful' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['user_rating' => true]);

        $this->assertDatabaseHas('news_ratings', [
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
            'helpful' => true,
        ]);
    }

    public function test_user_can_change_rating(): void
    {
        $user = User::factory()->create();
        $news = AssetNew::factory()->create();

        NewsRating::create([
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
            'helpful' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/news/{$news->id}/rate", [
            'helpful' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['user_rating' => false]);

        $this->assertDatabaseHas('news_ratings', [
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
            'helpful' => false,
        ]);
    }

    public function test_user_can_remove_rating_by_clicking_same_rating(): void
    {
        $user = User::factory()->create();
        $news = AssetNew::factory()->create();

        NewsRating::create([
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
            'helpful' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/news/{$news->id}/rate", [
            'helpful' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['user_rating' => null]);

        $this->assertDatabaseMissing('news_ratings', [
            'user_id' => $user->id,
            'asset_new_id' => $news->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_bookmark_news(): void
    {
        $news = AssetNew::factory()->create();

        $response = $this->postJson("/api/news/{$news->id}/bookmark");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_rate_news(): void
    {
        $news = AssetNew::factory()->create();

        $response = $this->postJson("/api/news/{$news->id}/rate", [
            'helpful' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_featured_news_is_returned_on_index(): void
    {
        // Create a high-score recent news that should be featured
        $featured = AssetNew::factory()->published()->create([
            'score' => 10,
            'created_at' => now(),
        ]);

        // Create other news
        AssetNew::factory()->published()->count(5)->create([
            'score' => 5,
            'created_at' => now()->subDays(10),
        ]);

        $response = $this->get('/en/news');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('news/Index')
            ->where('featured.id', $featured->id)
        );
    }
}
