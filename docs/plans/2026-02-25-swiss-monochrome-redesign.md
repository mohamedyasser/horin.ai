# Horin Frontend Redesign — Swiss Monochrome

**Date:** 2026-02-25
**Style:** Swiss Monochrome — Minimal + Premium + Professional + Accessible
**Scope:** Full redesign — Design system + Layouts + All 35 pages

---

## 1. Design System — Tokens & Foundation

### 1.1 Color Palette

#### Light Mode

| Token                   | Value       | Usage                |
|-------------------------|-------------|----------------------|
| `--background`          | `#FFFFFF`   | Page background      |
| `--foreground`          | `#09090B`   | Primary text         |
| `--card`                | `#FFFFFF`   | Card surfaces        |
| `--card-foreground`     | `#09090B`   | Card text            |
| `--muted`               | `#F4F4F5`   | Muted backgrounds    |
| `--muted-foreground`    | `#71717A`   | Secondary text       |
| `--border`              | `#E4E4E7`   | All borders          |
| `--input`               | `#E4E4E7`   | Input borders        |
| `--primary`             | `#09090B`   | Primary actions      |
| `--primary-foreground`  | `#FAFAFA`   | Text on primary      |
| `--secondary`           | `#F4F4F5`   | Secondary actions    |
| `--secondary-foreground`| `#09090B`   | Text on secondary    |
| `--accent`              | `#F4F4F5`   | Hover states         |
| `--accent-foreground`   | `#09090B`   | Text on accent       |
| `--destructive`         | `#DC2626`   | Errors, destructive  |
| `--destructive-foreground`| `#FAFAFA` | Text on destructive  |
| `--ring`                | `#09090B`   | Focus rings          |
| `--popover`             | `#FFFFFF`   | Popover background   |
| `--popover-foreground`  | `#09090B`   | Popover text         |

#### Dark Mode

| Token                   | Value       |
|-------------------------|-------------|
| `--background`          | `#09090B`   |
| `--foreground`          | `#FAFAFA`   |
| `--card`                | `#0A0A0B`   |
| `--card-foreground`     | `#FAFAFA`   |
| `--muted`               | `#18181B`   |
| `--muted-foreground`    | `#A1A1AA`   |
| `--border`              | `#27272A`   |
| `--input`               | `#27272A`   |
| `--primary`             | `#FAFAFA`   |
| `--primary-foreground`  | `#09090B`   |
| `--secondary`           | `#27272A`   |
| `--secondary-foreground`| `#FAFAFA`   |
| `--accent`              | `#27272A`   |
| `--accent-foreground`   | `#FAFAFA`   |
| `--destructive`         | `#DC2626`   |
| `--destructive-foreground`| `#FAFAFA` |
| `--ring`                | `#FAFAFA`   |
| `--popover`             | `#0A0A0B`   |
| `--popover-foreground`  | `#FAFAFA`   |

#### Financial Signal Colors (only non-monochrome colors)

| Token          | Value               | Usage                    |
|----------------|---------------------|--------------------------|
| `--gain`       | `#16A34A`           | Positive gain, buy       |
| `--loss`       | `#DC2626`           | Negative loss, sell      |
| `--gain-muted` | `#16A34A` at 10%    | Gain badge backgrounds   |
| `--loss-muted` | `#DC2626` at 10%    | Loss badge backgrounds   |

#### Sidebar Colors

| Token                          | Light       | Dark        |
|--------------------------------|-------------|-------------|
| `--sidebar-background`         | `#FAFAFA`   | `#0A0A0B`   |
| `--sidebar-foreground`         | `#3F3F46`   | `#A1A1AA`   |
| `--sidebar-primary`            | `#09090B`   | `#FAFAFA`   |
| `--sidebar-primary-foreground` | `#FAFAFA`   | `#09090B`   |
| `--sidebar-accent`             | `#F4F4F5`   | `#18181B`   |
| `--sidebar-accent-foreground`  | `#09090B`   | `#FAFAFA`   |
| `--sidebar-border`             | `#E4E4E7`   | `#27272A`   |
| `--sidebar-ring`               | `#09090B`   | `#FAFAFA`   |

