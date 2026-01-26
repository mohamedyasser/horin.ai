# Horin UX Improvement Roadmap
## Comprehensive 6-Week Implementation Plan

**Created:** January 26, 2026
**Timeline:** 6 weeks (Aggressive)
**Team Size:** 4+ developers
**Stack:** Laravel + Inertia.js + Vue 3 + TypeScript

---

## Executive Summary

### Problem Statement

Horin has powerful features (alerts, Telegram bot) that users cannot discover. Key pages lack action buttons, no watchlist exists, and there's no retention loop to bring users back.

### Solution

A 6-week sprint across 4 parallel workstreams to:
1. Surface existing features
2. Build watchlist system
3. Improve UX and discoverability
4. Create a retention engine

### Key Discovery

Many "missing" features identified in UX audit are **already built** but hidden:
- Alert System: Fully functional with 6+ alert types, 4 delivery channels
- Telegram Bot: Complete with commands, keyboards, handlers
- Onboarding: Exists in codebase

**Actually Missing:**
- Asset Detail page has no action buttons
- Watchlist system not implemented
- Features not promoted to guest users

---

## Workstream Overview

| Workstream | Focus | Owner Profile | Duration |
|------------|-------|---------------|----------|
| **WS1: Quick Wins** | Immediate fixes that unblock value | Frontend dev | Week 1 |
| **WS2: Watchlist System** | New feature: database, API, UI, Telegram | Full-stack dev | Weeks 1-4 |
| **WS3: UX & Discoverability** | Onboarding, guest promos, charts, filters | Frontend + Designer | Weeks 1-5 |
| **WS4: Retention & Engagement** | Telegram bot, notifications, gamification | Backend dev | Weeks 2-6 |

---

## WS1: Quick Wins (Week 1)

High-impact, low-effort changes that unblock value immediately.

### Tasks

| # | Task | File(s) | Effort |
|---|------|---------|--------|
| 1.1 | Add "Create Alert" button to Asset Detail header | `pages/assets/Show.vue` | 1h |
| 1.2 | Add alert promo card for guest users on Asset Detail | `pages/assets/Show.vue` | 1h |
| 1.3 | Add i18n translations for new UI elements | `i18n/ar.json`, `i18n/en.json` | 30m |
| 1.4 | Fix empty states with context + alternative actions | `Recommendations/Index.vue`, sidebar components | 4h |
| 1.5 | Add tooltips to all table headers (الثقة, المدة, نسبة الربح) | `components/predictions/PredictionTable.vue` | 2h |
| 1.6 | Hide/badge EGX market as "قريباً" (0 data) | `pages/Markets/Index.vue` | 30m |
| 1.7 | Add loading skeletons instead of empty jumps | Multiple pages | 3h |
| 1.8 | Add "/" keyboard shortcut to focus search | `layouts/AppLayout.vue` | 30m |
| 1.9 | Show trending stocks on empty search page | `pages/Search/Index.vue` | 2h |
| 1.10 | Add "last updated" timestamp to predictions | `PredictionTable.vue` | 1h |

**Total Effort:** ~16 hours (2 days)

---

## WS2: Watchlist System (Weeks 1-4)

Complete watchlist feature with database, API, frontend UI, and Telegram integration.

### Data Model

```
User (existing)
  └── Watchlists (1:many)
        ├── id, user_id, name, is_default, is_public
        └── WatchlistItems (1:many)
              ├── id, watchlist_id, asset_id, added_at
              └── notes (optional user notes)
```

**Constraints:**
- Free users: 3 watchlists, 30 items each
- Premium users: Unlimited watchlists, 100 items each

### Tasks

