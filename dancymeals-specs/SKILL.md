---
name: dancymeals-specs
description: >
  Functional specification skill for DancyMeals — a multi-tenant marketplace platform for freelance
  cooks in Cameroon. This skill is the authoritative reference for all functional requirements of
  DancyMeals. Consult this skill whenever implementing, reviewing, testing, or discussing any feature
  of DancyMeals. It knows exactly how every feature should work and what the business rules are.
  Use it for: implementing features (read the feature file before coding), checking requirements
  (verify behavior against the spec), and understanding project scope (read the overview and feature
  catalog). Trigger when anyone mentions "DancyMeals", "dancymeals spec", "cook platform spec",
  or asks about DancyMeals requirements.
---

# DancyMeals — Functional Specification

> Version: 1.0
> Date: 2026-02-14
> Status: Approved
> Project Type: Multi-Tenant Food Marketplace Platform

## Project Overview

### Purpose & Vision
DancyMeals empowers freelance cooks in Cameroon to run professional food businesses through their
own branded websites. Each cook gets a custom subdomain and/or domain (e.g., latifa.cm,
powels.dancymeals.com) while DancyMeals handles the technology, payments, and infrastructure.
The platform replaces chaotic WhatsApp-based ordering with a structured system that includes
online ordering, prepaid mobile money payments, order tracking, and business analytics.

### Target Users
- **Freelance Cooks**: Independent food sellers who currently manage orders via WhatsApp. They get
  a branded website, order management, payment processing, and business insights.
- **Food Customers**: People in Cameroonian towns looking for home-cooked meals. They get a
  discovery platform, online ordering, mobile money payment, and order tracking.
- **Managers**: Cook-appointed helpers who assist with order and meal management within a tenant.
- **Platform Admins**: DancyMeals staff who manage tenants, monitor payments, resolve escalated
  complaints, and oversee platform health.

### Success Criteria
- Cooks can create meals, receive and manage orders, and get paid via mobile money
- Customers can discover cooks, order meals, pay with MTN MoMo or Orange Money, and track orders
- Platform collects configurable commission (default 10%) on all completed transactions
- Cross-domain authentication works seamlessly across all tenant and main domains
- 90%+ mobile usage supported with responsive-first PWA

### Scope Boundaries
**In scope**: Web PWA platform (multi-tenant), mobile money payments (Flutterwave v3), push/email/DB
notifications, cook-level promo codes, order messaging, wallet system, complaint resolution,
analytics with exports, estimated prep time, future date scheduling.

**Out of scope**: Native mobile/desktop apps, offline data sync, platform-wide promo codes, general
chat (only per-order messaging), cook-customizable order statuses, order modification after payment,
dark kitchen logistics, subscription/recurring orders, languages beyond English/French.

---

## Tech Stack

The mandatory technology stack for this project. See `references/tech-stack.md` for full details.

| Technology | Role |
|-----------|------|
| Laravel 12 | Backend framework |
| Gale | Server-driven reactivity (SSE + Alpine.js) |
| Alpine.js v3 | Client-side interactivity |
| PostgreSQL | Database (via Eloquent) |
| Tailwind CSS v4 | Styling |
| Flutterwave v3 | Payment gateway (MTN MoMo + Orange Money) |
| laravel-notification-channels/webpush | Push notifications |
| Spatie packages | Activity log, roles/permissions, honeypot |
| OpenStreetMap API | Neighbourhood location search |
| Intervention Image / Spatie Media Library | Image management |
| Laravel Herd | Local development (subdomain activation via `herd link`) |

AI Tooling: Laravel Boost MCP, Context7 MCP, Playwright MCP, Laravel Simplifier plugin,
Gale skill, UI Designer skill.

For complete tech stack details: `references/tech-stack.md`

---

## General Concepts

Non-negotiable standards that apply across all features. See `references/general-concepts.md`
for full details.

- **Multi-Tenancy**: Single database, subdomain/custom domain per cook, middleware-based tenant
  resolution, row-level isolation with global scopes
- **Auth**: One auth system across all domains, cross-domain session sharing, users are DancyMeals
  accounts usable on any domain
- **RBAC**: Spatie Permissions — granular permission-based access. Roles: super-admin, admin, cook,
  manager, client. One role per tenant per user (default: client).
- **UI**: 100% responsive (mobile-first), light/dark mode, theme switcher, tenant-customizable
  themes/fonts/radiuses, UI Designer skill for all designs
