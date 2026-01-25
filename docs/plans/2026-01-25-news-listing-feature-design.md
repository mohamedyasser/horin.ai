# News Listing Feature Design

## Overview

A comprehensive news system for displaying financial news with multiple entry points, personalization, and interactive features.

## Scope

- Public news feed for all users
- Asset-specific news
- Market, sector, and country-filtered news
- Personalized smart feed for authenticated users
- Interactive features: bookmarks, ratings, comments

## Routes

### Public Routes

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/news` | Main news listing (filterable) |
| GET | `/news/{slug}` | News detail page |
| GET | `/markets/{code}/news` | News by market |
| GET | `/sectors/{id}/news` | News by sector |
| GET | `/countries/{id}/news` | News by country |
| GET | `/assets/{symbol}/news` | News by asset |

### Authenticated Routes

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/dashboard/news` | Personalized smart feed |
| POST | `/news/{id}/bookmark` | Toggle bookmark |
| POST | `/news/{id}/rate` | Rate helpfulness |
| POST | `/news/{id}/comments` | Add comment |
| GET | `/news/{id}/comments` | Get comments |

## Database Schema

### New Tables

```sql
-- User bookmarks
CREATE TABLE news_bookmarks (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    asset_new_id UUID REFERENCES asset_news(id) ON DELETE CASCADE,
    created_at TIMESTAMP,
    UNIQUE(user_id, asset_new_id)
);

-- User ratings (helpful/not helpful)
CREATE TABLE news_ratings (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    asset_new_id UUID REFERENCES asset_news(id) ON DELETE CASCADE,
    helpful BOOLEAN NOT NULL,
    created_at TIMESTAMP,
    UNIQUE(user_id, asset_new_id)
);

-- User comments
CREATE TABLE news_comments (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    asset_new_id UUID REFERENCES asset_news(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Interaction tracking for smart feed
CREATE TABLE user_news_interactions (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    asset_new_id UUID REFERENCES asset_news(id) ON DELETE CASCADE,
    interaction_type VARCHAR(20) NOT NULL, -- view, click, read_time
    metadata JSONB,
    created_at TIMESTAMP
);
```

### Model Relationships

**AssetNew Model:**
- `hasMany` bookmarks
- `hasMany` ratings
- `hasMany` comments
- `belongsTo` asset, market, sector, country

**User Model:**
- `hasMany` newsBookmarks
- `hasMany` newsRatings
- `hasMany` newsComments
- `hasMany` newsInteractions

## API Resources

### AssetNewResource (full detail)

```php
[
    'id',
    'title',
    'slug',
    'description',
    'content',
    'image_url',        // Full URL
    'score',
    'sentiment',
    'action',
    'category',
    'date',
    'created_at',
    'risks',
    'opportunities',
    'affected_sectors',
    'meta_tags',
    'meta_description',
    'asset' => AssetResource,
    'market' => MarketResource,
    'sector' => SectorResource,
    'country' => CountryResource,
    'bookmarked',       // Bool for auth user
    'user_rating',      // helpful/null for auth user
    'ratings_summary',  // {helpful_count, not_helpful_count}
    'comments_count',
]
```

### AssetNewCollectionResource (list view)

```php
[
    'id',
    'title',
    'slug',
    'description',      // Truncated
    'image_url',
    'score',
    'sentiment',
    'action',
    'category',
    'date',
    'created_at',
    'asset' => ['id', 'symbol', 'name'],
    'market' => ['id', 'code'],
    'bookmarked',
]
```

## Frontend Structure

### Pages

```
resources/js/pages/news/
├── Index.vue      - Main news listing with filters
├── Show.vue       - News detail page
└── Dashboard.vue  - Personalized smart feed (auth)
```

### Components

```
resources/js/components/news/
├── NewsCard.vue              - Grid view card
├── NewsListItem.vue          - List view item
├── NewsFeatured.vue          - Hero/featured card
├── NewsPreviewSheet.vue      - Quick preview panel
├── NewsFilters.vue           - Filter bar
├── NewsSearch.vue            - Search with autocomplete
├── NewsPagination.vue        - Hybrid pagination
├── NewsSection.vue           - Reusable section for other pages
├── NewsSentimentBadge.vue    - Sentiment indicator
├── NewsActionBadge.vue       - Action badge (buy/sell/watch)
├── NewsRisksOpportunities.vue - Risks/opportunities display
├── NewsComments.vue          - Comments section
├── NewsBookmarkButton.vue    - Bookmark with soft prompt
├── NewsRatingButtons.vue     - Rating with soft prompt
└── NewsShareButtons.vue      - Social share buttons
```

### TypeScript Types

```typescript
interface AssetNew {
    id: string;
    title: string;
    slug: string;
    description: string;
    content: string;
    image_url: string;
    score: number;
    sentiment: 'positive' | 'negative' | 'neutral';
    action: 'buy' | 'sell' | 'watch' | null;
    category: string;
    date: string;
    created_at: string;
    risks: string[];
    opportunities: string[];
    affected_sectors: string[];
    meta_tags: string[];
    meta_description: string;
    asset?: AssetSummary;
    market?: MarketSummary;
    sector?: SectorSummary;
    country?: CountrySummary;
    bookmarked?: boolean;
    user_rating?: boolean | null;
    ratings_summary?: { helpful_count: number; not_helpful_count: number };
    comments_count?: number;
}

interface NewsFilters {
    search?: string;
    sentiment?: 'positive' | 'negative' | 'neutral';
    category?: string;
    action?: 'buy' | 'sell' | 'watch';
    market_id?: string;
    sector_id?: string;
    country_id?: string;
    asset_id?: string;
    score_min?: number;
    score_max?: number;
    date_from?: string;
    date_to?: string;
}

interface NewsComment {
    id: string;
    user: UserSummary;
    content: string;
    created_at: string;
}
```

