# F-176: Order Rating Prompt — Completed

## Summary
Clients can rate completed orders with 1-5 stars inline on the order detail page. Rating submission via Gale $action with real-time UI update (no page reload). Cook's overall rating is recalculated in tenant settings. Cook is notified via push+DB notification. Activity logged. One rating per order enforced. Rating persists after refund.

## Key Files
- app/Models/Rating.php
- app/Services/RatingService.php
- app/Http/Controllers/Client/RatingController.php
- app/Http/Requests/Client/StoreRatingRequest.php
- app/Notifications/RatingReceivedNotification.php
- database/migrations/2026_02_21_104957_create_ratings_table.php
- database/factories/RatingFactory.php
- tests/Unit/Client/OrderRatingPromptUnitTest.php
- resources/views/client/orders/show.blade.php

## Retries
- IMPLEMENT: 0
- REVIEW: 0
- TEST: 0

## Gate Results
All 3 gates PASSED on first attempt.
