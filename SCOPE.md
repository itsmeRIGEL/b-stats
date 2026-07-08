# Pickle-Booking System — Full Scope Document

**Version**: 1.0  
**Prepared**: June 15, 2026  
**Tech Stack**: Laravel 12 (PHP 8.2) + Vue 3 SPA (Inertia.js) — full-stack web application, not a static site.

---

## 1. Custom Web Design with Revisions

### Pages (~25 total)

#### Public Pages
| Page | Route | Purpose |
|------|-------|---------|
| Landing / Welcome | `/` | Home page with branding, CTA |
| Court Booking | `/book` | Public client-facing booking form |
| Tournament Spectator | `/tournaments/live` | Public bracket view of live/completed tournaments |

#### Authentication Pages
| Page | Route | Purpose |
|------|-------|---------|
| Admin Login | `/admin/login` | Separate admin login portal |
| User Login | `/login` | Scheduler/scorer login |
| Register | `/register` | Account registration |
| Forgot Password | `/forgot-password` | Password reset request |
| Reset Password | `/reset-password/{token}` | New password form |
| Verify Email | `/verify-email` | Email verification prompt |
| Confirm Password | `/confirm-password` | Re-authentication for sensitive actions |

#### Admin / Dashboard Pages
| Page | Route | Purpose |
|------|-------|---------|
| Dashboard | `/dashboard` | Revenue summary, player counts, upcoming bookings, top players, weather widget |
| Bookings | `/bookings` | Full booking management — CRUD, approve/reject, payment tracking, court assignment |
| Scoring | `/scoring` | Live match scoring board with session management |
| All-Time Stats | `/all-time-stats` | Historical player leaderboard with match history (Overview / Leaderboard / Match History tabs) |
| Memberships | `/memberships` | Membership management — toggle yearly, track monthly dues, roster visibility |
| Sales Reports | `/sales-report` | Filterable revenue charts (daily / weekly / monthly) with granularity |
| Tournament Manager | `/tournaments` | Full tournament lifecycle — setup, bracket generation, scoring, archiving |
| Pickleball Settings | `/pickleball-settings` | System configuration — court count, fees, weather coords, logo, payment QR |
| Admin Users | `/admin-users` | User account management — create/edit/delete with role assignment |

#### Settings Pages
| Page | Route | Purpose |
|------|-------|---------|
| Profile | `/settings/profile` | Edit name, email, avatar |
| Password | `/settings/password` | Change password |
| Appearance | `/settings/appearance` | Light/dark mode toggle |

### Design Highlights
- **Responsive**: Fully adaptive mobile / tablet / desktop layouts
- **Theme**: Light and dark mode with system-aware default
- **UI Library**: Custom components built on shadcn-vue + Radix Vue primitives
- **Icons**: Lucide icon library throughout
- **Revision cycle**: Design feedback rounds included post-launch

---

## 2. Development

### Architecture
```
┌─────────────────────────────────────────────┐
│  Frontend (Vue 3 SPA)                        │
│  • Inertia.js (no full page reloads)        │
│  • TypeScript + Tailwind CSS                │
│  • shadcn-vue component library             │
│  • Vite build tool                          │
├─────────────────────────────────────────────┤
│  Backend (Laravel 12)                        │
│  • RESTful API via Inertia                  │
│  • MySQL database                           │
│  • 50 database migrations                   │
│  • Role-based middleware                     │
│  • Queue / cache / session (database)       │
└─────────────────────────────────────────────┘
```

### Core Features Built

#### Court Booking System
- Public booking form → admin approval → court assignment pipeline
- Overlap detection for same court / same time bookings
- Payment tracking (paid/unpaid toggles)
- Court-to-scorer assignment per date
- Booking types: standard booking, walk-in, reclub
- Booking expiry grace period for scoring visibility

#### Live Match Scoring
- Session-based scoring (add players, record matches, tally scores)
- Doubles support (2v2) with automatic win/loss/point computation
- Walk-in vs booking match tagging
- Booking session grouping with micro-leaderboards
- Real-time stat updates on tally

#### Player & Membership System
- Player roster with phone, birthday, address fields
- Yearly membership toggle (with fee tracking)
- Monthly membership dues with payment & revocation
- Roster visibility control (show/hide players)
- Bulk session player management

#### Tournament System
- **Bracket types**: Single Elimination, Double Elimination (with losers bracket), Round Robin
- **Team management**: 2-player teams with seeding, swapping
- **Bracket generation**: Automatic seeding and bracket tree creation
- **Match scheduling**: Configurable duration, rest time, break between rounds
- **Court assignment**: Dynamic court allocation per match
- **Scoring**: Score recording, forfeits, bypasses, match reset
- **Organization**: Tournament days + sub-folders with assigned scorers
- **Spectator mode**: Public bracket view for live and completed tournaments
- **Archiving**: Archive/unarchive completed tournaments

#### Analytics & Reporting
- Dashboard revenue cards (today / weekly / monthly with breakdowns)
- Sales report with date range picker and chart granularity
- All-time player leaderboard with points, win rate, match history
- Day-foldered match history with session sub-groups