## UI Design

### Main News Listing (`/news`)

- Mixed layout: Featured hero card at top + switchable grid/list below
- Full search with filters: sentiment, category, action, market, sector, country, asset, date range, score
- Hybrid pagination: infinite scroll + page jump
- View toggle (grid/list) persisted in localStorage
- Hover shows "Quick preview" button

### News Detail Page (`/news/{slug}`)

- Two-column layout: main content (2/3) + sidebar (1/3)
- Main: hero image, title, metadata, content, risks/opportunities, ratings, bookmark
- Sidebar: asset price card, related news, meta tags, share buttons
- Comments section below main content
- Similar news horizontal scroll at bottom

### Quick Preview (Sheet)

- Slide-out panel from right
- Shows: image, title, description, sentiment, action, first 2 risks/opportunities
- "Read full article" button to detail page

### Integration with Existing Pages

- Deferred loading `NewsSection` component
- Shows 3-5 recent news with "View all" link
- Added to: asset detail, market, sector pages

## Smart Feed Algorithm

Personalization weights:
1. **Watchlist assets (40%)** - News about user's watched assets
2. **User preferences (30%)** - Markets/sectors from settings
3. **Browsing history (20%)** - Based on tracked interactions (7-day decay)
4. **Trending content (10%)** - High-score recent news

## Authentication & Soft Prompts

- All news viewing is public
- Interactive actions (bookmark, rate, comment) trigger soft prompts for guests
- Guest actions stored in localStorage
- On login, prompt to save pending actions
- Authenticated users get full functionality

## Implementation Phases

### Phase 1: Core Foundation
- Migrations for new tables
- Models with relationships
- API Resources
- TypeScript types

### Phase 2: Public News Listing
- AssetNewController with filtering/search
- news/Index.vue page
- Core components: NewsCard, NewsListItem, NewsFeatured, NewsFilters, NewsSearch, NewsPagination
- Route registration

### Phase 3: News Detail Page
- news/Show.vue page
- Components: NewsPreviewSheet, NewsRisksOpportunities, NewsSentimentBadge, NewsActionBadge
- SEO meta tags

### Phase 4: Interactive Features
- Bookmark/Rating controllers and routes
- Components: NewsBookmarkButton, NewsRatingButtons, NewsShareButtons
- Soft prompt login modal
- localStorage for guest actions

### Phase 5: Comments System
- NewsCommentController
- NewsComments component
- Form validation (FormRequest)

### Phase 6: Integration
- Add NewsSection to existing pages
- Deferred loading setup
- Update AssetController, MarketController, SectorController

### Phase 7: Smart Feed
- SmartNewsFeedService
- User interaction tracking middleware
- news/Dashboard.vue
- Browsing history collection

### Phase 8: Polish
- i18n translations (Arabic/English)
- Dark mode styling
- Loading skeletons for all components
- Error states
- Feature tests

## Files to Create

### Backend

```
app/Http/Controllers/AssetNewController.php
app/Http/Controllers/UserNewsController.php
app/Http/Controllers/NewsCommentController.php
app/Http/Resources/AssetNewResource.php
app/Http/Resources/AssetNewCollectionResource.php
app/Http/Resources/NewsCommentResource.php
app/Http/Requests/NewsFilterRequest.php
app/Http/Requests/StoreNewsCommentRequest.php
app/Http/Requests/RateNewsRequest.php
app/Services/SmartNewsFeedService.php
app/Models/NewsBookmark.php
app/Models/NewsRating.php
app/Models/NewsComment.php
app/Models/UserNewsInteraction.php
database/migrations/xxxx_create_news_bookmarks_table.php
database/migrations/xxxx_create_news_ratings_table.php
database/migrations/xxxx_create_news_comments_table.php
database/migrations/xxxx_create_user_news_interactions_table.php
```

### Frontend

```
resources/js/pages/news/Index.vue
resources/js/pages/news/Show.vue
resources/js/pages/news/Dashboard.vue
resources/js/components/news/NewsCard.vue
resources/js/components/news/NewsListItem.vue
resources/js/components/news/NewsFeatured.vue
resources/js/components/news/NewsPreviewSheet.vue
resources/js/components/news/NewsFilters.vue
resources/js/components/news/NewsSearch.vue
resources/js/components/news/NewsPagination.vue
resources/js/components/news/NewsSection.vue
resources/js/components/news/NewsSentimentBadge.vue
resources/js/components/news/NewsActionBadge.vue
resources/js/components/news/NewsRisksOpportunities.vue
resources/js/components/news/NewsComments.vue
resources/js/components/news/NewsBookmarkButton.vue
resources/js/components/news/NewsRatingButtons.vue
resources/js/components/news/NewsShareButtons.vue
resources/js/types/news.ts
resources/js/composables/useNewsFilters.ts
resources/js/composables/useNewsPagination.ts
lang/en/news.php (or JSON)
lang/ar/news.php (or JSON)
```

### Tests

```
tests/Feature/AssetNewControllerTest.php
tests/Feature/NewsBookmarkTest.php
tests/Feature/NewsRatingTest.php
tests/Feature/NewsCommentTest.php
tests/Feature/SmartNewsFeedTest.php
```
