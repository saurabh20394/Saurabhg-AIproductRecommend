# Saurabhg_AIproductRecommend

AI product recommendations and personalization for Magento Open Source / Adobe Commerce 2.4.x.

The extension combines behavioral signals, semantic (vector) similarity, customer affinity, merchant rules,
inventory policy, segments, A/B testing and revenue attribution. **Magento remains the source of truth**:
AI output is treated as untrusted input and every recommendation is re-checked for status, visibility,
website, salability and exclusions before it is shown.

* No synchronous LLM calls on storefront requests. Embeddings, neighbors, popularity and relations are precomputed.
* Works out of the box with a built-in **local engine** (no external service). An optional **HTTP provider** connects
  any OpenAI-compatible embeddings API and an external recommendation API (for example FastAPI + pgvector).
* If anything fails, the storefront still works. Results fall back from cache to the last-good stored result, then to deterministic popular products.

Tested with Magento 2.4.9 and PHP 8.5. Declared compatibility: PHP 8.1 – 8.5.

---

## Installation

```bash
# app/code/Saurabhg/AIproductRecommend
bin/magento module:enable Saurabhg_AIproductRecommend
bin/magento setup:upgrade
bin/magento setup:di:compile            # production mode
bin/magento cache:flush
```

Then:

1. **Stores → Configuration → Saurabh Extensions → AI Product Recommendations → General → Enable = Yes**.
   The module ships disabled, so the storefront is not affected until you enable it. You can enable it per website or store view.
2. Build the catalog index and the initial signals:
   ```bash
   bin/magento airecommend:reindex      # embeddings + vector neighbors (+ catalog sync if enabled)
   bin/magento airecommend:calculate    # popularity, trending, co-purchase, co-view, analytics
   ```
3. Cron must run (`bin/magento cron:run`). Jobs are in the separate cron group `saurabhg_airecommend`.
   Queue consumers are started by cron through `cron_consumers_runner`. You can also run them yourself:
   ```bash
   bin/magento queue:consumers:start saurabhgAiRecommendEvent
   bin/magento queue:consumers:start saurabhgAiRecommendProductIndex
   ```
4. Nine starter units are created: home, category, product, cart and order success. Adjust them in
   **Marketing → AI Recommendations → Recommendation Units**.

### Uninstall / rollback

Disabling the module (`bin/magento module:disable Saurabhg_AIproductRecommend`) stops tracking and rendering.
It **does not delete data**: all `ai_*` tables stay in place. Deleting data needs an explicit administrator action. Either drop the
`ai_*` tables manually, or revert the product attribute with `bin/magento module:uninstall` for composer installs.
Recommendation data can always be rebuilt from catalog, order and behavior data with `airecommend:reindex` and `airecommend:calculate`.

---

## Architecture

```
Storefront (Luma / any theme)        Headless (PWA, React, mobile)
  placeholders (FPC-safe)              REST /V1/ai-recommendations/*
  js/tracker  js/recommendations       GraphQL aiRecommendations
        │ batched lazy render                │
        ▼                                    ▼
  airecommend/unit/render ──► RecommendationService ◄── ContextBuilder (validated input, session/token identity)
                                 │ cache (cache type "AI Recommendations", Redis if configured)
                                 │ remote AI API (optional, timeout + circuit breaker)
                                 │ local strategies: popular, trending, recently viewed, FBT, also viewed,
                                 │                   similar (vectors), recommended for you, complementary, hybrid, newest
                                 │ ProductDataLoader (one query: status, visibility, website, stock, price, categories)
                                 │ EligibilityFilter → RuleProcessor → CampaignProvider → Ranker → pins → fallback
                                 ▼
  events: js beacon / REST / GraphQL / server observers → validation → rate limit → message queue
          → EventProcessor (idempotent) → profiles, attribution (clicks → add-to-cart → orders)
  cron: stats, relations, embeddings fallback, analytics aggregation, precompute, retention
```

### Ranking formula