#### Chart Colors

| Token       | Light       | Dark        |
|-------------|-------------|-------------|
| `--chart-1` | `#09090B`   | `#FAFAFA`   |
| `--chart-2` | `#71717A`   | `#A1A1AA`   |
| `--chart-3` | `#A1A1AA`   | `#71717A`   |
| `--chart-4` | `#D4D4D8`   | `#3F3F46`   |
| `--chart-5` | `#E4E4E7`   | `#27272A`   |

### 1.2 Typography

| Element        | Font (EN)  | Font (AR) | Weight | Size                          | Letter-spacing |
|----------------|------------|-----------|--------|-------------------------------|----------------|
| H1 (hero)      | Inter      | Cairo     | 700    | `clamp(2rem, 4vw, 3.5rem)`   | `-0.025em`     |
| H2 (section)   | Inter      | Cairo     | 600    | `1.5rem`                      | `-0.02em`      |
| H3 (card title)| Inter      | Cairo     | 600    | `1.125rem`                    | `-0.01em`      |
| Body           | Inter      | Cairo     | 400    | `0.9375rem` (15px)            | `0`            |
| Small/caption  | Inter      | Cairo     | 400    | `0.8125rem` (13px)            | `0`            |
| Numbers        | tabular-nums variant | — | 500  | inherit                       | `0`            |

### 1.3 Spacing & Borders

| Property         | Value               |
|------------------|---------------------|
| Border radius    | `0.375rem` (6px)    |
| Border width     | `1px`               |
| Card padding     | `1.25rem` (20px)    |
| Section gap      | `1.5rem` (24px)     |
| Page padding     | `1.5rem` mobile, `2rem` desktop |
| Max content width| `80rem` (1280px)    |

### 1.4 Z-Index Scale

| Layer                | Value  |
|----------------------|--------|
| Base content         | `auto` |
| Sticky headers       | `10`   |
| Dropdowns/popovers   | `20`   |
| Sidebar              | `30`   |
| Modals/dialogs       | `40`   |
| Toasts/notifications | `50`   |

### 1.5 Shared Component Changes

| Component          | Change                                                                 |
|--------------------|------------------------------------------------------------------------|
| Button (primary)   | `bg-foreground text-background hover:bg-foreground/90`                 |
| Button (outline)   | `border-border hover:bg-muted`                                        |
| Card               | Remove all shadows. `border border-border` only.                      |
| Badge              | `text-xs font-medium px-2 py-0.5 rounded-sm`                         |
| Table rows         | `hover:bg-muted/50 transition-colors duration-150 cursor-pointer`     |
| Input              | `border-border focus:ring-1 focus:ring-foreground`                    |
| Skeleton           | `bg-muted animate-pulse rounded-sm`                                   |
| All interactive    | `cursor-pointer` on anything clickable                                |
| Transitions        | `duration-200` globally, respect `prefers-reduced-motion`             |
| Numbers            | `tabular-nums` on all financial data for column alignment             |

---

## 2. Layouts

### 2.1 Public Layout (GuestLayout) — Floating Navbar

**Navbar:**
- `fixed top-0 inset-x-0 z-30`
- Default: `bg-transparent`
- On scroll (>50px): `bg-background/80 backdrop-blur-lg border-b border-border`
- Height: `64px`
- Inner: `max-w-7xl mx-auto px-6 flex items-center justify-between`
- Left: Logo wordmark (black on light, white on dark)
- Center: Nav links — `text-sm font-medium hover:text-foreground transition-colors duration-200`
- Right: Language switcher + Login (ghost) + Register (primary solid)
- Mobile: Hamburger → Sheet from top