| # | Task | Layer | Effort | Week |
|---|------|-------|--------|------|
| 2.1 | Create migrations (`watchlists`, `watchlist_items`) | Backend | 2h | 1 |
| 2.2 | Create `Watchlist` and `WatchlistItem` models with relationships | Backend | 2h | 1 |
| 2.3 | Create `WatchlistController` with CRUD endpoints | Backend | 4h | 1 |
| 2.4 | Create Form Requests for validation | Backend | 1h | 1 |
| 2.5 | Create API Resources for watchlist responses | Backend | 1h | 1 |
| 2.6 | Add watchlist routes (web + API) | Backend | 1h | 1 |
| 2.7 | Create `useWatchlist` composable (add/remove/check) | Frontend | 3h | 2 |
| 2.8 | Create `WatchlistButton.vue` component (star/heart icon) | Frontend | 2h | 2 |
| 2.9 | Add `WatchlistButton` to Asset Detail page | Frontend | 1h | 2 |
| 2.10 | Add `WatchlistButton` to prediction table rows | Frontend | 2h | 2 |
| 2.11 | Create `Watchlist/Index.vue` - list all watchlists | Frontend | 4h | 2 |
| 2.12 | Create `Watchlist/Show.vue` - view watchlist items with prices | Frontend | 6h | 3 |
| 2.13 | Add watchlist management modal (create/rename/delete) | Frontend | 3h | 3 |
| 2.14 | Add watchlist widget to homepage sidebar | Frontend | 3h | 3 |
| 2.15 | Add "Watchlists" to main navigation | Frontend | 30m | 3 |
| 2.16 | Create `WatchlistCommand.php` for Telegram bot | Backend | 4h | 4 |
| 2.17 | Create `WatchlistKeyboard.php` for Telegram | Backend | 2h | 4 |
| 2.18 | Add `/add {symbol}` and `/remove {symbol}` commands | Backend | 3h | 4 |
| 2.19 | Integrate watchlist scope with existing alert system | Backend | 2h | 4 |
| 2.20 | Add i18n translations for all watchlist UI | Frontend | 1h | 4 |
| 2.21 | Write feature tests for watchlist CRUD | Testing | 4h | 4 |

**Total Effort:** ~50 hours

---

## WS3: UX & Discoverability (Weeks 1-5)

Improves conversion and session duration through onboarding, charts, and filtering.

### Sub-areas

| Area | Goal | Primary Metric |
|------|------|----------------|
| Onboarding | Guide new users, explain value | Conversion |
| Guest Promos | Show what registered users get | Conversion |
| Price Charts | Visualize predictions on asset pages | Session duration |
| Advanced Filters | Let users find exactly what they want | Session duration |
| Terminology Help | Reduce confusion for beginners | Bounce rate |

### Tasks

| # | Task | Area | Effort | Week |
|---|------|------|--------|------|
| 3.1 | Create `OnboardingModal.vue` - welcome modal for first-time visitors | Onboarding | 4h | 1 |
| 3.2 | Add "What's your goal?" step (beginner/speculator/professional) | Onboarding | 2h | 1 |
| 3.3 | Create persona-based quick tour (3-4 screens max) | Onboarding | 4h | 2 |
| 3.4 | Store onboarding completion in localStorage (guests) + DB (users) | Onboarding | 2h | 2 |
| 3.5 | Add "Skip" and "Don't show again" options | Onboarding | 1h | 2 |
| 3.6 | Create `FeaturePromoCard.vue` - reusable promo component | Guest | 2h | 1 |
| 3.7 | Add alert promo to Recommendations page (empty state) | Guest | 1h | 2 |
| 3.8 | Add Telegram bot promo to footer/sidebar | Guest | 2h | 2 |
| 3.9 | Create "What you get with account" comparison modal | Guest | 3h | 3 |
| 3.10 | Add subtle "Sign up" prompts after 3+ page views | Guest | 2h | 3 |
| 3.11 | Evaluate charting library (recommend: `lightweight-charts`) | Charts | 2h | 1 |
| 3.12 | Create `PriceChart.vue` component with line/candlestick toggle | Charts | 8h | 2-3 |
| 3.13 | Add prediction overlay markers to chart | Charts | 4h | 3 |
| 3.14 | Add timeframe selector (1D, 5D, 1M, 3M, 6M, 1Y) | Charts | 3h | 3 |
| 3.15 | Integrate `PriceChart` into Asset Detail page | Charts | 2h | 3 |
| 3.16 | Add volume bars below price chart | Charts | 2h | 4 |
| 3.17 | Add confidence bands visualization | Charts | 3h | 4 |
| 3.18 | Create `AdvancedFilters.vue` slide-out panel | Filters | 4h | 3 |
| 3.19 | Add confidence range slider (90-100%) | Filters | 2h | 3 |
| 3.20 | Add expected profit range slider (-10% to +10%) | Filters | 2h | 4 |
| 3.21 | Add timeframe multi-select (2m, 5m, 15m, 1H, 1D, etc.) | Filters | 2h | 4 |
| 3.22 | Add direction toggle (up/down/all) | Filters | 1h | 4 |
| 3.23 | Add "Save filter preset" functionality | Filters | 3h | 5 |
| 3.24 | Show live result count as filters change | Filters | 2h | 4 |
| 3.25 | Create `HelpTooltip.vue` component with "?" icon | Help | 2h | 1 |
| 3.26 | Add tooltips to: الثقة, المدة, نسبة الربح, الإشارة | Help | 2h | 2 |
| 3.27 | Create glossary page `/help/glossary` | Help | 3h | 5 |
| 3.28 | Add "Learn more" links from tooltips to glossary | Help | 1h | 5 |