```
final = Σ w_f × feature_f + merchant_boost + campaign_boost − penalty      (features normalized 0..1)
features: behavior, similarity, affinity, popularity, trending, conversion, inventory, recency, newness, ai
```

* **Hybrid** and **Recommended For You** units use the configured weights.
* The other unit types use type profiles (`Model/Recommendation/Ranker::PROFILES`).
* A/B variants can override weights.
* Boosts are capped by *Maximum Total Boost*.
* Exclusions always win over boosts and pins.
* Rules run by priority, from high to low, then by rule ID. The first rule that pins a slot keeps it.

### Unit types

| Type | Signal | Cold start |
|---|---|---|
| popular | weighted views/carts/wishlists/orders in window (log-normalized) | newest products |
| trending | time-decayed activity (half-life) | popular |
| recently_viewed | profile recency | – |
| frequently_bought_together | co-purchase cosine (orders) | unit fallback |
| also_viewed | co-view cosine (visitor histories) | unit fallback |
| similar | precomputed vector neighbors → live vector search | same-category popular / newest |
| recommended_for_you | neighbors of recent/cart/purchased products + affinity categories | popular |
| complementary | co-purchased products from *other* categories + category map | unit fallback |
| hybrid | all of the above | trending / popular |
| newest | created_at | – |

### Embeddings

* **Local** (`local-hashing-256`): signed feature hashing of weighted unigrams and bigrams over name, brand, categories,
  descriptions and configured attributes. It is deterministic, has no dependencies, and catches lexical and attribute similarity.
  Use it for development, small catalogs and resilience fallback.
* **HTTP**: any OpenAI-compatible endpoint (`POST {base}/v1/embeddings {model, input[]}`). Examples: OpenAI, Ollama, vLLM,
  LiteLLM, an Azure gateway, or your FastAPI service.
* Products are re-embedded only when the embedding text or the model changes. Each product stores its content hash, model and version.
* Embeddings never contain customer data. Payloads contain product data only.
* Local vector search compares each product with products that share its categories, up to the
  *Max Vector Comparisons* setting. For very large catalogs, use the remote recommendation API backed by a vector DB.

---

## Configuration highlights

| Group | Notes |
|---|---|
| Behavior Tracking & Consent | Consent modes: Magento cookie restriction, custom CMP cookie, or none. No event and no visitor cookie are created before consent. The visitor ID is a random first-party cookie `aipr_vid`. |
| Privacy | Retention for events, analytics and anonymous profiles. Attribution window. Data-processing summary for your privacy documentation. |
| AI Provider | Provider, base URL, API key (encrypted), models, paths, timeouts, circuit breaker. **Validate Connection** button. |
| Hybrid Ranking Weights | Formula weights, max boost, diversity cap per category. |
| Inventory & Exclusions | Out-of-stock policy, low-stock threshold, global SKU / category / product-type exclusions. |

Every product also gets the attribute **Exclude from AI Recommendations** (store view scope).

### Secrets

Do not keep API keys in the database of shared environments. Set them per environment instead:

```bash
bin/magento config:set --lock-env saurabhg_airecommend/ai/api_key "<key>"
# or environment variables (Magento native):
CONFIG__DEFAULT__SAURABHG_AIRECOMMEND__AI__ENDPOINT=https://ai.example.com
CONFIG__DEFAULT__SAURABHG_AIRECOMMEND__AI__API_KEY=...
```

Keys are never sent to the browser, never logged (`var/log/ai_recommend.log` is sanitized), and never included in health output.

---

## REST API

Store scope comes from the URL (`/rest/{store_code}/V1/...`). Customer personalization requires a customer bearer token.
Customer IDs are never accepted as request parameters.

