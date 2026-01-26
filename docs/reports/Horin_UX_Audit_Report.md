# Horin.ai - Comprehensive UX Audit Report
## Utility Density & Retention Analysis

**Platform:** horin.ai
**Audit Date:** January 26, 2026
**Auditor Role:** Senior Product Lead & UX Researcher (Fintech Specialization)

---

## Executive Summary

Horin is a **Financial Data/Analytics platform** focused on AI-powered price predictions for Middle Eastern stock markets (Saudi Arabia, Egypt, UAE, Kuwait, Qatar, Bahrain). The platform shows strong foundational architecture but has **critical friction points** that significantly impact utility density and user retention.

**Overall Platform Score:** 6.8/10

### Key Findings at a Glance

| Metric | Score | Priority |
|--------|-------|----------|
| Data Richness | 8/10 | ✅ Strength |
| Navigation Clarity | 7/10 | ⚠️ Moderate |
| Empty State Handling | 3/10 | 🔴 Critical |
| Onboarding Experience | 4/10 | 🔴 Critical |
| Professional Features | 7/10 | ⚠️ Moderate |
| Mobile Readiness | TBD | - |

---

## Page-by-Page Analysis

---

### 1. Homepage (horin.ai/ar)

#### Overview
The homepage serves as the primary entry point, displaying price predictions across all markets with filtering capabilities.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 5/10 | No onboarding, unclear terminology (ثقة, المدة, نسبة الربح) |
| **Speculator** | 7/10 | Quick access to predictions, but no real-time alerts |
| **Professional** | 6/10 | Limited customization, no advanced filters |

#### Friction Analysis

**Critical Issues:**
1. **No Value Proposition Explanation** - New users land directly on data without understanding what predictions mean or how to use them
2. **Terminology Without Context** - "الثقة 99.28%" appears without explanation of what confidence scores represent
3. **No Guided First Experience** - Missing tooltips, tutorials, or progressive disclosure
4. **Market Tabs Require Prior Knowledge** - Users must know what "TASI" or "EGX" means

**Moderate Issues:**
1. **Sidebar Widgets Lack Interactivity** - "الأكثر ارتفاعاً" and "الأعلى ثقة" are static lists
2. **No Personalization** - Cannot save favorite stocks or customize dashboard
3. **Limited Sorting Options** - Basic filters but no advanced sorting combinations

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🔴 P0 | Add interactive onboarding modal for first-time visitors | +40% activation rate |
| 🔴 P0 | Add hover tooltips explaining each column (الثقة, المدة, نسبة الربح) | -30% bounce rate |
| 🟡 P1 | Implement "Add to Watchlist" functionality | +25% return visits |
| 🟡 P1 | Add real-time price change indicators (blinking/animation) | +15% session duration |
| 🟢 P2 | Enable custom column arrangement | +10% professional engagement |

---

### 2. Recommendations Page (horin.ai/ar/recommendations)

#### Overview
Displays AI-generated trading signals (buy/sell recommendations) filtered by market.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 2/10 | Confusing empty state, no guidance |
| **Speculator** | 4/10 | Core feature but often shows no data |
| **Professional** | 3/10 | Cannot filter by confidence threshold or sector |

#### Friction Analysis

**CRITICAL: Empty State Crisis**

The page frequently displays: **"لا توجد توقعات متاحة للفلاتر المحددة"** (No predictions available for selected filters)

This is a **retention killer** because:
- Users expect actionable signals but find nothing
- No explanation of WHY no recommendations exist
- No alternative actions suggested
- Sidebar widgets also show "لا توجد بيانات متاحة"

**Additional Issues:**
1. **Filter Badge Shows "1"** - Indicates active filter but user didn't consciously apply it
2. **No Historical Recommendations** - Cannot see past signals and their accuracy
3. **Missing Notification Setup** - Cannot alert when new recommendations appear

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🔴 P0 | Redesign empty state with: explanation, expected timing, notification signup | +50% retention |
| 🔴 P0 | Show "last recommendation" with timestamp even when current is empty | +30% trust |
| 🟡 P1 | Add recommendation history with accuracy tracking | +40% professional adoption |
| 🟡 P1 | Implement push/email notifications for new signals | +35% daily return rate |
| 🟢 P2 | Add confidence threshold slider filter | +15% professional engagement |

---

### 3. Markets Page (horin.ai/ar/markets)

#### Overview
Overview of available markets with prediction counts and quick navigation.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 7/10 | Clear market cards, good visual hierarchy |
| **Speculator** | 6/10 | Quick access but limited comparative data |
| **Professional** | 5/10 | No market indices, no correlation data |

#### Friction Analysis

**Positive Elements:**
- Clear market cards with country labels
- Prediction counts visible (438,386 for TASI)
- "عرض التوقعات" CTA is prominent

