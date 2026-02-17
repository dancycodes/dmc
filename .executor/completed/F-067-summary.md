# F-067: Cook Card Component — Summary

## Overview
Reusable cook card component for the discovery grid. Features auto-cycling image carousel,
cook data fields (name, description, meal count, rating, town), clickable card navigation
to tenant domain, and placeholder handling for future features.

## Key Deliverables
- Complete rewrite of `_cook-card.blade.php` with image carousel, data fields, accessibility
- `Tenant::getUrl()` for centralized URL generation (subdomain or custom domain)
- Forward-compatible stubs for F-081 (cover images), F-108 (meals), F-176 (ratings), F-082 (towns)
- 23 unit tests
- Responsive dark/light mode, EN/FR translations

## Key Files
- `resources/views/discovery/_cook-card.blade.php`
- `app/Models/Tenant.php` (added getUrl())
- `tests/Unit/Discovery/CookCardUnitTest.php`

## Metrics
- Implement retries: 0
- Review retries: 0
- Test retries: 0
- Gate results: all PASS