- **Reactivity**: Gale for all server-driven reactivity. No full page reloads. SPA navigation.
- **Localization**: English default + French. All text via `__('English text')`. DB columns:
  `column_en`/`column_fr` for translatable fields. Language switcher.
- **PWA**: Offline page only, install prompt, push notifications via webpush
- **Notifications**: Push + Database + Email (3 channels). Email for critical events.
- **Payments**: Flutterwave v3, MTN MoMo + Orange Money, configurable commission per cook
  (default 10%), split payment via subaccounts
- **Testing**: Playwright MCP first (100% pass), then Pest feature tests
- **Security**: Rate limiting, Spatie Honeypot, tenant data isolation, active status enforcement

For complete implementation standards: `references/general-concepts.md`

---

## Feature Catalog

This table is the heart of the specification. Every feature has a unique code that is consistent
across all references, discussions, and implementation tasks.

**How to use this table:**
- To implement a feature: read its reference file first
- To check precedence: ensure all listed features are `done` before starting
- To understand a module: read the features in sequence

| Code | Feature | Module | Type | Priority | Precedence | Reference |
|------|---------|--------|------|----------|-----------|-----------|
| F-001 | Laravel Project Scaffolding | Foundation | foundation | Must-have | — | `references/F-001.md` |
| F-002 | Database Configuration | Foundation | foundation | Must-have | F-001 | `references/F-002.md` |
| F-003 | Core Package Installation | Foundation | foundation | Must-have | F-002 | `references/F-003.md` |
| F-004 | Multi-Tenant Domain Resolution | Foundation | foundation | Must-have | F-003 | `references/F-004.md` |
| F-005 | Authentication Scaffolding | Foundation | foundation | Must-have | F-003 | `references/F-005.md` |
| F-006 | Role & Permission Seed Setup | Foundation | foundation | Must-have | F-003 | `references/F-006.md` |
| F-007 | Localization System Setup | Foundation | foundation | Must-have | F-003 | `references/F-007.md` |
| F-008 | Language Switcher Component | Foundation | foundation | Must-have | F-007 | `references/F-008.md` |
| F-009 | Theme System (Light/Dark Mode) | Foundation | foundation | Must-have | F-003 | `references/F-009.md` |
| F-010 | Theme Switcher Component | Foundation | foundation | Must-have | F-009 | `references/F-010.md` |
| F-011 | Tenant Theme Customization Infrastructure | Foundation | foundation | Must-have | F-009 | `references/F-011.md` |
| F-012 | PWA Configuration | Foundation | foundation | Must-have | F-003 | `references/F-012.md` |
| F-013 | PWA Install Prompt | Foundation | foundation | Must-have | F-012 | `references/F-013.md` |
| F-014 | Push Notification Infrastructure | Foundation | foundation | Must-have | F-012 | `references/F-014.md` |
| F-015 | Email Notification Infrastructure | Foundation | foundation | Must-have | F-003 | `references/F-015.md` |
| F-016 | Base Layout & Responsive Navigation | Foundation | foundation | Must-have | F-009, F-007 | `references/F-016.md` |
| F-017 | Activity Logging Setup | Foundation | foundation | Must-have | F-003 | `references/F-017.md` |
| F-018 | Honeypot Protection Setup | Foundation | foundation | Must-have | F-003 | `references/F-018.md` |
| F-019 | Rate Limiting Setup | Foundation | foundation | Must-have | F-003 | `references/F-019.md` |
| F-020 | Testing Infrastructure | Foundation | foundation | Must-have | F-003 | `references/F-020.md` |
| F-021 | User Registration Form | Auth | functional | Must-have | F-005, F-016, F-018 | `references/F-021.md` |
| F-022 | User Registration Submission | Auth | functional | Must-have | F-021, F-006 | `references/F-022.md` |
| F-023 | Email Verification Flow | Auth | functional | Must-have | F-022, F-015 | `references/F-023.md` |
| F-024 | User Login | Auth | functional | Must-have | F-005, F-016, F-018 | `references/F-024.md` |
| F-025 | User Logout | Auth | functional | Must-have | F-024 | `references/F-025.md` |
| F-026 | Password Reset Request | Auth | functional | Must-have | F-005, F-015 | `references/F-026.md` |
| F-027 | Password Reset Execution | Auth | functional | Must-have | F-026 | `references/F-027.md` |
| F-028 | Cross-Domain Session Sharing | Auth | functional | Must-have | F-004, F-024 | `references/F-028.md` |
| F-029 | Active Status Enforcement | Auth | functional | Must-have | F-006, F-024 | `references/F-029.md` |
| F-030 | Profile View | Auth | functional | Must-have | F-024 | `references/F-030.md` |
| F-031 | Profile Photo Upload | Auth | functional | Should-have | F-030 | `references/F-031.md` |
| F-032 | Profile Basic Info Edit | Auth | functional | Must-have | F-030 | `references/F-032.md` |
| F-033 | Add Delivery Address | User Settings | functional | Must-have | F-030 | `references/F-033.md` |
| F-034 | Delivery Address List | User Settings | functional | Must-have | F-033 | `references/F-034.md` |
| F-035 | Edit Delivery Address | User Settings | functional | Must-have | F-034 | `references/F-035.md` |
| F-036 | Delete Delivery Address | User Settings | functional | Must-have | F-034 | `references/F-036.md` |
| F-037 | Add Payment Method | User Settings | functional | Must-have | F-030 | `references/F-037.md` |
| F-038 | Payment Method List | User Settings | functional | Must-have | F-037 | `references/F-038.md` |
| F-039 | Edit Payment Method | User Settings | functional | Must-have | F-038 | `references/F-039.md` |
| F-040 | Delete Payment Method | User Settings | functional | Must-have | F-038 | `references/F-040.md` |
| F-041 | Notification Preferences Management | User Settings | functional | Should-have | F-030, F-014 | `references/F-041.md` |
| F-042 | Language Preference Setting | User Settings | functional | Must-have | F-030, F-008 | `references/F-042.md` |
| F-043 | Admin Panel Layout & Access Control | Admin | functional | Must-have | F-006, F-016 | `references/F-043.md` |
| F-044 | Super-Admin Creation Artisan Command | Admin | functional | Must-have | F-006 | `references/F-044.md` |
| F-045 | Tenant Creation Form | Admin | functional | Must-have | F-043 | `references/F-045.md` |
| F-046 | Tenant List & Search View | Admin | functional | Must-have | F-043 | `references/F-046.md` |
| F-047 | Tenant Detail View | Admin | functional | Must-have | F-046 | `references/F-047.md` |
| F-048 | Tenant Edit & Status Toggle | Admin | functional | Must-have | F-047 | `references/F-048.md` |
| F-049 | Cook Account Assignment to Tenant | Admin | functional | Must-have | F-045 | `references/F-049.md` |
| F-050 | User Management List & Search | Admin | functional | Must-have | F-043 | `references/F-050.md` |
| F-051 | User Detail View & Status Toggle | Admin | functional | Must-have | F-050 | `references/F-051.md` |
| F-052 | Create Role | Admin | functional | Must-have | F-043, F-006 | `references/F-052.md` |
| F-053 | Role List View | Admin | functional | Must-have | F-052 | `references/F-053.md` |
| F-054 | Edit Role | Admin | functional | Must-have | F-053 | `references/F-054.md` |
| F-055 | Delete Role | Admin | functional | Must-have | F-053 | `references/F-055.md` |
| F-056 | Permission Assignment to Roles | Admin | functional | Must-have | F-052 | `references/F-056.md` |
| F-057 | Platform Analytics Dashboard | Admin | functional | Should-have | F-043 | `references/F-057.md` |
| F-058 | Financial Reports & Export | Admin | functional | Should-have | F-057 | `references/F-058.md` |
| F-059 | Payment Monitoring View | Admin | functional | Must-have | F-043 | `references/F-059.md` |
| F-060 | Complaint Escalation Queue | Admin | functional | Must-have | F-043 | `references/F-060.md` |
| F-061 | Admin Complaint Resolution | Admin | functional | Must-have | F-060 | `references/F-061.md` |
| F-062 | Commission Configuration per Cook | Admin | functional | Must-have | F-047 | `references/F-062.md` |
| F-063 | Platform Settings Management | Admin | functional | Must-have | F-043 | `references/F-063.md` |
| F-064 | Activity Log Viewer | Admin | functional | Should-have | F-043, F-017 | `references/F-064.md` |
| F-065 | Manual Payout Task Queue | Admin | functional | Must-have | F-043 | `references/F-065.md` |
| F-066 | Discovery Page Layout | Discovery | functional | Must-have | F-016, F-004 | `references/F-066.md` |
| F-067 | Cook Card Component | Discovery | functional | Must-have | F-066 | `references/F-067.md` |
| F-068 | Discovery Search | Discovery | functional | Must-have | F-066 | `references/F-068.md` |
| F-069 | Discovery Filters | Discovery | functional | Must-have | F-066 | `references/F-069.md` |
| F-070 | Discovery Sort Options | Discovery | functional | Should-have | F-066 | `references/F-070.md` |
| F-071 | Cook Setup Wizard Shell | Cook Onboarding | functional | Must-have | F-049, F-076 | `references/F-071.md` |
| F-072 | Brand Info Step | Cook Onboarding | functional | Must-have | F-071 | `references/F-072.md` |
| F-073 | Cover Images Step | Cook Onboarding | functional | Must-have | F-071 | `references/F-073.md` |
| F-074 | Delivery Areas Step | Cook Onboarding | functional | Must-have | F-071 | `references/F-074.md` |
| F-075 | Schedule & First Meal Step | Cook Onboarding | functional | Must-have | F-071 | `references/F-075.md` |
| F-076 | Cook Dashboard Layout & Navigation | Cook Dashboard | functional | Must-have | F-004, F-006, F-016 | `references/F-076.md` |
| F-077 | Cook Dashboard Home | Cook Dashboard | functional | Must-have | F-076 | `references/F-077.md` |
| F-078 | Cook Quick Actions Panel | Cook Dashboard | functional | Should-have | F-077 | `references/F-078.md` |
| F-079 | Cook Brand Profile View | Cook Profile | functional | Must-have | F-076 | `references/F-079.md` |
| F-080 | Cook Brand Profile Edit | Cook Profile | functional | Must-have | F-079 | `references/F-080.md` |
| F-081 | Cook Cover Images Management | Cook Profile | functional | Must-have | F-079 | `references/F-081.md` |
| F-082 | Add Town | Location | functional | Must-have | F-076 | `references/F-082.md` |
| F-083 | Town List View | Location | functional | Must-have | F-082 | `references/F-083.md` |
| F-084 | Edit Town | Location | functional | Must-have | F-083 | `references/F-084.md` |
| F-085 | Delete Town | Location | functional | Must-have | F-083 | `references/F-085.md` |
| F-086 | Add Quarter | Location | functional | Must-have | F-082 | `references/F-086.md` |
| F-087 | Quarter List View | Location | functional | Must-have | F-086 | `references/F-087.md` |
| F-088 | Edit Quarter | Location | functional | Must-have | F-087 | `references/F-088.md` |
| F-089 | Delete Quarter | Location | functional | Must-have | F-087 | `references/F-089.md` |
| F-090 | Quarter Group Creation | Location | functional | Must-have | F-086 | `references/F-090.md` |
| F-091 | Delivery Fee Configuration | Location | functional | Must-have | F-086 | `references/F-091.md` |
| F-092 | Add Pickup Location | Location | functional | Must-have | F-076 | `references/F-092.md` |
| F-093 | Pickup Location List View | Location | functional | Must-have | F-092 | `references/F-093.md` |
| F-094 | Edit Pickup Location | Location | functional | Must-have | F-093 | `references/F-094.md` |
| F-095 | Delete Pickup Location | Location | functional | Must-have | F-093 | `references/F-095.md` |
| F-096 | Meal-Specific Location Override | Location | functional | Must-have | F-091, F-108 | `references/F-096.md` |
| F-097 | OpenStreetMap Neighbourhood Search | Location | functional | Must-have | F-003 | `references/F-097.md` |
| F-098 | Cook Day Schedule Creation | Schedule | functional | Must-have | F-076 | `references/F-098.md` |
| F-099 | Order Time Interval Configuration | Schedule | functional | Must-have | F-098 | `references/F-099.md` |
| F-100 | Delivery/Pickup Time Interval Config | Schedule | functional | Must-have | F-098 | `references/F-100.md` |
| F-101 | Create Schedule Template | Schedule | functional | Must-have | F-098 | `references/F-101.md` |
| F-102 | Schedule Template List View | Schedule | functional | Must-have | F-101 | `references/F-102.md` |
| F-103 | Edit Schedule Template | Schedule | functional | Must-have | F-102 | `references/F-103.md` |
| F-104 | Delete Schedule Template | Schedule | functional | Must-have | F-102 | `references/F-104.md` |
| F-105 | Schedule Template Application to Days | Schedule | functional | Must-have | F-101 | `references/F-105.md` |
| F-106 | Meal Schedule Override | Schedule | functional | Must-have | F-098, F-108 | `references/F-106.md` |
| F-107 | Schedule Validation Rules | Schedule | edge-case | Must-have | F-098 | `references/F-107.md` |
| F-108 | Meal Creation Form | Meal | functional | Must-have | F-076 | `references/F-108.md` |
| F-109 | Meal Image Upload & Carousel | Meal | functional | Must-have | F-108 | `references/F-109.md` |
| F-110 | Meal Edit | Meal | functional | Must-have | F-108 | `references/F-110.md` |
| F-111 | Meal Delete | Meal | functional | Must-have | F-108 | `references/F-111.md` |
| F-112 | Meal Status Toggle (Draft/Live) | Meal | functional | Must-have | F-108 | `references/F-112.md` |
| F-113 | Meal Availability Toggle | Meal | functional | Must-have | F-108 | `references/F-113.md` |
| F-114 | Meal Tag Assignment | Meal | functional | Must-have | F-108, F-115 | `references/F-114.md` |
| F-115 | Cook Tag Management | Meal | functional | Must-have | F-076 | `references/F-115.md` |
| F-116 | Meal List View (Cook Dashboard) | Meal | functional | Must-have | F-108 | `references/F-116.md` |
| F-117 | Meal Estimated Preparation Time | Meal | functional | Should-have | F-108 | `references/F-117.md` |
| F-118 | Meal Component Creation | Meal Component | functional | Must-have | F-108 | `references/F-118.md` |
| F-119 | Meal Component Edit | Meal Component | functional | Must-have | F-118 | `references/F-119.md` |
| F-120 | Meal Component Delete | Meal Component | functional | Must-have | F-118 | `references/F-120.md` |
| F-121 | Custom Selling Unit Definition | Meal Component | functional | Must-have | F-076 | `references/F-121.md` |
| F-122 | Meal Component Requirement Rules | Meal Component | functional | Must-have | F-118 | `references/F-122.md` |
| F-123 | Meal Component Availability Toggle | Meal Component | functional | Must-have | F-118 | `references/F-123.md` |
| F-124 | Meal Component Quantity Settings | Meal Component | functional | Must-have | F-118 | `references/F-124.md` |
| F-125 | Meal Component List View | Meal Component | functional | Must-have | F-118 | `references/F-125.md` |
| F-126 | Tenant Landing Page Layout | Tenant Landing | functional | Must-have | F-004, F-016, F-011 | `references/F-126.md` |
| F-127 | Cook Brand Header Section | Tenant Landing | functional | Must-have | F-126 | `references/F-127.md` |
| F-128 | Available Meals Grid Display | Tenant Landing | functional | Must-have | F-126, F-108 | `references/F-128.md` |
| F-129 | Meal Detail View | Tenant Landing | functional | Must-have | F-128 | `references/F-129.md` |
| F-130 | Ratings Summary Display | Tenant Landing | functional | Should-have | F-126, F-176 | `references/F-130.md` |
| F-131 | Testimonials Showcase Section | Tenant Landing | functional | Should-have | F-126, F-182 | `references/F-131.md` |
| F-132 | Schedule & Availability Display | Tenant Landing | functional | Must-have | F-126, F-098 | `references/F-132.md` |
| F-133 | Delivery Areas & Fees Display | Tenant Landing | functional | Must-have | F-126, F-091 | `references/F-133.md` |
| F-134 | WhatsApp Contact & Social Links | Tenant Landing | functional | Must-have | F-126 | `references/F-134.md` |
| F-135 | Meal Search Bar | Meal Search | functional | Must-have | F-128 | `references/F-135.md` |
| F-136 | Meal Filters | Meal Search | functional | Must-have | F-128 | `references/F-136.md` |
| F-137 | Meal Sort Options | Meal Search | functional | Should-have | F-128 | `references/F-137.md` |
| F-138 | Meal Component Selection & Cart Add | Ordering | functional | Must-have | F-129, F-118 | `references/F-138.md` |
| F-139 | Order Cart Management | Ordering | functional | Must-have | F-138 | `references/F-139.md` |
| F-140 | Delivery/Pickup Choice Selection | Ordering | functional | Must-have | F-139 | `references/F-140.md` |
| F-141 | Delivery Location Selection | Ordering | functional | Must-have | F-140, F-097 | `references/F-141.md` |
| F-142 | Pickup Location Selection | Ordering | functional | Must-have | F-140, F-092 | `references/F-142.md` |
| F-143 | Order Phone Number | Ordering | functional | Must-have | F-139 | `references/F-143.md` |
| F-144 | Minimum Order Amount Validation | Ordering | edge-case | Must-have | F-139, F-213 | `references/F-144.md` |
| F-145 | Delivery Fee Calculation | Ordering | functional | Must-have | F-141 | `references/F-145.md` |
| F-146 | Order Total Calculation & Summary | Ordering | functional | Must-have | F-139 | `references/F-146.md` |
| F-147 | Location Not Available Flow | Ordering | edge-case | Must-have | F-141 | `references/F-147.md` |
| F-148 | Order Scheduling for Future Date | Ordering | functional | Should-have | F-139, F-098 | `references/F-148.md` |
| F-149 | Payment Method Selection | Payment | functional | Must-have | F-146 | `references/F-149.md` |
| F-150 | Flutterwave Payment Initiation | Payment | functional | Must-have | F-149 | `references/F-150.md` |
| F-151 | Payment Webhook Handling | Payment | functional | Must-have | F-150 | `references/F-151.md` |
| F-152 | Payment Retry with Timeout | Payment | edge-case | Must-have | F-150 | `references/F-152.md` |
| F-153 | Wallet Balance Payment | Payment | functional | Must-have | F-149, F-166 | `references/F-153.md` |
| F-154 | Payment Receipt & Confirmation | Payment | functional | Must-have | F-151 | `references/F-154.md` |
| F-155 | Cook Order List View | Order Mgmt | functional | Must-have | F-076, F-151 | `references/F-155.md` |
| F-156 | Cook Order Detail View | Order Mgmt | functional | Must-have | F-155 | `references/F-156.md` |
| F-157 | Single Order Status Update | Order Mgmt | functional | Must-have | F-156 | `references/F-157.md` |
| F-158 | Mass Order Status Update | Order Mgmt | functional | Must-have | F-155 | `references/F-158.md` |
| F-159 | Order Status Transition Validation | Order Mgmt | edge-case | Must-have | F-157 | `references/F-159.md` |
| F-160 | Client Order List | Order Tracking | functional | Must-have | F-024, F-151 | `references/F-160.md` |
| F-161 | Client Order Detail & Status Tracking | Order Tracking | functional | Must-have | F-160 | `references/F-161.md` |
| F-162 | Order Cancellation | Order Tracking | functional | Must-have | F-161, F-212 | `references/F-162.md` |
| F-163 | Order Cancellation Refund Processing | Order Tracking | functional | Must-have | F-162, F-167 | `references/F-163.md` |
| F-164 | Client Transaction History | Client Financial | functional | Must-have | F-024, F-151 | `references/F-164.md` |
| F-165 | Transaction Detail View | Client Financial | functional | Must-have | F-164 | `references/F-165.md` |
| F-166 | Client Wallet Dashboard | Wallet | functional | Must-have | F-024 | `references/F-166.md` |
| F-167 | Client Wallet Refund Credit | Wallet | functional | Must-have | F-166 | `references/F-167.md` |
| F-168 | Client Wallet Payment for Orders | Wallet | functional | Must-have | F-166, F-063 | `references/F-168.md` |
| F-169 | Cook Wallet Dashboard | Wallet | functional | Must-have | F-076, F-151 | `references/F-169.md` |
| F-170 | Cook Wallet Transaction History | Wallet | functional | Must-have | F-169 | `references/F-170.md` |
| F-171 | Withdrawable Timer Logic | Wallet | functional | Must-have | F-169 | `references/F-171.md` |
| F-172 | Cook Withdrawal Request | Wallet | functional | Must-have | F-169 | `references/F-172.md` |
| F-173 | Flutterwave Transfer Execution | Wallet | functional | Must-have | F-172 | `references/F-173.md` |
| F-174 | Cook Auto-Deduction for Refunds | Wallet | functional | Must-have | F-169 | `references/F-174.md` |
| F-175 | Commission Deduction on Completion | Wallet | functional | Must-have | F-169, F-062 | `references/F-175.md` |
| F-176 | Order Rating Prompt | Rating | functional | Must-have | F-161 | `references/F-176.md` |
| F-177 | Order Review Text Submission | Rating | functional | Must-have | F-176 | `references/F-177.md` |
| F-178 | Rating & Review Display on Meal | Rating | functional | Must-have | F-176 | `references/F-178.md` |
| F-179 | Cook Overall Rating Calculation | Rating | functional | Must-have | F-176 | `references/F-179.md` |
| F-180 | Testimonial Submission Form | Testimonial | functional | Should-have | F-024, F-076 | `references/F-180.md` |
| F-181 | Cook Testimonial Moderation | Testimonial | functional | Should-have | F-180 | `references/F-181.md` |
| F-182 | Approved Testimonials Display | Testimonial | functional | Should-have | F-181 | `references/F-182.md` |
| F-183 | Client Complaint Submission | Complaint | functional | Must-have | F-161 | `references/F-183.md` |
| F-184 | Cook/Manager Complaint Response | Complaint | functional | Must-have | F-183, F-076 | `references/F-184.md` |
| F-185 | Complaint Auto-Escalation | Complaint | functional | Must-have | F-184 | `references/F-185.md` |
| F-186 | Complaint-Triggered Payment Block | Complaint | functional | Must-have | F-183, F-169 | `references/F-186.md` |
| F-187 | Complaint Status Tracking | Complaint | functional | Must-have | F-183 | `references/F-187.md` |
| F-188 | Order Message Thread View | Messaging | functional | Should-have | F-161 | `references/F-188.md` |
| F-189 | Message Send | Messaging | functional | Should-have | F-188 | `references/F-189.md` |
| F-190 | Message Notification | Messaging | functional | Should-have | F-189, F-014 | `references/F-190.md` |
| F-191 | Order Creation Notifications | Notification | functional | Must-have | F-014, F-015, F-151 | `references/F-191.md` |
| F-192 | Order Status Update Notifications | Notification | functional | Must-have | F-014, F-015, F-157 | `references/F-192.md` |
| F-193 | Complaint Notifications | Notification | functional | Must-have | F-014, F-015, F-183 | `references/F-193.md` |
| F-194 | Payment Notifications | Notification | functional | Must-have | F-014, F-015, F-151 | `references/F-194.md` |
| F-195 | System Announcement Notifications | Notification | functional | Should-have | F-014, F-015, F-043 | `references/F-195.md` |
| F-196 | Favorite Cook Toggle | Favorites | functional | Should-have | F-024, F-066 | `references/F-196.md` |
| F-197 | Favorite Meal Toggle | Favorites | functional | Should-have | F-024, F-129 | `references/F-197.md` |
| F-198 | Favorites List View | Favorites | functional | Should-have | F-196 | `references/F-198.md` |
| F-199 | Reorder from Past Order | Favorites | functional | Should-have | F-160 | `references/F-199.md` |
| F-200 | Cook Revenue Analytics | Analytics | functional | Should-have | F-076, F-151 | `references/F-200.md` |
| F-201 | Cook Order Analytics | Analytics | functional | Should-have | F-076, F-151 | `references/F-201.md` |
| F-202 | Cook Customer Retention Analytics | Analytics | functional | Could-have | F-200 | `references/F-202.md` |
| F-203 | Cook Delivery Performance Analytics | Analytics | functional | Could-have | F-200 | `references/F-203.md` |
| F-204 | Client Spending & Order Stats | Analytics | functional | Should-have | F-024, F-151 | `references/F-204.md` |
| F-205 | Admin Platform Revenue Analytics | Analytics | functional | Should-have | F-057 | `references/F-205.md` |
| F-206 | Admin Cook Performance Metrics | Analytics | functional | Should-have | F-057 | `references/F-206.md` |
| F-207 | Admin Growth Metrics | Analytics | functional | Should-have | F-057 | `references/F-207.md` |
| F-208 | Analytics CSV/PDF Export | Analytics | functional | Should-have | F-200 | `references/F-208.md` |
| F-209 | Cook Creates Manager Role | Manager | functional | Must-have | F-076, F-006 | `references/F-209.md` |
| F-210 | Manager Permission Configuration | Manager | functional | Must-have | F-209 | `references/F-210.md` |
| F-211 | Manager Dashboard Access | Manager | functional | Must-have | F-210, F-076 | `references/F-211.md` |
| F-212 | Cancellation Window Configuration | Cook Settings | functional | Must-have | F-076 | `references/F-212.md` |
| F-213 | Minimum Order Amount Configuration | Cook Settings | functional | Must-have | F-076 | `references/F-213.md` |
| F-214 | Cook Theme Selection | Cook Settings | functional | Must-have | F-076, F-011 | `references/F-214.md` |
| F-215 | Cook Promo Code Creation | Promo | functional | Should-have | F-076 | `references/F-215.md` |
| F-216 | Cook Promo Code Edit | Promo | functional | Should-have | F-215 | `references/F-216.md` |
| F-217 | Cook Promo Code Deactivation | Promo | functional | Should-have | F-215 | `references/F-217.md` |
| F-218 | Promo Code Application at Checkout | Promo | functional | Should-have | F-215, F-146 | `references/F-218.md` |
| F-219 | Promo Code Validation Rules | Promo | edge-case | Should-have | F-215 | `references/F-219.md` |