| Endpoint | Method | ACL | Purpose |
|---|---|---|---|
| `/V1/ai-recommendations` | GET | anonymous | `unitCode`, `type`, `productSku`, `categoryId`, `cartSkus[]`, `visitorId`, `pageType`, `limit`, `customerGroupId` (admin/integration tokens only) |
| `/V1/ai-recommendations/units/:code` | GET | anonymous | Recommendations of a configured unit |
| `/V1/ai-recommendations/similar/:sku` | GET | anonymous | Semantic similar products |
| `/V1/ai-recommendations/trending` | GET | anonymous | Trending products |
| `/V1/ai-recommendations/events` | POST | anonymous, rate limited | `{"events":[{event_type, event_id, sku, ...}]}` |
| `/V1/ai-recommendations/profile/mine` | GET | customer token | Own aggregate profile |
| `/V1/ai-recommendations/profile/:id` | GET / DELETE | `::profiles` / `::profiles_delete` | Profile access, erasure |
| `/V1/ai-recommendations/admin/reindex` | POST | `::reindex` | `{"productIds":[...], "storeIds":[...]}`. Leave both empty for a full reindex |
| `/V1/ai-recommendations/admin/health` | GET | `::monitor` | Dependency state |

Example response:

```json
{
  "unit_code": "pdp_similar", "title": "Similar Products", "type": "similar",
  "source": "local", "is_fallback": false, "request_id": "9f2c41d07a1b33e0",
  "tracking_token": "eyJ1Ijoi...a1b2",
  "items": [{"sku": "24-MB02", "product_id": 2, "name": "Fusion Backpack", "url": "https://.../fusion-backpack.html",
             "price": 59, "image_url": "https://.../m/b/mb02.jpg", "position": 1, "score": null,
             "reason_code": "similar", "reason_label": "Similar to this item"}]
}
```

`source` is one of: `local`, `remote`, `cache`, `last_good`, `fallback`, `holdout`, `disabled`, `not_applicable`, `not_found`.
`score` is `null` unless *Expose Scores in REST/GraphQL* is enabled.

Send `tracking_token` back with `recommendation_impression` and `recommendation_click` events. The token is HMAC-signed,
so clients cannot forge experiment attribution.

## GraphQL

```graphql
query {
  aiRecommendations(unitCode: "pdp_similar", productSku: "24-MB01", limit: 6) {
    unitCode source isFallback trackingToken
    products { sku position score reason reasonLabel product { name url_key price_range { minimum_price { final_price { value currency } } } } }
  }
}

mutation {
  aiRecommendationTrackEvents(events: [{eventType: "recommendation_click", sku: "24-MB02", position: 1,
                                        trackingToken: "...", visitorId: "v8a7c..."}]) { accepted rejected errors }
}
```

The customer comes only from the customer token. Unlike the illustrative `customerId` argument in the requirements, there is
**no `customerId` argument**, because it would let anyone read another customer's personalization.

## External recommendation API contract (optional)

`POST {base}{ranking path}` (default `/v1/recommendations`), with timeout *Storefront Request Timeout* (default 800 ms):

```json
{"store_id": "default", "visitor_id": "v...", "customer_id": "p_<pseudonymous hash>",
 "context": {"page_type": "product", "product_sku": "SKU-1001", "category_id": 42, "cart_skus": ["SKU-2001"], "customer_group_id": 1},
 "profile": {"top_categories": [42, 7], "top_brands": ["12"], "recent_skus": ["SKU-1001"]},
 "unit": {"code": "pdp_similar", "type": "similar", "limit": 18}}
```

Response: `{"source": "hybrid", "model_version": "ranker-v1", "recommendations": [{"sku": "SKU-1002", "score": 0.93, "reason_code": "semantic_similarity"}]}`.
The response is schema-validated and clamped. Unknown SKUs are dropped, and Magento rules always apply afterwards.
Catalog sync (optional) sends `POST {base}{catalog path} {"store_id": "...", "products": [...]}` with product data only.

---

## Storefront

* Placeholders are rendered into full-page-cached pages. Units load lazily when they come near the viewport, with **one batched request**
  (`airecommend/unit/render`, private, `no-store`).
* An impression is tracked when an item is ≥ 50 % visible. A click records the unit and position. Add-to-cart and purchases are tracked
  server-side (`checkout_cart_product_add_after`, `checkout_submit_all_after`) and attributed to the latest click within the window.
