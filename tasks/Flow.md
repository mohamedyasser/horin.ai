Here is the **full user flow** in table format for the Predictions-only website.

---

## 1. High Level Journey

| Step | Page                                                         | User Goal                      | User Action                                  | System Response                              |
| ---- | ------------------------------------------------------------ | ------------------------------ | -------------------------------------------- | -------------------------------------------- |
| 1    | Home `/`                                                     | Understand what the site does  | Lands on page                                | Show short intro and top predictions         |
| 2    | Home `/`                                                     | Find interesting prediction    | Uses search or market pills or scrolls table | Show filtered predictions list               |
| 3    | Predictions List `/predictions`                              | Compare multiple assets        | Adjusts filters and sorting                  | Update table with new results                |
| 4    | Asset Detail `/asset/:symbol`                                | Inspect one asset deeply       | Clicks on a row or symbol                    | Show full prediction detail for that asset   |
| 5    | Markets `/markets` or Sectors `/sectors`                     | Explore by market or sector    | Clicks navigation link                       | Show markets or sectors list                 |
| 6    | Market Detail `/markets/:id` or Sector Detail `/sectors/:id` | Explore focused group          | Clicks one market or sector                  | Show predictions only inside that scope      |
| 7    | Search `/search`                                             | Find a specific asset          | Types symbol or company name                 | Show matching assets with prediction summary |
| 8    | Asset Detail `/asset/:symbol`                                | Decide if prediction is useful | Views charts and metrics                     | Optionally goes back to list or another page |

---

## 2. Flow A — Fast Asset Lookup (Search First)

| Step | Page                       | User Intent                   | Action                                    | System Behavior                                  | Result                                |
| ---- | -------------------------- | ----------------------------- | ----------------------------------------- | ------------------------------------------------ | ------------------------------------- |
| A1   | Any page                   | Find a specific stock quickly | Click Search or focus search bar          | Focus input                                      | Ready to type                         |
| A2   | Search `/search`           | Search by symbol or name      | Type for example “COMI”                   | Call search API with query                       | Show table of matches                 |
| A3   | Search `/search`           | Choose correct asset          | Click on desired row                      | Navigate to `/asset/COMI`                        | Asset detail opens                    |
| A4   | Asset Detail `/asset/COMI` | Understand prediction         | Scroll through price, predictions, charts | Load price, prediction, indicators               | User sees future price and confidence |
| A5   | Asset Detail               | Compare with others           | Click Back to Predictions                 | Navigate to `/predictions` with previous filters | User continues browsing               |

---

## 3. Flow B — Discover via Market

| Step | Page                         | User Intent                       | Action                                             | System Behavior                                  | Result                                |
| ---- | ---------------------------- | --------------------------------- | -------------------------------------------------- | ------------------------------------------------ | ------------------------------------- |
| B1   | Home `/`                     | Focus on one exchange             | Click “Markets” in header                          | Navigate to `/markets`                           | Markets list opens                    |
| B2   | Markets `/markets`           | Pick a market                     | Scan list and click for example “EGX”              | Navigate to `/markets/:id` for EGX               | Market detail opens                   |
| B3   | Market Detail `/markets/:id` | Browse predictions in this market | Adjust filters such as sector, horizon, confidence | Fetch predictions with these filters             | Table updates with scoped predictions |
| B4   | Market Detail                | Inspect one asset                 | Click a prediction row                             | Navigate to `/asset/:symbol`                     | Asset detail opens                    |
| B5   | Asset Detail                 | Return to same market view        | Click “Back to Market”                             | Navigate to `/markets/:id` with previous filters | User remains in same context          |

---

## 4. Flow C — Discover via Sector

| Step | Page                         | User Intent                      | Action                                           | System Behavior                                | Result                        |
| ---- | ---------------------------- | -------------------------------- | ------------------------------------------------ | ---------------------------------------------- | ----------------------------- |
| C1   | Home `/`                     | Focus on one sector              | Click “Sectors” in header                        | Navigate to `/sectors`                         | Sectors list opens            |
| C2   | Sectors `/sectors`           | Pick a sector                    | Click for example “Banking”                      | Navigate to `/sectors/:id`                     | Sector detail opens           |
| C3   | Sector Detail `/sectors/:id` | Browse sector predictions        | Adjust market, horizon, gain, confidence filters | Fetch predictions in that sector               | Table updates                 |
| C4   | Sector Detail                | Inspect one asset                | Click a row                                      | Navigate to `/asset/:symbol`                   | Asset detail opens            |
| C5   | Asset Detail                 | Compare with other sector assets | Click “Back to Sector”                           | Navigate to `/sectors/:id` with filters intact | User continues in same sector |

---

## 5. Flow D — Explore Top Predictions from Home

| Step | Page         | User Intent                    | Action                                      | System Behavior                      | Result                             |
| ---- | ------------ | ------------------------------ | ------------------------------------------- | ------------------------------------ | ---------------------------------- |
| D1   | Home `/`     | Quickly see best opportunities | Look at “Top Predicted Gainers” section     | Top 5 assets fetched and shown       | User sees strongest moves          |
| D2   | Home `/`     | Open interesting asset         | Click a card for example “COMI”             | Navigate to `/asset/COMI`            | Asset detail opens                 |
| D3   | Asset Detail | Validate signal                | Check prediction, gain %, confidence, chart | Load model outputs and price history | User judges if it looks reasonable |
| D4   | Asset Detail | Find more like this            | Click “Back to All Predictions”             | Navigate to `/predictions`           | User can scan full list            |

---

## 6. Flow E — Full Predictions Browsing

| Step | Page                       | User Intent          | Action                                                     | System Behavior                                        | Result                           |
| ---- | -------------------------- | -------------------- | ---------------------------------------------------------- | ------------------------------------------------------ | -------------------------------- |
| E1   | Home `/`                   | View all predictions | Click “All Predictions” link                               | Navigate to `/predictions`                             | Predictions list opens           |
| E2   | Predictions `/predictions` | Narrow down          | Set filters such as Market = EGX, Horizon = 30d, Gain% > 5 | Send filter parameters to API                          | Table shows only matching assets |
| E3   | Predictions `/predictions` | Sort by importance   | Choose sorting “Highest Predicted Gain”                    | Reorder list client side or server side                | Biggest upside appears first     |
| E4   | Predictions `/predictions` | Inspect asset        | Click a row                                                | Navigate to `/asset/:symbol`                           | Asset detail opens               |
| E5   | Asset Detail               | Check another asset  | Click browser back or internal “Back to Predictions”       | Return to `/predictions` with same filters and sorting | User has consistent view         |

---

## 7. Flow F — Entry from External Link or Share

| Step | Page             | User Intent            | Action                           | System Behavior                                 | Result                       |
| ---- | ---------------- | ---------------------- | -------------------------------- | ----------------------------------------------- | ---------------------------- |
| F1   | Asset Detail URL | User opens shared link | Paste or click URL `/asset/COMI` | Backend fetches asset, price, prediction        | Page loads directly          |
| F2   | Asset Detail     | Explore more           | Click “Back to All Predictions”  | Navigate to `/predictions` with default filters | User joins normal flow       |
| F3   | Predictions      | Refine view            | Set filters and sorting          | Reload table                                    | User becomes regular browser |

---
