# Settings: Trading Profile & Market Preferences

## Overview

Add two new settings pages that allow users to update their onboarding choices after initial setup.

## Pages

### Trading Profile (`/settings/trading-profile`)

Allows users to update their trading-related preferences.

**Fields:**
- Experience Level: Beginner, Intermediate, Advanced, Expert
- Risk Level: Conservative, Moderate, Aggressive
- Investment Goal: Growth, Income, Preservation, Speculation
- Trading Style: Day Trading, Swing Trading, Position Trading, Buy & Hold

**UI Pattern:**
- Card-based selection (reuse onboarding card style)
- Two sections: "Experience & Risk Profile" and "Investment Goals & Style"
- Auto-save on card click with "Saved" indicator
- "Done" button to exit

### Market Preferences (`/settings/market-preferences`)

Allows users to update their market and sector interests.

**Fields:**
- Country: Single select with search
- Markets: Multi-select checkbox list (minimum 1)
- Sectors: Multi-select checkbox list (minimum 1)

**UI Pattern:**
- Country uses searchable dropdown, auto-saves on change
- Markets and Sectors use checkbox lists with manual "Save" button
- "Done" button to exit

## Navigation

Settings sidebar order:
1. Profile
2. Password
3. Two-Factor Auth
4. Trading Profile (new)
5. Market Preferences (new)
6. Appearance

## Routes

```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('settings/trading-profile', [TradingProfileController::class, 'edit'])->name('trading-profile.edit');
    Route::patch('settings/trading-profile', [TradingProfileController::class, 'update'])->name('trading-profile.update');

    Route::get('settings/market-preferences', [MarketPreferencesController::class, 'edit'])->name('market-preferences.edit');
    Route::patch('settings/market-preferences', [MarketPreferencesController::class, 'update'])->name('market-preferences.update');
});
```

## Files to Create

### Backend

**Controllers:**
- `app/Http/Controllers/Settings/TradingProfileController.php`
- `app/Http/Controllers/Settings/MarketPreferencesController.php`

**Form Requests:**
- `app/Http/Requests/Settings/TradingProfileUpdateRequest.php`
- `app/Http/Requests/Settings/MarketPreferencesUpdateRequest.php`

### Frontend

**Pages:**
- `resources/js/pages/settings/TradingProfile.vue`
- `resources/js/pages/settings/MarketPreferences.vue`

**Components:**
- `resources/js/components/MultiSelectList.vue` - Checkbox list with save button

## Files to Modify

- `resources/js/layouts/settings/Layout.vue` - Add navigation items
- `resources/js/i18n/ar.json` - Add Arabic translations
- `resources/js/i18n/en.json` - Add English translations
- `routes/web.php` - Add routes

## Translations

```json
{
  "settings": {
    "tradingProfile": {
      "title": "Trading Profile",
      "experienceRisk": {
        "heading": "Experience & Risk Profile",
        "description": "Update your experience level and risk tolerance"
      },
      "goalsStyle": {
        "heading": "Investment Goals & Style",
        "description": "Update your investment goals and trading style"
      },
      "saved": "Saved",
      "done": "Done"
    },
    "marketPreferences": {
      "title": "Market Preferences",
      "country": {
        "heading": "Your Country",
        "description": "Select your country"
      },
      "markets": {
        "heading": "Markets You Follow",
        "description": "Select the markets you want to follow"
      },
      "sectors": {
        "heading": "Sectors of Interest",
        "description": "Select the sectors you are interested in"
      },
      "save": "Save",
      "minimumRequired": "At least one selection is required",
      "done": "Done"
    }
  }
}
```

## Validation Rules

### TradingProfileUpdateRequest

```php
public function rules(): array
{
    return [
        'experience_level' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
        'risk_level' => ['sometimes', 'string', Rule::in(['conservative', 'moderate', 'aggressive'])],
        'investment_goal' => ['sometimes', 'string', Rule::in(['growth', 'income', 'preservation', 'speculation'])],
        'trading_style' => ['sometimes', 'string', Rule::in(['day_trading', 'swing_trading', 'position_trading', 'buy_and_hold'])],
    ];
}
```

### MarketPreferencesUpdateRequest

```php
public function rules(): array
{
    return [
        'country_id' => ['sometimes', 'exists:countries,id'],
        'markets' => ['sometimes', 'array', 'min:1'],
        'markets.*' => ['exists:markets,id'],
        'sectors' => ['sometimes', 'array', 'min:1'],
        'sectors.*' => ['exists:sectors,id'],
    ];
}
```

## Component Reuse

From onboarding:
- Card selection component style
- SearchableSelect for country dropdown
- Option configurations (experience levels, risk levels, etc.)

New:
- `MultiSelectList.vue` - Generic checkbox list with save button

## RTL Support

- Card grids use CSS Grid with `gap` (RTL-safe)
- Text alignment follows document direction
- Icons positioned using logical properties (start/end vs left/right)
