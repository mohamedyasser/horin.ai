Here is the **full sitemap** in a clean, simple table.
Only for the **Predictions-only website**.

---

# Sitemap Table (All Pages)

| Page             | URL Pattern          | Purpose                             | Entry Points                             |
| ---------------- | -------------------- | ----------------------------------- | ---------------------------------------- |
| Home             | `/`                  | Show global predictions overview    | Direct, header                           |
| Predictions List | `/predictions`       | Browse all predictions with filters | Header, home                             |
| Asset Detail     | `/asset/:symbol`     | Show prediction for one asset       | Predictions list, market, sector, search |
| Markets          | `/markets`           | List all markets                    | Header                                   |
| Market Detail    | `/markets/:marketId` | Predictions for one market          | Markets page                             |
| Sectors          | `/sectors`           | List all sectors                    | Header                                   |
| Sector Detail    | `/sectors/:sectorId` | Predictions for one sector          | Sectors page                             |
| Search           | `/search`            | Find asset by symbol or name        | Header, search bar                       |

---

# Optional Sub-Routes (minimal)

| Page                        | URL Pattern                   | Purpose                               |
| --------------------------- | ----------------------------- | ------------------------------------- |
| Market Predictions Filtered | `/markets/:marketId?sector=X` | Market predictions filtered by sector |
| Sector Predictions Filtered | `/sectors/:sectorId?market=Y` | Sector predictions filtered by market |
| Search With Query           | `/search?q=COMI`              | Pre-filled search                     |

---

# Navigation Map Table

| From          | To               | Trigger                 |
| ------------- | ---------------- | ----------------------- |
| Home          | Predictions List | Click “All Predictions” |
| Home          | Asset Detail     | Click asset row         |
| Predictions   | Asset Detail     | Click asset row         |
| Markets       | Market Detail    | Click market card       |
| Market Detail | Asset Detail     | Click asset row         |
| Sectors       | Sector Detail    | Click sector card       |
| Sector Detail | Asset Detail     | Click asset row         |
| Search        | Asset Detail     | Click search result row |

---

# Page Grouping Table

| Category    | Pages                                |
| ----------- | ------------------------------------ |
| Predictions | Home, Predictions List, Asset Detail |
| Markets     | Markets, Market Detail               |
| Sectors     | Sectors, Sector Detail               |
| Utilities   | Search                               |

---