**Page body:**
- `pt-16` for fixed navbar offset
- Sections alternate `bg-background` / `bg-muted/30`
- Each section: `max-w-7xl mx-auto px-6 py-16`
- Footer: `border-t border-border`, minimal (logo + nav + copyright)

### 2.2 Authenticated Layout (AppLayout + Sidebar)

**Sidebar:**
- Width: `256px` expanded, `48px` collapsed
- `bg-background border-e border-border`
- Nav items: `text-sm font-medium py-2 px-3 rounded-md`
- Active: `bg-muted text-foreground font-semibold`
- Hover: `bg-muted/50`
- Footer: User avatar + name + settings dropdown
- Mobile: Sheet overlay from start side, `w-72`

**Content area:**
- Top: Breadcrumbs + title + actions
- Content: `p-6`, full width
- Scroll: Content scrolls, sidebar fixed

### 2.3 Auth Layout — Centered Card

- `max-w-sm mx-auto mt-24`
- Logo above
- Card: `bg-card border border-border rounded-md p-8`
- Fields: `space-y-4`

### 2.4 Settings Layout

- Sub-navigation sidebar within content area
- Mobile: horizontal scrollable tabs
- Content: `max-w-2xl`

---

## 3. Page Redesigns — All 35 Pages

### Group A: Public Pages (Guest Layout)

#### A1. Welcome/Home (Welcome.vue)
- Hero: `py-20`, large heading `clamp(2rem,4vw,3.5rem)`, subtitle `text-muted-foreground`, search bar `max-w-xl h-12`
- Market filter bar: horizontal pills, active = `bg-foreground text-background`
- Tab section: Recommendations | Predictions | News. Indicator = `border-b-2 border-foreground`
- Predictions table: `border border-border rounded-md`, `hover:bg-muted/50` rows, tabular-nums, green/red gains
- Sidebar cards: `border border-border`, no shadows, compact lists
- Empty states: 50% opacity icon + muted text

#### A2. Markets (Markets.vue)
- Grid of market cards: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4`
- Card: `border border-border rounded-md p-5 hover:bg-muted/30 cursor-pointer transition-colors duration-200`
- Market name + country flag + asset count + key stats

#### A3. Market Detail (markets/Show.vue)
- Header: market name + country + description
- Stats row: 3-4 KPI cards, value `text-2xl font-bold tabular-nums`, label `text-xs text-muted-foreground uppercase tracking-wide`
- Asset table below, same style as home

#### A4. Sectors (Sectors.vue)
- Same grid pattern as Markets

#### A5. Sector Detail (sectors/Show.vue)
- Same structure as Market Detail

#### A6. Predictions (Predictions.vue)
- Full-width data table with inline filter row (selects + search)
- Sortable columns, green/red gains, confidence percentages
- Pagination at bottom

#### A7. Recommendations (Recommendations.vue)
- Similar table to predictions
- Buy badge: `bg-gain-muted text-gain`, Sell badge: `bg-loss-muted text-loss`
- Columns: Asset, Signal, Entry, Target, Stop Loss, Confidence, Date

#### A8. Asset Detail (assets/Show.vue)
- Header: symbol `text-2xl font-bold` + name + market badge
- Price: `text-3xl font-bold tabular-nums` + daily change green/red
- Chart: ECharts, monochrome theme, grid lines in border color
- Tabs: Predictions | Recommendations | News
- Sidebar: key stats in compact list

#### A9. Search (Search.vue)
- Large input `max-w-2xl mx-auto`
- Results grouped by type with section headings

#### A10. News Index (news/Index.vue)
- Grid: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`
- Card: image + title + source + date + sentiment badge

#### A11. News Detail (news/Show.vue)
- Article: `max-w-3xl mx-auto`
- Image `rounded-md`, title `text-2xl font-bold`, body `leading-relaxed`
- Related assets + comments below

#### A12-A17. Static Pages (About, FAQ, Methodology, Privacy, Terms, Contact)
- All: `max-w-3xl mx-auto py-12`, prose styling
- FAQ: collapsible accordion with `border-b border-border`
- Contact: simple form

