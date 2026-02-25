# Swiss Monochrome Redesign — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Redesign all 35 frontend pages with a Swiss Monochrome design system — pure black/white palette with green/red financial accents only.

**Architecture:** Update CSS design tokens first, then shared UI components (Card, Badge, Input), then layouts (GuestLayout floating navbar, AppSidebar refinement), then pages in dependency order (public pages first, then authenticated, then settings/auth). Each task is self-contained and commits independently.

**Tech Stack:** Vue 3, Tailwind CSS v4 (CSS-first @theme), shadcn-vue (CVA variants), Inertia.js v2, vue-i18n, Lucide icons, ECharts

**Design Doc:** `docs/plans/2026-02-25-swiss-monochrome-redesign.md`

---

## Phase 1: Design System Tokens

### Task 1: Update CSS design tokens in app.css

**Files:**
- Modify: `resources/css/app.css`

**Step 1: Replace the `:root` light mode variables (lines 96-131)**

Replace the existing `:root` block with:

```css
:root {
    --background: #FFFFFF;
    --foreground: #09090B;
    --card: #FFFFFF;
    --card-foreground: #09090B;
    --popover: #FFFFFF;
    --popover-foreground: #09090B;
    --primary: #09090B;
    --primary-foreground: #FAFAFA;
    --secondary: #F4F4F5;
    --secondary-foreground: #09090B;
    --muted: #F4F4F5;
    --muted-foreground: #71717A;
    --accent: #F4F4F5;
    --accent-foreground: #09090B;
    --destructive: #DC2626;
    --destructive-foreground: #FAFAFA;
    --border: #E4E4E7;
    --input: #E4E4E7;
    --ring: #09090B;
    --radius: 0.375rem;

    /* Financial signal colors */
    --gain: #16A34A;
    --loss: #DC2626;

    /* Chart colors — monochrome scale */
    --chart-1: #09090B;
    --chart-2: #71717A;
    --chart-3: #A1A1AA;
    --chart-4: #D4D4D8;
    --chart-5: #E4E4E7;

    /* Sidebar */
    --sidebar-background: #FAFAFA;
    --sidebar-foreground: #3F3F46;
    --sidebar-primary: #09090B;
    --sidebar-primary-foreground: #FAFAFA;
    --sidebar-accent: #F4F4F5;
    --sidebar-accent-foreground: #09090B;
    --sidebar-border: #E4E4E7;
    --sidebar-ring: #09090B;
}
```

**Step 2: Replace the `.dark` block (lines 133-167)**

```css
.dark {
    --background: #09090B;
    --foreground: #FAFAFA;
    --card: #0A0A0B;
    --card-foreground: #FAFAFA;
    --popover: #0A0A0B;
    --popover-foreground: #FAFAFA;
    --primary: #FAFAFA;
    --primary-foreground: #09090B;
    --secondary: #27272A;
    --secondary-foreground: #FAFAFA;
    --muted: #18181B;
    --muted-foreground: #A1A1AA;
    --accent: #27272A;
    --accent-foreground: #FAFAFA;
    --destructive: #DC2626;
    --destructive-foreground: #FAFAFA;
    --border: #27272A;
    --input: #27272A;
    --ring: #FAFAFA;

    /* Financial signal colors */
    --gain: #16A34A;
    --loss: #DC2626;

    /* Chart colors — monochrome scale (inverted) */
    --chart-1: #FAFAFA;
    --chart-2: #A1A1AA;
    --chart-3: #71717A;
    --chart-4: #3F3F46;
    --chart-5: #27272A;

    /* Sidebar */
    --sidebar-background: #0A0A0B;
    --sidebar-foreground: #A1A1AA;
    --sidebar-primary: #FAFAFA;
    --sidebar-primary-foreground: #09090B;
    --sidebar-accent: #18181B;
    --sidebar-accent-foreground: #FAFAFA;
    --sidebar-border: #27272A;
    --sidebar-ring: #FAFAFA;
}
```

**Step 3: Add financial signal color utilities and tabular-nums to `@theme inline`**

After the existing `--color-sidebar-ring` line (line 65), add:

```css
    --color-gain: var(--gain);
    --color-loss: var(--loss);
```

**Step 4: Add utility classes after the RTL styles (after line 187)**