**Total Effort:** ~72 hours

**Recommended Charting Library:** `lightweight-charts` by TradingView

---

## WS4: Retention & Engagement (Weeks 2-6)

Builds the retention engine - mechanisms that bring users back daily.

### Sub-areas

| Area | Goal | Primary Metric |
|------|------|----------------|
| Telegram Bot Enhancements | Maximize the free notification channel | Daily return rate |
| Notification System | Multi-channel alerts (push, email, in-app) | 7-day retention |
| Gamification | Create habits through achievements | 30-day retention |
| Personalization | Remember user preferences | Session quality |

### Tasks

| # | Task | Area | Effort | Week |
|---|------|------|--------|------|
| 4.1 | Create `/top` command - top 5 predictions now | Telegram | 3h | 2 |
| 4.2 | Create `/news` command - latest 3 headlines | Telegram | 2h | 2 |
| 4.3 | Create `/price {symbol}` command - quick price check | Telegram | 2h | 2 |
| 4.4 | Add inline action buttons to alert notifications | Telegram | 3h | 3 |
| 4.5 | Create daily digest job (8 AM local time) | Telegram | 6h | 3 |
| 4.6 | Create market open reminder (5 min before) | Telegram | 3h | 3 |
| 4.7 | Create weekly performance summary (Sunday) | Telegram | 4h | 4 |
| 4.8 | Add user timezone detection and storage | Telegram | 2h | 3 |
| 4.9 | Create digest preferences (frequency, content) in `/settings` | Telegram | 3h | 4 |
| 4.10 | Create `notifications` database table | Notifications | 2h | 2 |
| 4.11 | Create `Notification` model and relationships | Notifications | 1h | 2 |
| 4.12 | Build in-app notification center UI | Notifications | 6h | 3 |
| 4.13 | Add notification bell icon to header with unread count | Notifications | 2h | 3 |
| 4.14 | Create `NotificationPreferences` settings page | Notifications | 4h | 4 |
| 4.15 | Implement browser push notifications (Web Push API) | Notifications | 6h | 4 |
| 4.16 | Create email digest template (daily/weekly options) | Notifications | 4h | 5 |
| 4.17 | Build email digest queue job | Notifications | 3h | 5 |
| 4.18 | Create `achievements` and `user_achievements` tables | Gamification | 2h | 4 |
| 4.19 | Define achievement types and criteria | Gamification | 2h | 4 |
| 4.20 | Create `AchievementService` to track and award | Gamification | 4h | 4 |
| 4.21 | Build achievements display on user profile | Gamification | 3h | 5 |
| 4.22 | Create achievement unlock notification | Gamification | 2h | 5 |
| 4.23 | Add streak tracking (daily visit counter) | Gamification | 3h | 5 |
| 4.24 | Create streak badge display in header | Gamification | 2h | 5 |
| 4.25 | Create `user_preferences` table (if not exists) | Personalization | 1h | 2 |
| 4.26 | Store preferred market (default to user's country) | Personalization | 2h | 3 |
| 4.27 | Store favorite sectors | Personalization | 2h | 3 |
| 4.28 | Apply preferences to homepage default filters | Personalization | 2h | 4 |
| 4.29 | Add dark mode toggle and persistence | Personalization | 3h | 5 |
| 4.30 | Remember language preference in localStorage + DB | Personalization | 1h | 3 |

**Total Effort:** ~85 hours

### Achievement Badges

| Badge | Criteria | Icon |
|-------|----------|------|
| First Steps | View first prediction | 🏅 |
| Watchful Eye | Add 10 stocks to watchlist | ⭐ |
| Alert Master | Create 5 alerts | 🔔 |
| News Reader | Read 50 news articles | 📰 |
| 7-Day Streak | Visit 7 days in a row | 🔥 |
| 30-Day Streak | Visit 30 days in a row | 💎 |
| Sector Explorer | View all sectors | 🎯 |

---

## Timeline Summary

### Week-by-Week Breakdown

| Week | WS1 | WS2 | WS3 | WS4 |
|------|-----|-----|-----|-----|
| **1** | All tasks complete | Backend foundation | Onboarding modal, Tooltip component, Chart eval | — |
| **2** | Buffer/polish | Frontend composable + button | Onboarding tour, Guest promos, Chart start | Telegram commands, Notifications table |
| **3** | — | Watchlist pages + nav | Chart completion, Filters start | Daily digest, Notification center |
| **4** | — | Telegram integration | Filters completion, Chart extras | Gamification tables, Push notifications |
| **5** | — | Testing & polish | Filter presets, Glossary | Email digest, Achievements UI, Streaks |
| **6** | — | — | Final polish | Dark mode, Final polish |

### Team Allocation

| Developer | Primary Focus | Secondary Focus |
|-----------|---------------|-----------------|
| Dev 1 | WS1 (Week 1) → WS3 Charts (Weeks 2-4) | Polish support |
| Dev 2 | WS2 Backend (Weeks 1-2) → WS4 Telegram (Weeks 3-4) | Testing |
| Dev 3 | WS2 Frontend (Weeks 2-4) → WS3 Filters (Weeks 4-5) | Integration |
| Dev 4 | WS3 Onboarding + Guest (Weeks 1-3) → WS4 Notifications (Weeks 3-5) | Gamification |

### Milestone Checkpoints

| Milestone | Target | Success Criteria |
|-----------|--------|------------------|
| M1: Quick Wins Live | End of Week 1 | Alert button on Asset Detail, empty states fixed, tooltips added |
| M2: Watchlist MVP | End of Week 3 | Users can create watchlists, add/remove stocks from any page |
| M3: Charts Live | End of Week 3 | Price charts with predictions visible on Asset Detail |
| M4: Notifications Working | End of Week 4 | Daily digest via Telegram, in-app notification center |
| M5: Full Feature Complete | End of Week 5 | All features functional, entering polish phase |
| M6: Release Ready | End of Week 6 | Tested, polished, deployed |

### Effort Summary

| Workstream | Total Hours | Team Days |
|------------|-------------|-----------|
| WS1: Quick Wins | 16h | 2 days |
| WS2: Watchlist | 50h | 6.25 days |
| WS3: UX & Discoverability | 72h | 9 days |
| WS4: Retention | 85h | 10.6 days |
| **Total** | **223h** | **~28 team days** |

With 4 developers over 6 weeks (120 team days available), there is comfortable buffer for code review, testing, unexpected issues, and integration work.

---

## Expected Impact

| Metric | Expected Improvement |
|--------|----------------------|
| Bounce Rate | -30% (onboarding + tooltips) |
| Session Duration | +40% (charts + filters) |
| Guest → Registered | +40% (promos + value visibility) |
| Daily Return Rate | +60% (Telegram digest + alerts) |
| 7-Day Retention | +50% (notifications + watchlist) |
| 30-Day Retention | +40% (streaks + gamification) |

---

## Risk Mitigation

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Charting library integration issues | Medium | Week 1 evaluation, fallback to simpler chart |
| Telegram bot rate limiting | Low | Queue system, respect rate limits |
| Scope creep | Medium | Strict milestone gates, defer nice-to-haves |
| Testing bottleneck | Medium | Write tests alongside features, not after |
| Performance with watchlist queries | Low | Eager loading, caching, pagination |

---

## Dependencies

```
Week 1: WS1 Quick Wins (independent)
        WS2 Backend (independent)
        WS3 Onboarding (independent)

Week 2: WS2 Frontend ──depends on──▶ WS2 Backend
        WS3 Charts (independent)
        WS4 Telegram (independent)

Week 3: WS2 Telegram ──depends on──▶ WS2 Backend
        WS4 Digest ──depends on──▶ WS4 Preferences

Week 4: WS4 Gamification (independent)
        WS3 Filters ──depends on──▶ Backend filter params

Week 5: All streams converge for integration
```

---

## Definition of Done

Each feature must have:
- [ ] Working implementation
- [ ] i18n translations (Arabic + English)
- [ ] RTL support verified
- [ ] Feature tests passing
- [ ] Code review approved
- [ ] Deployed to staging
- [ ] Product sign-off

---

## Recommended Package Additions

| Package | Purpose | Install Command |
|---------|---------|-----------------|
| `lightweight-charts` | Price charts (TradingView) | `npm i lightweight-charts` |
| `@vueuse/core` | Composables (if not installed) | `npm i @vueuse/core` |

---

## Reference Documents

- `docs/reports/Horin_UX_Audit_Report.md` - Full UX audit findings
- `docs/reports/Horin_Engagement_Core_Deep_Dive.md` - Engagement analysis
- `docs/reports/Horin_Code_Analysis_Report.md` - Code review and quick wins

---

**Document Status:** Approved for implementation
**Next Steps:** Assign developers to workstreams, create git branches, kick off Week 1