* Accessible carousel: a labelled region, keyboard arrows, prev/next buttons, focusable list, and reduced-motion support.
* Product links are canonical URLs without tracking parameters, so there are no duplicate URLs or crawl traps. The render endpoint sends `X-Robots-Tag: noindex`.
* CMS widget **AI Product Recommendations**. Hyvä and headless stores can use REST or GraphQL.

## Admin

**Marketing → AI Recommendations**:

* Dashboard: attributed revenue, CTR, conversion, AOV lift, per-unit table, daily trend, system health.
* Recommendation Units, Business Rules (conditions and actions), Campaigns, Customer Segments, A/B Experiments (results per variant).
* Simulator / Explain: runs the live pipeline for a sample context. It shows component scores, applied rules and campaigns,
  and the reason each excluded candidate was dropped. Use it to preview rules before you publish them.
* Reports: daily per-unit metrics with CSV export.
* Index & Queue Monitor: embedding progress per store, failures, dead-letter job log with retry / resolve, operational actions.

ACL suggestion for roles: *Merchandiser* gets units, rules, campaigns and the simulator. *Marketing* gets campaigns, segments, experiments and reports.
*Analyst* gets the dashboard and reports. *Developer* gets the monitor, reindex, the profiles API and configuration.

## Cron and queues

| Job | Schedule | Purpose |
|---|---|---|
| `saurabhg_airecommend_trending_calculation` | hourly :15 | popularity / trending / conversion |
| `saurabhg_airecommend_co_purchase_calculation` | daily 03:30 | frequently bought together + also viewed |
| `saurabhg_airecommend_embedding_generation` | every 10 min | index pending products (works without consumers) |
| `saurabhg_airecommend_catalog_sync` | daily 02:45 | reconcile products changed in the last 25 h |
| `saurabhg_airecommend_profile_aggregation` | hourly :40 | decay / prune active profiles |
| `saurabhg_airecommend_recommendation_precompute` | hourly :20 | warm context-free units |
| `saurabhg_airecommend_analytics_aggregation` | hourly :05 | daily aggregates |
| `saurabhg_airecommend_event_cleanup` | daily 04:15 | retention policy |

Queues use the default connection: MySQL (`db`), or AMQP when it is configured in `env.php`. The topics are
`saurabhg.airecommend.event` and `saurabhg.airecommend.product.index`. If publishing fails, events are processed inline.
Failed batches are stored in the error log (dead-letter) and can be retried from the monitor.

## Privacy

* Consent is checked on the client and on the server. Logged-in personalization can be turned off (*Personalize for Logged-in Customers*).
* Profiles hold aggregate features only: affinities, price band, recent product IDs, order counters.
* Deleting a customer removes the profile (FK cascade and observer), deletes merged visitor profiles and experiment assignments,
  and anonymizes events, impressions, clicks and conversions. Revenue aggregates are kept.
* `bin/magento airecommend:privacy:forget --customer=ID` or `--visitor=ID`. REST: `DELETE /V1/ai-recommendations/profile/:id`.
* Remote AI requests contain a pseudonymous HMAC customer key, never an e-mail, name or address.

## Development

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Saurabhg/AIproductRecommend/Test/Unit
vendor/bin/phpcs --standard=Magento2 app/code/Saurabhg/AIproductRecommend
bin/magento airecommend:health
```

Extension points (di.xml):

* add an embedding provider to `ProviderPool::embeddingProviders`
* replace `RankingProviderInterface`
* add or replace unit strategies in `StrategyPool::strategies`, which implement `StrategyInterface`

### Known limitations / next steps

* Integration, API-functional and performance tests are not included yet. Unit tests cover scoring, rules, validation, tokens,
  provider adapters and the vector math.
* The FastAPI / pgvector service is not part of this module. The HTTP contracts above are what it needs to implement.
* Analytics days are UTC. A/B results show lift but no significance test.
* Luma storefront components are included. For Hyvä, use the REST or GraphQL API, or build an Alpine template on top of the render endpoint.