```css
/* Financial signal utilities */
.text-gain {
    color: var(--gain);
}
.text-loss {
    color: var(--loss);
}
.bg-gain-muted {
    background-color: color-mix(in srgb, var(--gain) 10%, transparent);
}
.bg-loss-muted {
    background-color: color-mix(in srgb, var(--loss) 10%, transparent);
}

/* Tabular numbers for financial data */
.tabular-nums {
    font-variant-numeric: tabular-nums;
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

**Step 5: Verify the build compiles**

Run: `npm run build 2>&1 | tail -20`
Expected: Build succeeds with no errors

**Step 6: Commit**

```bash
git add resources/css/app.css
git commit -m "style: update design tokens to Swiss Monochrome palette"
```

---

## Phase 2: Shared UI Components

### Task 2: Update Card component — remove shadow, use subtle border radius

**Files:**
- Modify: `resources/js/components/ui/card/Card.vue`

**Step 1: Update the Card class string**

In `Card.vue` line 15, replace:
```
'bg-card text-card-foreground flex flex-col gap-6 rounded-xl border py-6 shadow-sm'
```
with:
```
'bg-card text-card-foreground flex flex-col gap-6 rounded-md border border-border py-6'
```

Changes: `rounded-xl` → `rounded-md`, removed `shadow-sm`, added explicit `border-border`.

**Step 2: Commit**

```bash
git add resources/js/components/ui/card/Card.vue
git commit -m "style: update Card to Swiss Monochrome — no shadow, subtle radius"
```

### Task 3: Update Button variants — monochrome focus rings

**Files:**
- Modify: `resources/js/components/ui/button/index.ts`

**Step 1: Update the button variants**

Replace the entire `buttonVariants` definition with:

```typescript
export const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors duration-200 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*=\'size-\'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-1 focus-visible:ring-ring cursor-pointer',
  {
    variants: {
      variant: {
        default:
          'bg-foreground text-background hover:bg-foreground/90',
        destructive:
          'bg-destructive text-white hover:bg-destructive/90 focus-visible:ring-destructive',
        outline:
          'border border-border bg-background hover:bg-muted hover:text-foreground',
        secondary:
          'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        ghost:
          'hover:bg-muted hover:text-foreground',
        link: 'text-foreground underline-offset-4 hover:underline',
      },
      size: {
        default: 'h-9 px-4 py-2 has-[>svg]:px-3',
        sm: 'h-8 rounded-md gap-1.5 px-3 has-[>svg]:px-2.5',
        lg: 'h-10 rounded-md px-6 has-[>svg]:px-4',
        icon: 'size-9',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
)
```

Key changes:
- Default variant: `bg-foreground text-background` (black button in light, white in dark)
- Outline: explicit `border-border`, `hover:bg-muted`
- Ghost: `hover:bg-muted`
- Link: `text-foreground` not `text-primary`
- Base: simplified focus ring to `focus-visible:ring-1 focus-visible:ring-ring`
- Base: added `cursor-pointer`
- Base: `transition-colors duration-200` instead of `transition-all`
- Removed all `shadow-xs`

**Step 2: Commit**

```bash
git add resources/js/components/ui/button/index.ts
git commit -m "style: update Button variants to Swiss Monochrome"
```

### Task 4: Update Badge variants — smaller, monochrome

**Files:**
- Modify: `resources/js/components/ui/badge/index.ts`

**Step 1: Replace badge variants**

```typescript
export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-sm border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:ring-1 focus-visible:ring-ring transition-colors overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-foreground text-background",
        secondary:
          "border-transparent bg-secondary text-secondary-foreground",
        destructive:
          "border-transparent bg-destructive text-white",
        outline:
          "text-foreground border-border",
        gain:
          "border-transparent bg-gain-muted text-gain",
        loss:
          "border-transparent bg-loss-muted text-loss",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
```

Key changes:
- `rounded-md` → `rounded-sm`
- Added `gain` and `loss` variants for financial signals
- Simplified focus ring
- Removed hover effects from badges (badges are not interactive)

**Step 2: Update the BadgeVariants type**

The type export stays the same: `export type BadgeVariants = VariantProps<typeof badgeVariants>`

**Step 3: Commit**

```bash
git add resources/js/components/ui/badge/index.ts
git commit -m "style: update Badge to Swiss Monochrome with gain/loss variants"
```

### Task 5: Update Input component — monochrome focus

**Files:**
- Modify: `resources/js/components/ui/input/Input.vue`

**Step 1: Replace the input class string**

Replace the `:class` binding (lines 26-31) with:

```typescript
:class="cn(
  'file:text-foreground placeholder:text-muted-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base transition-colors duration-200 outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
  'focus-visible:border-foreground focus-visible:ring-1 focus-visible:ring-foreground',
  'aria-invalid:ring-destructive/20 aria-invalid:border-destructive',
  props.class,
)"
```

Key changes:
- Removed `dark:bg-input/30`, `shadow-xs`, `selection:bg-primary selection:text-primary-foreground`
- Focus: `focus-visible:border-foreground focus-visible:ring-1 focus-visible:ring-foreground` — monochrome focus
- Simplified transition to `transition-colors duration-200`

**Step 2: Commit**

```bash
git add resources/js/components/ui/input/Input.vue
git commit -m "style: update Input to Swiss Monochrome focus style"
```

### Task 6: Update ClickableTableRow — consistent hover

**Files:**
- Modify: `resources/js/components/ClickableTableRow.vue`

**Step 1: Read current file to confirm exact classes**

**Step 2: Update the `<tr>` element classes**

Ensure the row has these classes:
```
hover:bg-muted/50 transition-colors duration-150 cursor-pointer focus-visible:bg-muted/50 focus-visible:outline-none
```

The active state should use:
```
bg-muted
```

**Step 3: Commit**

```bash
git add resources/js/components/ClickableTableRow.vue
git commit -m "style: update ClickableTableRow hover to Swiss Monochrome"
```

### Task 7: Update FilterButtonBar — monochrome active pill

**Files:**
- Modify: `resources/js/components/FilterButtonBar.vue`

**Step 1: Update active/inactive button styling**

Active state should use variant `default` (which is now `bg-foreground text-background`).
Inactive state should use variant `outline`.

Ensure the buttons have `cursor-pointer`.

**Step 2: Commit**

```bash
git add resources/js/components/FilterButtonBar.vue
git commit -m "style: update FilterButtonBar to Swiss Monochrome active state"
```

### Task 8: Build and verify all component changes

**Step 1: Run build**

Run: `npm run build 2>&1 | tail -20`
Expected: No errors

**Step 2: Commit any missed formatting**

Run: `npx prettier --write resources/js/components/ui/button/index.ts resources/js/components/ui/badge/index.ts resources/js/components/ui/card/Card.vue resources/js/components/ui/input/Input.vue`

---

## Phase 3: Layouts

### Task 9: Redesign GuestLayout — floating transparent navbar

**Files:**
- Modify: `resources/js/layouts/GuestLayout.vue`

**Step 1: Update the `<header>` element**

Replace the header (line 45):
```html
<header class="border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
```

With a scroll-aware floating header. Add a `scrolled` ref:

```typescript
import { computed, ref, onMounted, onUnmounted } from 'vue';

const scrolled = ref(false);
const onScroll = () => { scrolled.value = window.scrollY > 50; };
onMounted(() => window.addEventListener('scroll', onScroll));
onUnmounted(() => window.removeEventListener('scroll', onScroll));
```

Update the `<header>`:
```html
<header
    class="fixed inset-x-0 top-0 z-30 transition-all duration-300"
    :class="scrolled ? 'bg-background/80 backdrop-blur-lg border-b border-border' : 'bg-transparent'"
>
```

**Step 2: Add `pt-16` to the main content**

Update `<main>`:
```html
<main class="pt-16">
```

**Step 3: Update the logo area**

Replace the logo link (lines 48-53):
```html
<LocalizedLink href="/" class="flex items-center gap-2">
    <div class="flex size-8 items-center justify-center rounded-md bg-foreground text-background">
        <AppLogoIcon class="size-5 fill-current" />
    </div>
    <span class="text-lg font-bold tracking-tight">Horin</span>
</LocalizedLink>
```

Changes: `bg-primary text-primary-foreground` → `bg-foreground text-background`, `font-semibold` → `font-bold tracking-tight`

**Step 4: Update auth button styling**

For the "Get Started" / "Dashboard" button, keep `default` variant (now black/white).
For the Login button (if we add one), use `ghost` variant.

**Step 5: Update footer**

Replace footer (line 206):
```html
<footer class="border-t border-border">
    <div class="mx-auto max-w-7xl px-6 py-8">
```

Remove `bg-muted/30`, change `px-4 py-6` → `px-6 py-8`.

**Step 6: Update inner container padding**

Change `px-4` → `px-6` in the header inner div (line 46):
```html
<div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6">
```

**Step 7: Commit**

```bash
git add resources/js/layouts/GuestLayout.vue
git commit -m "style: redesign GuestLayout with floating transparent navbar"
```

### Task 10: Refine AppSidebar — cleaner monochrome styling

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`

**Step 1: Review current styling — the sidebar uses ShadcnUI Sidebar primitives**

The sidebar component delegates styling to the Sidebar/SidebarContent/etc. primitives. The token changes from Task 1 already update the sidebar colors. Verify that the sidebar looks correct with the new tokens.

**Step 2: If needed, update the Sidebar primitive styles**

Check `resources/js/components/ui/sidebar/Sidebar.vue` for any hardcoded colors that conflict with the new tokens. Update if necessary.

**Step 3: Commit if changes were needed**

```bash
git add resources/js/components/AppSidebar.vue resources/js/components/ui/sidebar/
git commit -m "style: refine AppSidebar for Swiss Monochrome tokens"
```

### Task 11: Update AppContent — remove rounded-xl

**Files:**
- Modify: `resources/js/components/AppContent.vue`

**Step 1: Check the classes on AppContent**

If the component uses `rounded-xl`, change to `rounded-md` or remove rounding entirely for the content area.

**Step 2: Commit**

```bash
git add resources/js/components/AppContent.vue
git commit -m "style: simplify AppContent border radius"
```

### Task 12: Update auth layouts — cleaner card style

**Files:**
- Modify: `resources/js/layouts/auth/AuthSimpleLayout.vue`
- Modify: `resources/js/layouts/auth/AuthCardLayout.vue`
- Modify: `resources/js/layouts/auth/AuthSplitLayout.vue`

**Step 1: AuthSimpleLayout — ensure clean centered layout**

The simple layout should use `max-w-sm mx-auto`, no card wrapper. Verify logo uses `bg-foreground text-background`.

**Step 2: AuthCardLayout — update card styling**

Ensure the card uses `rounded-md border border-border` (no shadow). Background should be `bg-card`.

**Step 3: AuthSplitLayout — monochrome split**

The dark sidebar should use `bg-foreground` (which is `#09090B` in light mode). Text on it should be `text-background`.

**Step 4: Commit**

```bash
git add resources/js/layouts/auth/
git commit -m "style: update auth layouts to Swiss Monochrome"
```

### Task 13: Update settings layout

**Files:**
- Modify: `resources/js/layouts/settings/Layout.vue`

**Step 1: Ensure settings nav uses monochrome active states**

Active nav item: `bg-muted text-foreground font-medium`
Inactive: `text-muted-foreground hover:bg-muted/50 hover:text-foreground`

**Step 2: Commit**

```bash
git add resources/js/layouts/settings/Layout.vue
git commit -m "style: update settings layout to Swiss Monochrome"
```

### Task 14: Build verification

Run: `npm run build 2>&1 | tail -20`
Expected: No errors

---

## Phase 4: Public Pages

### Task 15: Redesign Welcome/Home page

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

**Step 1: Update hero section**

Replace the hero section (lines 206-227):
```html
<section class="pt-20 pb-12">
    <div class="mx-auto max-w-7xl px-6 text-center">
        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl" style="letter-spacing: -0.025em">
            {{ t('home.heroTitle') }}
        </h1>
        <p class="mt-3 text-lg text-muted-foreground">
            {{ t('home.heroSubtitle') }}
        </p>

        <!-- Search Bar -->
        <div class="relative mx-auto mt-8 max-w-xl">
            <Search v-if="!isSearching" class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
            <Loader2 v-else class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground animate-spin" />
            <Input
                v-model="searchQuery"
                type="text"
                :placeholder="t('home.searchPlaceholder')"
                class="h-12 ps-10 text-base"
            />
        </div>
    </div>
</section>
```

Changes: Remove `border-b border-border/40 bg-muted/30`, use `pt-20 pb-12`, change `px-4` → `px-6`.

**Step 2: Update market filter bar**

```html
<section class="border-b border-border">
    <div class="mx-auto max-w-7xl px-6 py-3">
```

Change `px-4 py-4` → `px-6 py-3`, remove `/40` from border opacity.

**Step 3: Update tab buttons to use monochrome indicator**

Replace the tab button active class (line 254):
```
activeTab === 'recommendations' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'
```

And the indicator span:
```html
<span v-if="activeTab === 'recommendations'" class="absolute bottom-0 inset-x-0 h-0.5 bg-foreground" />
```

Change `text-primary` → `text-foreground`, `bg-primary` → `bg-foreground` for all three tab buttons.

**Step 4: Update main content area padding**

```html
<div class="mx-auto max-w-7xl px-6 py-8">
```

Change `px-4` → `px-6`.

**Step 5: Add `tabular-nums` to all price/percentage displays**

In the predictions table `<td>` cells that display prices, add `tabular-nums` class to ensure column alignment.

**Step 6: Commit**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "style: redesign Welcome page to Swiss Monochrome"
```

### Task 16: Redesign Markets page

**Files:**
- Modify: `resources/js/pages/markets/Markets.vue`

**Step 1: Update hero section — same pattern as Welcome**

Remove background colors, use `pt-20 pb-12`, `px-6`.

**Step 2: Update market cards**

Ensure cards use: `border border-border rounded-md p-5 hover:bg-muted/30 cursor-pointer transition-colors duration-200`

No shadows.

**Step 3: Update sidebar cards — no shadows, consistent borders**

**Step 4: Commit**

```bash
git add resources/js/pages/markets/Markets.vue
git commit -m "style: redesign Markets page to Swiss Monochrome"
```

### Task 17: Redesign Market Detail page

**Files:**
- Modify: `resources/js/pages/markets/Show.vue`

**Step 1: Update stats cards — KPI style**

Value: `text-2xl font-bold tabular-nums`
Label: `text-xs text-muted-foreground uppercase tracking-wide`

**Step 2: Update asset table — consistent with Welcome table**

**Step 3: Commit**

```bash
git add resources/js/pages/markets/Show.vue
git commit -m "style: redesign Market Detail page to Swiss Monochrome"
```

### Task 18: Redesign Sectors and Sector Detail pages

**Files:**
- Modify: `resources/js/pages/sectors/Sectors.vue`
- Modify: `resources/js/pages/sectors/Show.vue`

**Step 1: Apply same patterns as Markets pages**

**Step 2: Commit**

```bash
git add resources/js/pages/sectors/
git commit -m "style: redesign Sectors pages to Swiss Monochrome"
```

### Task 19: Redesign Predictions page

**Files:**
- Modify: `resources/js/pages/Predictions.vue`

**Step 1: Update hero, filter bar, table — same patterns**

**Step 2: Ensure green/red gain indicators use `text-gain` / `text-loss` classes**

Replace `text-green-600 dark:text-green-400` → `text-gain`
Replace `text-red-600 dark:text-red-400` → `text-loss`

**Step 3: Update confidence colors to monochrome scale**

Instead of color-coded confidence, use font weight:
- High (>80%): `font-bold`
- Medium (50-80%): `font-medium`
- Low (<50%): `font-normal text-muted-foreground`

**Step 4: Add `tabular-nums` to all numeric columns**

**Step 5: Commit**

```bash
git add resources/js/pages/Predictions.vue
git commit -m "style: redesign Predictions page to Swiss Monochrome"
```

### Task 20: Redesign Recommendations page

**Files:**
- Modify: `resources/js/pages/Recommendations.vue`

**Step 1: Apply same patterns as Predictions**

**Step 2: Update recommendation badges**

Use the new Badge `gain` and `loss` variants:
- Strong Buy / Buy → `<Badge variant="gain">`
- Strong Sell / Sell → `<Badge variant="loss">`
- Hold → `<Badge variant="outline">`

**Step 3: Commit**

```bash
git add resources/js/pages/Recommendations.vue
git commit -m "style: redesign Recommendations page to Swiss Monochrome"
```

### Task 21: Redesign Asset Detail page

**Files:**
- Modify: `resources/js/pages/assets/Show.vue`

**Step 1: Update header — large symbol, clean layout**

Symbol: `text-2xl font-bold`
Price: `text-3xl font-bold tabular-nums`
Daily change: `text-gain` or `text-loss` with arrow icon

**Step 2: Update price details card — no shadow**

**Step 3: Update chart theme (if ECharts theme is configurable)**

Set chart grid lines to use border color. Area fill should be removed or use very subtle monochrome gradient.

**Step 4: Update sidebar stat displays — `tabular-nums`**

**Step 5: Commit**

```bash
git add resources/js/pages/assets/Show.vue
git commit -m "style: redesign Asset Detail page to Swiss Monochrome"
```

### Task 22: Redesign Search page

**Files:**
- Modify: `resources/js/pages/Search.vue`

**Step 1: Update search input — larger, centered**

`max-w-2xl mx-auto`, `h-12` input.

**Step 2: Update results table — consistent styling**

**Step 3: Commit**

```bash
git add resources/js/pages/Search.vue
git commit -m "style: redesign Search page to Swiss Monochrome"
```

### Task 23: Redesign News Index page

**Files:**
- Modify: `resources/js/pages/news/Index.vue`

**Step 1: Update hero, grid layout**

Cards grid: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`

**Step 2: Commit**

```bash
git add resources/js/pages/news/Index.vue
git commit -m "style: redesign News Index page to Swiss Monochrome"
```

### Task 24: Redesign News Detail page

**Files:**
- Modify: `resources/js/pages/news/Show.vue`

**Step 1: Update article layout**

`max-w-3xl mx-auto`, body text `leading-relaxed`.

**Step 2: Update badges, action buttons**

**Step 3: Commit**

```bash
git add resources/js/pages/news/Show.vue
git commit -m "style: redesign News Detail page to Swiss Monochrome"
```

### Task 25: Update News components

**Files:**
- Modify: `resources/js/components/news/NewsCard.vue`
- Modify: `resources/js/components/news/NewsListItem.vue`
- Modify: `resources/js/components/news/NewsFeatured.vue`
- Modify: `resources/js/components/news/NewsSentimentBadge.vue`
- Modify: `resources/js/components/news/NewsSection.vue`

**Step 1: NewsCard — remove card shadow, update hover**

`border border-border rounded-md overflow-hidden hover:bg-muted/30 cursor-pointer transition-colors duration-200`

Remove any gradient backgrounds or colored accents.

**Step 2: NewsSentimentBadge — use gain/loss badge variants**

Positive → `variant="gain"`, Negative → `variant="loss"`, Neutral → `variant="outline"`

**Step 3: NewsFeatured — monochrome styling**

Remove gradient backgrounds. Use `border border-border rounded-md`.

**Step 4: NewsListItem and NewsSection — consistent styling**

**Step 5: Commit**

```bash
git add resources/js/components/news/
git commit -m "style: update News components to Swiss Monochrome"
```

### Task 26: Redesign static pages (About, FAQ, Methodology, Privacy, Terms, Contact)

**Files:**
- Modify: `resources/js/pages/About.vue`
- Modify: `resources/js/pages/Faq.vue`
- Modify: `resources/js/pages/Methodology.vue`
- Modify: `resources/js/pages/Privacy.vue`
- Modify: `resources/js/pages/Terms.vue`
- Modify: `resources/js/pages/Contact.vue`

**Step 1: All static pages — consistent prose layout**

Wrap content in `max-w-3xl mx-auto py-12 px-6`.
H1: `text-3xl font-bold tracking-tight`
Body: `text-base leading-relaxed text-muted-foreground`

**Step 2: FAQ — update accordion styling**

Use `border-b border-border` dividers between items. Remove any colored backgrounds.

**Step 3: Methodology — update tables**

Table borders: `border border-border`, header: `bg-muted/50`.

**Step 4: Contact — clean form**

Input fields use updated Input component. Submit button uses default (black) variant.

**Step 5: Commit**

```bash
git add resources/js/pages/About.vue resources/js/pages/Faq.vue resources/js/pages/Methodology.vue resources/js/pages/Privacy.vue resources/js/pages/Terms.vue resources/js/pages/Contact.vue
git commit -m "style: redesign static pages to Swiss Monochrome"
```

---

## Phase 5: Authenticated Pages

### Task 27: Full Dashboard redesign

**Files:**
- Modify: `resources/js/pages/Dashboard.vue`

This is the biggest single task — the dashboard is currently empty placeholders.

**Step 1: Update the script section**

Replace the entire `<script setup>` with imports for the dashboard widgets:

```typescript
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import LocalizedLink from '@/components/LocalizedLink.vue';
import AssetDisplay from '@/components/AssetDisplay.vue';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
import {
    TrendingUp,
    TrendingDown,
    ArrowUpRight,
    ArrowDownRight,
    Bell,
    Eye,
    BarChart3,
    Activity,
} from 'lucide-vue-next';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';

const { t, locale } = useI18n();
const { formatGain, getConfidenceColor } = usePredictionFormatters();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('dashboard.title'),
        href: dashboard().url,
    },
]);

interface Props {
    stats?: {
        portfolioValue: number;
        dailyPnl: number;
        dailyPnlPercent: number;
        activeAlerts: number;
        watchlistCount: number;
    };
    watchlist?: Array<{
        id: number;
        symbol: string;
        name: string;
        marketCode: string;
        currentPrice: number;
        priceChange: number;
        priceChangePercent: number;
    }>;
    recentAlerts?: Array<{
        id: number;
        type: string;
        assetSymbol: string;
        message: string;
        triggeredAt: string;
    }>;
    recentPredictions?: Array<{
        id: number;
        asset: { symbol: string; name: string; market?: { code: string } };
        predictedPrice: number;
        expectedGainPercent: number;
        confidence: number;
        horizonLabel: string;
    }>;
    recentRecommendations?: Array<{
        id: number;
        asset: { symbol: string; name: string; market?: { code: string } };
        action: string;
        score: number;
    }>;
}

const props = defineProps<Props>();
const stats = computed(() => props.stats ?? {
    portfolioValue: 0, dailyPnl: 0, dailyPnlPercent: 0, activeAlerts: 0, watchlistCount: 0
});
</script>
```

**Step 2: Replace the template**

```html
<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Welcome -->
            <div>
                <h1 class="text-lg font-semibold">{{ t('dashboard.welcome') }}</h1>
                <p class="text-sm text-muted-foreground">{{ new Date().toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>

            <!-- KPI Row -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('dashboard.portfolioValue') }}</p>
                            <BarChart3 class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">{{ stats.portfolioValue.toLocaleString() }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('dashboard.dailyPnl') }}</p>
                            <Activity class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums" :class="stats.dailyPnl >= 0 ? 'text-gain' : 'text-loss'">
                            <span dir="ltr">{{ stats.dailyPnl >= 0 ? '+' : '' }}{{ stats.dailyPnl.toLocaleString() }}</span>
                        </p>
                        <p class="mt-1 text-xs tabular-nums" :class="stats.dailyPnlPercent >= 0 ? 'text-gain' : 'text-loss'">
                            <span dir="ltr">{{ stats.dailyPnlPercent >= 0 ? '+' : '' }}{{ stats.dailyPnlPercent.toFixed(2) }}%</span>
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('dashboard.activeAlerts') }}</p>
                            <Bell class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">{{ stats.activeAlerts }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('dashboard.watchlistCount') }}</p>
                            <Eye class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">{{ stats.watchlistCount }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Two Column: Watchlist + Recent Alerts -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Watchlist -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">{{ t('dashboard.watchlist') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred data="watchlist">
                            <template #fallback>
                                <div class="space-y-3">
                                    <Skeleton v-for="i in 5" :key="i" class="h-10 w-full rounded-sm" />
                                </div>
                            </template>
                            <div v-if="props.watchlist?.length" class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-border">
                                            <th class="pb-2 text-start text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('home.table.symbol') }}</th>
                                            <th class="pb-2 text-end text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('home.table.current') }}</th>
                                            <th class="pb-2 text-end text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ t('home.table.gainPercent') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in props.watchlist" :key="item.id" class="border-b border-border/50 last:border-0">
                                            <td class="py-2.5">
                                                <AssetDisplay :symbol="item.symbol" :market-code="item.marketCode" :show-name="false" :show-logo="false" size="sm" />
                                            </td>
                                            <td dir="ltr" class="py-2.5 text-end text-sm tabular-nums">{{ item.currentPrice.toFixed(2) }}</td>
                                            <td class="py-2.5 text-end">
                                                <span dir="ltr" class="inline-flex items-center gap-0.5 text-sm font-medium tabular-nums" :class="item.priceChangePercent >= 0 ? 'text-gain' : 'text-loss'">
                                                    <ArrowUpRight v-if="item.priceChangePercent >= 0" class="size-3.5" />
                                                    <ArrowDownRight v-else class="size-3.5" />
                                                    {{ Math.abs(item.priceChangePercent).toFixed(2) }}%
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="flex flex-col items-center py-8 text-center">
                                <Eye class="size-8 text-muted-foreground/50" />
                                <p class="mt-2 text-sm text-muted-foreground">{{ t('dashboard.emptyWatchlist') }}</p>
                            </div>
                        </Deferred>
                    </CardContent>
                </Card>

                <!-- Recent Alerts -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">{{ t('dashboard.recentAlerts') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred data="recentAlerts">
                            <template #fallback>
                                <div class="space-y-3">
                                    <Skeleton v-for="i in 5" :key="i" class="h-8 w-full rounded-sm" />
                                </div>
                            </template>
                            <div v-if="props.recentAlerts?.length" class="space-y-3">
                                <div v-for="alert in props.recentAlerts" :key="alert.id" class="flex items-start gap-3 text-sm">
                                    <Bell class="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">{{ alert.assetSymbol }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ alert.message }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center py-8 text-center">
                                <Bell class="size-8 text-muted-foreground/50" />
                                <p class="mt-2 text-sm text-muted-foreground">{{ t('dashboard.noRecentAlerts') }}</p>
                            </div>
                        </Deferred>
                    </CardContent>
                </Card>
            </div>

            <!-- Bottom: Recent Predictions + Recommendations -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">{{ t('dashboard.recentPredictions') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred data="recentPredictions">
                            <template #fallback>
                                <div class="space-y-3">
                                    <Skeleton v-for="i in 4" :key="i" class="h-8 w-full rounded-sm" />
                                </div>
                            </template>
                            <div v-if="props.recentPredictions?.length" class="space-y-2">
                                <LocalizedLink
                                    v-for="prediction in props.recentPredictions"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded-md px-2 py-1.5 transition-colors duration-150 hover:bg-muted/50"
                                >
                                    <AssetDisplay :symbol="prediction.asset.symbol" :market-code="prediction.asset.market?.code" :show-name="false" :show-logo="false" size="sm" />
                                    <span dir="ltr" class="text-sm font-medium tabular-nums" :class="prediction.expectedGainPercent >= 0 ? 'text-gain' : 'text-loss'">
                                        {{ formatGain(prediction.expectedGainPercent) }}
                                    </span>
                                </LocalizedLink>
                            </div>
                            <div v-else class="flex flex-col items-center py-8 text-center">
                                <TrendingUp class="size-8 text-muted-foreground/50" />
                                <p class="mt-2 text-sm text-muted-foreground">{{ t('dashboard.noPredictions') }}</p>
                            </div>
                        </Deferred>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">{{ t('dashboard.recentRecommendations') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Deferred data="recentRecommendations">
                            <template #fallback>
                                <div class="space-y-3">
                                    <Skeleton v-for="i in 4" :key="i" class="h-8 w-full rounded-sm" />
                                </div>
                            </template>
                            <div v-if="props.recentRecommendations?.length" class="space-y-2">
                                <LocalizedLink
                                    v-for="rec in props.recentRecommendations"
                                    :key="rec.id"
                                    :href="`/assets/${rec.asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded-md px-2 py-1.5 transition-colors duration-150 hover:bg-muted/50"
                                >
                                    <AssetDisplay :symbol="rec.asset.symbol" :market-code="rec.asset.market?.code" :show-name="false" :show-logo="false" size="sm" />
                                    <Badge :variant="rec.action === 'buy' || rec.action === 'strong_buy' ? 'gain' : rec.action === 'sell' || rec.action === 'strong_sell' ? 'loss' : 'outline'">
                                        {{ rec.action }}
                                    </Badge>
                                </LocalizedLink>
                            </div>
                            <div v-else class="flex flex-col items-center py-8 text-center">
                                <Activity class="size-8 text-muted-foreground/50" />
                                <p class="mt-2 text-sm text-muted-foreground">{{ t('dashboard.noRecommendations') }}</p>
                            </div>
                        </Deferred>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
```

**Step 3: Verify build**

Run: `npm run build 2>&1 | tail -20`

**Step 4: Commit**

```bash
git add resources/js/pages/Dashboard.vue
git commit -m "feat: full Dashboard redesign with KPIs, watchlist, alerts, and activity feeds"
```

### Task 28: Redesign Onboarding page

**Files:**
- Modify: `resources/js/pages/Onboarding.vue`
- Modify: `resources/js/components/onboarding/OnboardingStep1.vue`
- Modify: `resources/js/components/onboarding/OnboardingStep2.vue`
- Modify: `resources/js/components/onboarding/OnboardingStep3.vue`
- Modify: `resources/js/components/onboarding/OnboardingStep4.vue`

**Step 1: Update stepper styling**

Active step circle: `bg-foreground text-background`
Completed step: checkmark with `bg-foreground text-background`
Upcoming step: `border border-border` hollow circle

**Step 2: Update SelectableCard styling in step components**

Selected: `border-foreground border-2`
Unselected: `border-border hover:bg-muted/30`

Remove any emoji icons from investment goals if they're used as primary icons. Keep them only as supporting text.

**Step 3: Center content `max-w-lg mx-auto`**

**Step 4: Commit**

```bash
git add resources/js/pages/Onboarding.vue resources/js/components/onboarding/
git commit -m "style: redesign Onboarding flow to Swiss Monochrome"
```

---

## Phase 6: Alert Pages

### Task 29: Redesign Alert pages

**Files:**
- Modify: `resources/js/pages/Alerts/Index.vue`
- Modify: `resources/js/pages/Alerts/Create.vue`
- Modify: `resources/js/pages/Alerts/Edit.vue`
- Modify: `resources/js/pages/Alerts/Show.vue`
- Modify: `resources/js/pages/Alerts/History.vue`

**Step 1: Alerts Index — update card and badge styling**

Status badges:
- Active: `<Badge variant="default">` (bg-foreground text-background)
- Paused: `<Badge variant="secondary">`
- Triggered: `<Badge variant="gain">` or `<Badge variant="loss">`

"Create Alert" button: default variant (black/white solid).

**Step 2: Alerts Create — update stepper and form**

Same stepper style as Onboarding. Form inputs use updated Input component.

**Step 3: Alerts Edit — consistent with Create**

**Step 4: Alerts Show — monochrome detail cards**

**Step 5: Alerts History — clean table styling**

**Step 6: Commit**

```bash
git add resources/js/pages/Alerts/
git commit -m "style: redesign Alert pages to Swiss Monochrome"
```

---

## Phase 7: Auth Pages

### Task 30: Redesign auth pages

**Files:**
- Modify: `resources/js/pages/auth/TelegramAuth.vue`
- Modify: `resources/js/pages/auth/TwoFactorChallenge.vue`
- Modify: `resources/js/pages/auth/VerifyEmail.vue`
- Modify: `resources/js/pages/auth/VerifyPhone.vue`

**Step 1: TelegramAuth — clean centered loading state**

**Step 2: TwoFactorChallenge — monochrome PinInput**

Ensure PinInput uses monochrome focus: `focus:ring-foreground`.

**Step 3: VerifyEmail — clean card with mail icon**

**Step 4: VerifyPhone — clean card**

**Step 5: Commit**

```bash
git add resources/js/pages/auth/
git commit -m "style: redesign auth pages to Swiss Monochrome"
```

---

## Phase 8: Settings Pages

### Task 31: Redesign settings pages

**Files:**
- Modify: `resources/js/pages/settings/Profile.vue`
- Modify: `resources/js/pages/settings/Password.vue`
- Modify: `resources/js/pages/settings/Appearance.vue`
- Modify: `resources/js/pages/settings/TwoFactor.vue`
- Modify: `resources/js/pages/settings/Alerts.vue`
- Modify: `resources/js/pages/settings/MarketPreferences.vue`
- Modify: `resources/js/pages/settings/TradingProfile.vue`

**Step 1: Appearance — update theme cards**

Selected card: `border-foreground border-2 rounded-md`
Unselected: `border-border rounded-md hover:bg-muted/30`

**Step 2: TwoFactor — monochrome QR code card, recovery codes in monospace**

Recovery codes: `font-mono text-sm bg-muted p-4 rounded-md`

**Step 3: Alerts settings — clean toggle switches**

**Step 4: MarketPreferences — consistent checkbox/card styling**

**Step 5: TradingProfile — SelectableCard with monochrome active state**

Remove emoji icons from the display if they serve as primary UI icons. Keep text labels.

**Step 6: Profile, Password — already use form inputs, just verify monochrome styling**

**Step 7: Commit**

```bash
git add resources/js/pages/settings/
git commit -m "style: redesign settings pages to Swiss Monochrome"
```

---

## Phase 9: Supporting Components

### Task 32: Update remaining shared components

**Files:**
- Modify: `resources/js/components/RecommendationsTable.vue`
- Modify: `resources/js/components/RecommendationCard.vue`
- Modify: `resources/js/components/PredictionListItem.vue`
- Modify: `resources/js/components/SidebarStatCard.vue`
- Modify: `resources/js/components/Heading.vue`
- Modify: `resources/js/components/HeadingSmall.vue`
- Modify: `resources/js/components/Breadcrumbs.vue`
- Modify: `resources/js/components/PaginationControls.vue`

**Step 1: RecommendationsTable — use gain/loss text classes, tabular-nums**

Replace `text-green-*` / `text-red-*` with `text-gain` / `text-loss` throughout.

**Step 2: RecommendationCard — remove colored backgrounds, use monochrome**

**Step 3: PredictionListItem — consistent hover style**

**Step 4: SidebarStatCard — no shadow, border only**

**Step 5: Heading — tighten letter-spacing**

`text-xl font-semibold tracking-tight`

**Step 6: PaginationControls — monochrome buttons**

**Step 7: Commit**

```bash
git add resources/js/components/RecommendationsTable.vue resources/js/components/RecommendationCard.vue resources/js/components/PredictionListItem.vue resources/js/components/SidebarStatCard.vue resources/js/components/Heading.vue resources/js/components/HeadingSmall.vue resources/js/components/Breadcrumbs.vue resources/js/components/PaginationControls.vue
git commit -m "style: update shared components to Swiss Monochrome"
```

### Task 33: Global green/red replacement across all files

**Step 1: Search and replace all remaining hardcoded green/red classes**

Search for:
- `text-green-600` → `text-gain`
- `text-green-400` → `text-gain`
- `text-green-500` → `text-gain`
- `dark:text-green-400` → remove (text-gain handles both modes)
- `text-red-600` → `text-loss`
- `text-red-400` → `text-loss`
- `text-red-500` → `text-loss`
- `dark:text-red-400` → remove
- `bg-green-*` → `bg-gain-muted` where used for badges/backgrounds
- `bg-red-*` → `bg-loss-muted` where used for badges/backgrounds

Run grep to find all instances first:
```bash
grep -rn "text-green-\|text-red-\|bg-green-\|bg-red-" resources/js/ --include="*.vue" --include="*.ts"
```

**Step 2: Replace each instance, being careful of non-financial uses**

Some green/red may be used for non-financial purposes (success/error messages). Those can stay as `text-destructive` for errors. For success messages, use `text-gain`.

**Step 3: Commit**

```bash
git add resources/js/
git commit -m "style: replace hardcoded green/red with gain/loss utilities"
```

---

## Phase 10: Final Verification

### Task 34: Full build and lint

**Step 1: Run build**

```bash
npm run build 2>&1 | tail -30
```

Expected: No errors.

**Step 2: Run lint**

```bash
npm run lint 2>&1 | tail -30
```

Fix any lint errors.

**Step 3: Run Pint (PHP formatter)**

```bash
vendor/bin/pint --dirty
```

**Step 4: Run formatter**

```bash
npm run format
```

**Step 5: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: fix formatting after Swiss Monochrome redesign"
```

### Task 35: Run existing tests

**Step 1: Run test suite**

```bash
php artisan test 2>&1 | tail -30
```

Expected: All tests pass. The redesign is CSS/template-only — no logic changes should break tests.

**Step 2: Fix any test failures if they exist**

If tests reference specific CSS classes in assertions, update them.

**Step 3: Commit fixes if needed**

```bash
git add -A
git commit -m "test: fix tests after Swiss Monochrome redesign"
```

---

## Task Dependency Graph (DAG)

```
Phase 1: [Task 1] ─────────────────────────────────────────────────────────────┐
Phase 2: [Task 2] [Task 3] [Task 4] [Task 5] [Task 6] [Task 7] → [Task 8] ──┤
Phase 3: [Task 9] [Task 10] [Task 11] [Task 12] [Task 13] → [Task 14] ──────┤
Phase 4: [Task 15-26] (can be parallelized) ──────────────────────────────────┤
Phase 5: [Task 27] [Task 28] ─────────────────────────────────────────────────┤
Phase 6: [Task 29] ───────────────────────────────────────────────────────────┤
Phase 7: [Task 30] ───────────────────────────────────────────────────────────┤
Phase 8: [Task 31] ───────────────────────────────────────────────────────────┤
Phase 9: [Task 32] [Task 33] ─────────────────────────────────────────────────┤
Phase 10: [Task 34] [Task 35] ────────────────────────────────────────────────┘
```

**Critical path:** Task 1 → Task 3/4/5 → Task 8 → Task 9 → Task 15 → Task 27 → Task 33 → Task 34

**Parallelizable within phases:**
- Phase 2: Tasks 2-7 are independent
- Phase 3: Tasks 9-13 are independent
- Phase 4: Tasks 15-26 are independent (all pages can be updated in parallel)
- Phase 5-8: Tasks 27-31 are independent