---

## Notification Catalog

| Code | Trigger Event | Channel(s) | Recipient(s) | Content Summary |
|------|--------------|------------|--------------|-----------------|
| N-001 | New order placed | Push, DB, Email | Cook, Manager(s) | New order from {customer} - {meal} |
| N-002 | Order status updated | Push, DB, Email | Client | Your order is now {status} |
| N-003 | Order confirmed by cook | Push, DB | Client | Your order has been confirmed |
| N-004 | Order ready | Push, DB | Client | Your order is ready |
| N-005 | Order completed | Push, DB | Client | Your order has been completed |
| N-006 | Payment successful | Push, DB, Email | Client | Payment of {amount} XAF received |
| N-007 | Payment failed | Push, DB | Client | Payment failed, please retry |
| N-008 | Refund processed | Push, DB, Email | Client | Refund credited to wallet |
| N-009 | Complaint submitted | Push, DB | Cook, Manager(s) | New complaint on order |
| N-010 | Complaint response | Push, DB | Client | Cook responded to complaint |
| N-011 | Complaint escalated | Push, DB | Admin(s) | Complaint escalated - unresolved 24h |
| N-012 | Complaint resolved | Push, DB, Email | Client | Complaint resolved |
| N-013 | Withdrawal processed | Push, DB, Email | Cook | Withdrawal sent to mobile money |
| N-014 | Withdrawal failed | Push, DB | Cook, Admin | Withdrawal failed - manual action needed |
| N-015 | Amount withdrawable | Push, DB | Cook | Amount now withdrawable |
| N-016 | New order message | Push, DB | Recipient | New message on order |
| N-017 | Order cancelled | Push, DB | Cook | Order cancelled by customer |
| N-018 | New testimonial | Push, DB | Cook | New testimonial received |
| N-019 | Rating received | Push, DB | Cook | New rating on order |
| N-020 | System announcement | Push, DB, Email | Targeted roles | Announcement content |
| N-021 | Email verification | Email | New user | Verify your email |
| N-022 | Password reset | Email | User | Reset your password |
| N-023 | Cancel window expiring | Push, DB | Client | Cancellation window expires soon |
| N-024 | Promo code applied | DB | Client | Promo code applied |