### Group B: Authenticated Pages (App Layout)

#### B1. Dashboard (Dashboard.vue) — Full Redesign
- Welcome: "Good morning, {name}" + date, `text-lg`
- KPI row: 4 cards — Portfolio value, Daily P&L, Active alerts, Watchlist count
  - Value: `text-2xl font-bold tabular-nums`
  - Label: `text-xs text-muted-foreground uppercase tracking-wide`
  - Trend: small arrow + percentage green/red
- Two-column: Watchlist table (2/3) + Recent alerts feed (1/3)
- Bottom row: Recent predictions + Recent recommendations, side by side
- All use `Deferred` props with skeleton loading

#### B2. Onboarding (Onboarding.vue)
- Horizontal stepper: active = `bg-foreground text-background` circle, completed = checkmark, upcoming = hollow
- Content: `max-w-lg mx-auto`, clean forms, Next/Back buttons

### Group C: Alert Pages

#### C1. Alerts Index (Alerts/Index.vue)
- Header + "Create Alert" button (primary solid)
- Alert cards: type icon + asset + condition + status badge
- Status: active = `bg-foreground text-background`, paused = `bg-muted`, triggered = green/red

#### C2. Alert Create (Alerts/Create.vue)
- Step form: Asset → Type → Config → Delivery. `max-w-2xl mx-auto`

#### C3. Alert Edit (Alerts/Edit.vue)
- Same as Create, pre-filled

#### C4. Alert Show (Alerts/Show.vue)
- Details header + config section + trigger history table

#### C5. Alert History (Alerts/History.vue)
- Full trigger history table, filterable

### Group D: Auth Pages

#### D1-D4. Auth pages (TelegramAuth, TwoFactorChallenge, VerifyEmail, VerifyPhone)
- Centered card layout, relevant icon, loading/input states

### Group E: Settings Pages

All use settings sub-layout, content `max-w-2xl`.

#### E1. Profile — Name, email, avatar upload
#### E2. Password — Current/new/confirm password form
#### E3. Appearance — Theme toggle with 3 selectable cards (Light/Dark/System)
#### E4. Two-Factor — Enable toggle, QR code, recovery codes in monospace
#### E5. Alerts Settings — Notification toggles per channel per type
#### E6. Market Preferences — Multi-select markets/sectors
#### E7. Trading Profile — Risk tolerance, style, horizons (selectable cards)

---

## 4. UX Standards

### Accessibility
- Color contrast: 4.5:1 minimum (WCAG AA)
- Focus rings: visible `ring-1 ring-foreground` on all interactive
- Keyboard nav: tab order matches visual order
- `aria-label` on icon-only buttons
- `alt` text on all meaningful images
- `prefers-reduced-motion` respected

### Loading States
- Skeleton screens (`animate-pulse`) for all deferred content
- Button loading: disable + spinner for async operations >300ms
- No frozen UI — always show system status

### Interactions
- `cursor-pointer` on all clickable elements
- Hover: `transition-colors duration-200`
- No layout-shifting hover effects (no scale transforms)
- Touch targets: minimum 44x44px

### Responsive Breakpoints
- 375px (mobile)
- 768px (tablet)
- 1024px (desktop)
- 1440px (wide)
- No horizontal scroll on any viewport

### RTL Support
- All layouts use logical properties (`start`/`end` not `left`/`right`)
- Cairo font for Arabic
- Sidebar on correct side per locale
- Arrow icons flip direction in RTL

---

## 5. Anti-Patterns to Avoid

- No emojis as icons — use Lucide SVGs only
- No box shadows on cards — borders only
- No gradient backgrounds
- No color accents other than green/red financial signals
- No bouncy/spring animations
- No arbitrary z-index values (use scale: 10/20/30/40/50)
- No `z-[9999]`
- No continuous decorative animations (only loading indicators)
