# Product Requirements Document (PRD)
## Project: User Scanner Web Platform (Laravel + PHP + Tailwind)

## 1) Overview
User Scanner is currently a Python CLI/library for email and username OSINT checks. This PRD defines the requirements to evolve it into a full-featured web application built with **Laravel (PHP)** and **Tailwind CSS**, while preserving scanning capabilities, modularity, and output accuracy.

## 2) Problem Statement
Current usage is command-line focused, which limits:
- Non-technical adoption
- Team collaboration and shared scan history
- Monitoring and observability at scale
- Productization opportunities (multi-tenant, subscriptions, API)

A modern web app is needed for interactive scanning workflows, account management, reporting, and extensibility.

## 3) Goals
- Deliver a production-ready Laravel web app with a clean Tailwind UI.
- Support both **single scan** and **bulk scan** workflows for username and email.
- Maintain modular scan architecture for easy platform connector additions.
- Provide persistent history, exports, and API access.
- Introduce secure authentication, role-based access, rate limits, and auditability.

## 4) Non-Goals (Phase 1)
- Re-implementing every existing Python connector on day one.
- Building a mobile-native app.
- Real-time distributed crawling across the public web.
- Bypassing CAPTCHAs or anti-bot protections in violation of platform terms.

## 5) Target Users
- **Security researchers / OSINT analysts**: run investigative scans, export evidence.
- **Brand/Community managers**: check username availability across platforms.
- **Agencies/Teams**: manage shared scans and reports.
- **Developers**: integrate scan APIs into internal tools.

## 6) Product Scope
### 6.1 Core Modules
1. User management
2. Scan execution engine
3. Scan targets/configuration (categories, modules, proxies)
4. Results storage, filtering, and export
5. Connector admin and management

### 6.2 Supported Scan Types
- Username scan
- Email registration scan

### 6.3 Scan Output
- Username: `Taken` / `Available` / `Error`
- Email: `Registered` / `Not Registered` / `Error`
- Include reason, response metadata, confidence (`high`, `mid`, `low`) and checked URL when available.

## 7) Functional Requirements

### FR-1: Dashboard
- Summary cards: total scans, success/error rates, most used modules.
- Recent scans table and quick actions.
- Tailwind-based responsive design.

### FR-2: Scan Creation (Single)
- User can choose scan type: username/email.
- Input validation with clear error messages.
- Optional category/module selection.
- Optional proxy profile selection.
- Optional verbose output toggle.

### FR-3: Scan Engine & Connectors
- Connector abstraction with consistent interface:
  - `supports(type)`
  - `scan(target, options)`
  - normalized result schema
- Connector groups (social, dev, creator, etc.).
- Retry policy, timeout policy, and standardized error handling.
- Connector health status shown in admin panel.

### FR-4: Job Processing
- Laravel Queue for asynchronous execution.
- Ability to cancel queued/running scan batches.
- Dead-letter/failure handling and retry limits.

### FR-5: Results Management
- Persist scans and item-level results in DB.
- Filter by type, status, category, module, date range, user.
- Export to JSON and CSV.

### FR-6: Admin Panel
- Enable/disable connectors/modules.
- Configure queue, timeout, and proxy settings.

## 8) Non-Functional Requirements
### NFR-1: Performance
- P95 page load < 2.5s for dashboard at moderate load.
- Queue throughput target: configurable; baseline 1000 scan items/hour on a single worker node.

### NFR-2: Availability & Reliability
- Graceful degradation when connectors fail.
- Idempotent queue jobs where practical.
- Backoff/retry strategy per connector.

### NFR-3: Security
- OWASP-aligned validation and output escaping.
- CSRF protection and secure cookie settings.

### NFR-4: Scalability
- Horizontally scalable queue workers.
- Caching strategy for static connector metadata.

### NFR-5: Observability
- Structured logs, metrics, and tracing-friendly hooks.
- Alerting for worker failures, queue backlog, and elevated error rates.

## 9) UX Requirements (Tailwind)
- Clean, utility-first design system using Tailwind.
- Accessible components (WCAG AA baseline).
- Key views:
  - Landing page
  - Dashboard
  - New scan wizard
  - Scan detail with live status
  - Results explorer with filters
  - Settings and admin panel

## 10) Suggested Architecture
- **Frontend:** Laravel Blade + Tailwind + optional Alpine.js/Livewire for interactivity.
- **Backend:** Laravel 11+, PHP 8.3+, service-layer pattern.
- **Queue:** Redis + Horizon.
- **DB:** MySQL.
- **Storage:** Local.

## 11) Migration Strategy (Python CLI ➜ Laravel)
### Phase A: Foundation
- Set up Laravel app, Tailwind, RBAC, base schema.
- Build dashboard + single scan flow with a minimal connector set.

### Phase B: Engine Parity
- Port all connectors (GitHub, X, Reddit, Instagram, etc.).
- Implement queue-driven scans and exports.

### Phase C: Productization
- Admin controls.
- Performance hardening.

## 12) MVP Definition
MVP is complete when:
- Users can log in and run single username/email scans.
- High-value connectors are operational.
- Results persist, filter, and export as CSV/JSON.
- Queue workers process batches asynchronously with visible progress.
- Admin can enable/disable connectors and inspect failed jobs.

## 13) Acceptance Criteria
- End-to-end scan workflow works.
- API endpoints return stable, documented schemas.
- Exports match UI data and include timestamps/module metadata.
- Security baseline checks (CSRF, rate limits) pass internal review.

## 14) KPIs
- Time-to-first-scan.
- Scan success/error ratio per connector.