**Issues:**
1. **EGX Shows 0 Predictions** - Egyptian market card shows "0 توقعات" and "0 أصول" - should be hidden or marked as "coming soon"
2. **Missing Market Performance Summary** - No daily/weekly change for market indices
3. **No Market Comparison View** - Cannot compare performance across markets
4. **"مغلق" Status Not Actionable** - Market closed indicator exists but no opening time shown

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🔴 P0 | Hide or badge markets with 0 data as "قريباً" (Coming Soon) | -20% confusion |
| 🟡 P1 | Add market index summary (TASI index value, daily change %) | +25% speculator engagement |
| 🟡 P1 | Show market opening countdown when closed | +15% session timing optimization |
| 🟢 P2 | Add market correlation heatmap for professionals | +20% professional retention |

---

### 4. Sectors Page (horin.ai/ar/sectors)

#### Overview
Breakdown of predictions by industry sector with detailed statistics.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 6/10 | Clear sector names but limited context |
| **Speculator** | 8/10 | Good for sector rotation strategies |
| **Professional** | 7/10 | Decent depth, missing comparative analytics |

#### Friction Analysis

**Positive Elements:**
- Rich data presentation (الصناعية: 104,070 توقعات, 73 أصول)
- Clear sector descriptions
- Good sorting sidebar ("أفضل القطاعات", "القطاع الرائج")

**Issues:**
1. **No Sector Performance Metrics** - Shows prediction count but not sector returns
2. **Static Presentation** - No charts or visual trends
3. **Missing Sector Comparison** - Cannot overlay sectors for comparison

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🟡 P1 | Add sector performance sparklines (7-day trend) | +30% speculator engagement |
| 🟡 P1 | Include sector-level buy/sell signal aggregation | +25% actionability |
| 🟢 P2 | Add sector rotation visualization | +20% professional retention |

---

### 5. Search Page (horin.ai/ar/search)

#### Overview
Asset search functionality with recent search history.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 8/10 | Simple, clear, includes search history |
| **Speculator** | 7/10 | Fast results, shows key metrics |
| **Professional** | 6/10 | No advanced search (by PE, volume, sector) |

#### Friction Analysis

**Positive Elements:**
- Recent searches feature ("عمليات البحث الأخيرة")
- Fast autocomplete
- Results show price and change immediately

**Issues:**
1. **No Search Suggestions** - New users see empty state, not trending stocks
2. **Limited Search Scope** - Cannot search by criteria (e.g., "stocks up >5%")
3. **No Keyboard Shortcuts** - Missing "/" to focus search

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🟡 P1 | Show trending searches for new visitors | +20% activation |
| 🟡 P1 | Add global search shortcut (/) | +15% power user engagement |
| 🟢 P2 | Implement advanced search filters | +25% professional adoption |

---

### 6. Stock Detail Page (horin.ai/ar/assets/{symbol})

#### Overview
Individual asset page showing price, predictions across multiple timeframes, and historical accuracy.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 5/10 | Overwhelming data without context |
| **Speculator** | 8/10 | Multi-timeframe predictions are excellent |
| **Professional** | 7/10 | Good depth, missing fundamental data |

#### Friction Analysis

**Positive Elements:**
- Multi-timeframe predictions (5m, 2m, 1H, 15m, 1D, 1W, 1M, 3M, 6M, 1Y)
- Confidence scores for each prediction
- Historical accuracy tracking ("التوقعات الأخيرة" with accuracy %)
- Real-time price data with volume

**Issues:**
1. **"لا توجد توصية متاحة"** - Even on major stocks like Aramco, sometimes shows no recommendation
2. **No Price Chart** - Missing candlestick or line chart visualization
3. **No Fundamental Data** - PE ratio, market cap, dividend yield missing
4. **No Social/News Integration** - No related news or sentiment on stock page

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🔴 P0 | Add interactive price chart with prediction overlays | +40% session duration |
| 🔴 P0 | Always show at least a "neutral" recommendation with reasoning | +30% trust |
| 🟡 P1 | Add fundamental data section | +35% professional adoption |
| 🟡 P1 | Integrate related news feed from news page | +25% stickiness |
| 🟢 P2 | Add price alerts functionality | +40% daily return rate |

---

### 7. News Page (horin.ai/ar/news)

#### Overview
AI-powered news and analysis with sentiment classification.

#### Utility Score by Persona

| Persona | Score | Rationale |
|---------|-------|-----------|
| **Beginner** | 7/10 | Clear sentiment badges, readable format |
| **Speculator** | 8/10 | Sentiment filter is actionable |
| **Professional** | 7/10 | Good coverage, missing custom feeds |

#### Friction Analysis