#### Weather Integration
- Open-Meteo API weather forecast
- Cached with configurable coordinates
- Fallback mock data on API failure

---

## 3. Page / Content Migration

### Data Migration Paths
| Source | Target | Method |
|--------|--------|--------|
| Player records (existing) | `players` table | Manual import or seed |
| Booking history | `bookings` + `game_matches` tables | Admin or CSV import |
| Match results | `game_matches` table | Via scoring session or bulk |
| Membership payments | `membership_payments` table | Via admin manager |

### Workflow Migrations
1. **Public Booking**: Client submits form → admin approves → court assigned → scorer receives booking in scoring view
2. **Walk-in Scoring**: Scorer adds players to session → records matches → tallies → stats auto-update
3. **Tournament**: Create tournament → add teams → generate bracket → assign courts → score matches → archive

---

## 4. Site Security

### Access Control
- **4 roles** with granular permissions:
  - `admin`: Full system access (settings, users, finances)
  - `scheduler`: Booking management, court assignments, tournaments, sales
  - `scorer`: Scoring board, match recording, stats viewing
  - `scheduler_scorer`: Dual role with session-level role switching

### Authentication
- Email verification required (bypassable per user)
- Separate admin login route (`/admin/login`)
- Password reset with signed tokens
- Session management with configurable lifetime

### Middleware Protection
- Route groups gated by `EnsureRole` middleware
- 403 redirect for unauthorized access
- Inertia shared data includes only permitted information
- CSRF protection on all POST/PUT/DELETE routes

### Data Safety
- All user input validated via Laravel Form Requests
- SQL injection prevention via Eloquent ORM
- XSS protection via Inertia auto-escaping
- Passwords hashed with bcrypt
- Payment QR codes stored as server files (not external URLs)

---

## 5. CMS Dashboard (for edits)

All admin-editable content is managed through the application's built-in admin pages — no external CMS is needed.

### Editable Content Areas

| Section | Admin Page | What Can Be Edited |
|---------|-----------|-------------------|
| Application Branding | Settings | Name, logo image |
| Court Configuration | Settings | Number of courts, walk-in courts, mixed-use courts |
| Pricing | Settings | Cost per hour, walk-in game fee, membership fees |
| Scoring Rules | Settings | Win points, loss penalty, randomization toggle |
| Weather | Settings | Latitude/longitude coordinates |
| Payment | Settings | Payment QR code image, booking expiration grace |
| Users | Admin Users | Create/edit/delete admin/scheduler/scorer accounts |
| Bookings | Bookings | Create, edit, approve, reject, track payment |
| Players | Scoring / Memberships | Add/edit/delete players, toggle membership, manage roster |
| Memberships | Memberships | Toggle yearly membership, record/revoke monthly dues |
| Tournaments | Tournament Manager | Full lifecycle — setup, teams, brackets, scoring, archive |
| Sales Reports | Sales Reports | Filter and view revenue data (read-only) |

---

## 6. Optimization for Conversions

### Public Facing
- **Low-friction booking**: Court booking form on the landing page, no account required for clients
- **Tournament spectator mode**: Public bracket views drive engagement and promote walk-in signups
- **Mobile-responsive**: The entire booking flow works on mobile — most bookings happen on-the-go
- **Weather widget**: Helps planners decide whether to book outdoor courts

### Operational Efficiency
- **Live scoring → instant stats**: Tallying a match immediately updates the all-time leaderboard and player win rates — no manual data entry
- **Booking session grouping**: Scorers see matches organized by time slot, reducing cognitive load
- **Automated bracket generation**: Creating a tournament with 16+ teams takes seconds, not hours
- **Recurring workflows**: Monthly membership dues, booking approvals, court assignments all have admin toggle UI

### Retention Features
- **Leaderboard gamification**: Points system and win rate tracking encourage competitive play
- **Membership system**: Yearly and monthly tiers with clear pricing
- **Match history**: Day-foldered with session micro-leaderboards — players can review their performance
- **Sales reports**: Admins can identify peak hours, popular courts, and adjust pricing

---

## 7. Post-Launch Support

### Included (4 hours free)
- Bug fixes and edge-case handling
- Minor UI adjustments and styling revisions
- Documentation walkthrough for admin users
- Data migration assistance for existing records

### Out of Scope
- Custom integrations with third-party payment gateways (beyond manual payment tracking)
- SMS/email notification system beyond the built-in booking approval flow
- Mobile native app (the SPA is mobile-responsive via browser)
- Multi-tenant (multi-court-facility) support
- Real-time WebSocket scoring (current scoring is page-refresh based)

---

## Page Count Summary

| Category | Pages |
|----------|-------|
| Public | 1 (Landing) |
| Client Booking | 1 |
| Spectator | 1 |
| Authentication | 7 |
| Dashboard & Admin | 9 |
| Settings | 3 |
| **Total** | **~22 pages** |
