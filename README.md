# Recovery Interface — 3D Biomechanical Telemetry & Workout Tracker

An engineering-focused web application designed to compute, model, and visualize localized muscular fatigue and biological readiness. Built with vanilla ES6 modules, a layered PHP backend architecture, and an interactive 3D SVG telemetry viewport.

---

## Architecture Overview

The application follows a layered, three-tier-inspired architecture that separates the frontend, application logic, data access, and database layers.

```text
[ Frontend: ES6 Modules + CSS Subsystems ]
                   │
                   ▼ (HTTP/JSON API)
     [ Application Endpoints (pages/) ]
                   │
         ┌─────────┴─────────┐
         ▼                   ▼
[ Business Logic (BLL) ]   [ Data Access (DAL) ]
  • RecoveryCalculator       • Repositories (PDO)
  • AnalyticsService         • Parameterized SQL
         │                   │
         └─────────┬─────────┘
                   ▼
       [ MySQL / MariaDB (InnoDB) ]
```

### Key Modules:
* **Presentation Layer (`js/`, `css/`)**:
  * Decoupled native ES6 modules (`state`, `api`, `hologram`, `analytics`, `picker`, `planner`, `workout`, `profile`, `auth`, `main`).
  * CSS organized into 7 specialized subsystems imported via a master stylesheet index.
  * 3D hologram stage with interactive touch/drag rotation on the Y-axis.
* **Business Logic Layer (`bll/`)**:
  * `RecoveryCalculator.php`: Implements exponential fatigue decay, relative load scaling, and multi-factor biometric adjustments.
  * `AnalyticsService.php`: Aggregates 8-week weekly volume, 30-day regional balance, and exercise frequency.
* **Data Access Layer (`dal/`)**:
  * Parameterized PDO queries are used for database-bound user input to reduce SQL injection risk.
---

## Core Capabilities

1. **Dynamic Holographic Heatmap**: 17 anatomical muscle groups render real-time localized readiness colors with continuous laser-sweep telemetry.
2. **Predictive Simulator (Session Planner)**: Simulate future workout impact on any selected date before logging the actual session.
3. **Profile-Based Fatigue Normalization**: Scales fatigue by external weight vs. body weight ratio, age recovery curve, biological sex, and lifestyle activity tier.
4. **Comprehensive Training Analytics**: Visual histogram of weekly sets, regional load distribution, and top 5 recorded exercises.
5. **Multi-User Authentication and data isolation**: Password hashing using PHP's password_hash() with PASSWORD_DEFAULT, user-scoped database access, and DOM-safe rendering of user-provided content to reduce HTML injection/XSS risk.

---

## Database Setup & Installation

### Prerequisites
* PHP 8.1 or higher (with `pdo_mysql` extension enabled).
* MySQL 8.0+ or MariaDB 10.4+ (e.g., via XAMPP).

### Quick Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/raff-bit/recovery-interface.git
   cd recovery-interface
   ```

2. **Configure Environment**:
   Duplicate .env.example as .env and configure the database credentials.
   

3. **Import Database Schema & Seeds**:
   Execute the unified schema and reference data in MySQL / phpMyAdmin:
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p < database/seed.sql
   mysql -u root -p < database/seed_additional_exercises.sql
   ```

4. Run Application:
   - Place the project folder inside XAMPP's htdocs/ directory.
   - Start Apache and MySQL from the XAMPP Control Panel.
   - Open the application through http://localhost/fit-app

---

## Automated Test Suite

A standalone test suite verifies core model behavior, edge cases, and profile-based adjustments without requiring an external test runner or database.

```bash
php tests/RecoveryCalculatorTest.php
```

### Verified Test Cases:
* Guest baseline initialization (100% readiness).
* Acute fatigue accumulation following recent workouts.
* Exponential decay convergence (>= 95% readiness at full recovery window).
* Non-linear RPE fatigue scaling (RPE 10 > RPE 6).
* Volume accumulation across multiple sets.
* Relative load scaling (external weight vs. athlete body weight).
* Bodyweight movement load normalization.
* Age-adjusted physiological recovery expansion.
* Future predictive engine delta calculations.

---

## Documentation
* [Biomechanical Recovery Model Specification](docs/recovery-model.md)

---

## License
Distributed under the MIT License. See `LICENSE` for more information.