---

## User Roles Summary

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| Super-Admin | Platform owner with full control. Created via artisan command. | All permissions |
| Admin | Platform management via /vault-entry on main domain. | Tenant CRUD, user mgmt, financials, complaints, settings |
| Cook | Tenant owner. Manages food business through cook dashboard. | Meal CRUD, orders, brand, locations, schedules, managers, promos |
| Manager | Cook-assigned helper with configurable permissions. | Subset of cook permissions as configured |
| Client | Default role. Orders and interacts on any tenant domain. | Browse, order, pay, rate, review, complain, message |

---

## Order Status Flow

Pending Payment > Paid > Confirmed > Preparing > Ready > Out for Delivery / Ready for Pickup > Delivered / Picked Up > Completed

Special states: Cancelled (client within time window), Refunded (after cancellation/complaint)

---

## How to Use This Skill

### Implementing a Feature
1. Check the Feature Catalog for the feature
2. Verify all Precedence features are done
3. Read the feature's reference file
4. Read `references/general-concepts.md` for standards
5. Read `references/tech-stack.md` for technology details
6. Implement according to the spec

### Checking Requirements
Read the specific feature file for scenarios, business rules, acceptance criteria, and edge cases.

### Generating a Client Document
Read `references/client-doc-guide.md` for instructions.

### Understanding Project History
Read `references/spec-state.json` for interview history and decisions.