**Positive Elements:**
- Excellent sentiment classification (إيجابي, سلبي, محايد)
- News quality scores (10/10, 8/10, 7/10)
- Visual tags (مراقبة, حركة التداولات)
- Good imagery and formatting
- Multiple view modes (list/grid)

**Issues:**
1. **No Stock-Specific News Filter** - Cannot filter news for watchlist stocks
2. **No News Alerts** - Cannot subscribe to news about specific stocks
3. **Missing Source Diversity Indicator** - Cannot assess news source reliability

#### Optimization Plan

| Priority | Action | Expected Impact |
|----------|--------|-----------------|
| 🟡 P1 | Add "News for my watchlist" filter | +30% personalization value |
| 🟡 P1 | Implement news alert subscriptions | +35% daily return rate |
| 🟢 P2 | Add source credibility scores | +15% trust |

---

## Cross-Platform Issues

### Navigation & Information Architecture

**Issues Identified:**
1. **"ابدأ الآن" Button Unclear** - CTA in header but unclear what action it triggers
2. **Duplicate Navigation Items** - "التوقعات" appears twice in some contexts
3. **No Breadcrumbs** - Deep pages lack navigation context
4. **Missing Footer Utility** - Footer has legal links but no quick navigation

### Data Loading Experience

**Issues Identified:**
1. **No Loading States** - Pages jump from empty to full without skeleton loaders
2. **No Error States** - When API fails, users see generic empty states
3. **No Offline Handling** - No indication when connection is lost

### Personalization Gap

**Critical Missing Features:**
1. **No User Accounts** - Cannot save preferences, watchlists, or settings
2. **No Customizable Dashboard** - One-size-fits-all experience
3. **No Alert System** - Push notifications for price/recommendation changes

---

## Retention & Session Duration Optimization Matrix

### Quick Wins (Implement in 1-2 weeks)

| Feature | Effort | Impact on Retention | Impact on Session Duration |
|---------|--------|--------------------|-----------------------------|
| Tooltips for terminology | Low | +20% | +5% |
| Trending stocks on search | Low | +15% | +10% |
| Fix empty states with context | Medium | +30% | +15% |
| Add price charts to stock pages | Medium | +25% | +40% |

### Strategic Initiatives (1-3 months)

| Feature | Effort | Impact on Retention | Impact on Session Duration |
|---------|--------|--------------------|-----------------------------|
| User accounts & watchlists | High | +50% | +30% |
| Push notification system | High | +60% | +20% |
| Interactive onboarding | Medium | +40% | +25% |
| Advanced filtering | Medium | +20% | +35% |

---

## Persona-Specific Recommendations

### For Beginners
1. Create guided onboarding flow explaining predictions
2. Add "What does this mean?" links throughout
3. Implement "Beginner Mode" with simplified interface
4. Add educational content section

### For Speculators
1. Real-time price alerts and push notifications
2. Quick-action buttons (Add to watchlist, Set alert)
3. Market open/close countdowns
4. Momentum indicators and trending signals

### For Professionals
1. API access for data export
2. Custom screening tools
3. Portfolio tracking integration
4. Historical backtesting data
5. Bulk data download options

---

## Priority Implementation Roadmap

### Phase 1: Critical Fixes (Weeks 1-2)
- [ ] Fix empty state messaging across all pages
- [ ] Add tooltips for all terminology
- [ ] Implement price charts on stock pages
- [ ] Hide/badge markets with no data

### Phase 2: Core Experience (Weeks 3-6)
- [ ] Build user account system
- [ ] Create watchlist functionality
- [ ] Add onboarding flow
- [ ] Implement basic price alerts

### Phase 3: Engagement Features (Weeks 7-12)
- [ ] Push notification system
- [ ] News alerts by stock
- [ ] Advanced filtering & screening
- [ ] Sector performance analytics

### Phase 4: Professional Features (Months 4-6)
- [ ] API access
- [ ] Portfolio tracking
- [ ] Backtesting tools
- [ ] Export capabilities

---

## Conclusion

Horin has **strong foundational data and prediction capabilities** but suffers from significant UX friction that limits its utility density. The most critical issues are:

1. **Empty state handling** - Users frequently encounter "no data" without context
2. **Onboarding absence** - New users have no guidance
3. **Personalization gap** - Cannot save preferences or create watchlists

Addressing these three areas alone could potentially **double daily active user retention** and **increase average session duration by 40%+**.

The platform has excellent potential for high-frequency return visits given its predictive data nature, but currently fails to capitalize on this through lack of alerts, notifications, and personalization features.

---

**Report Generated:** January 26, 2026
**Analysis Method:** Full manual audit across all primary pages
**Recommendations Based On:** Fintech UX best practices, retention optimization frameworks, persona-based design principles
