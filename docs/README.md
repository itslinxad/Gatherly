# 🏛️ Gatherly: Event Management System (GEMS)

## 🎉 NEW: Conversational AI Event Planner (v2.0)

**NOW LIVE!** Our AI assistant uses conversational dialogue to help organizers plan complete events:

- 🤖 **Incremental Questioning** - Asks questions step-by-step for better accuracy
- 🏛️ **Venue Recommendations** - Top 3 venues with ML-based matching scores
- 👥 **Supplier Recommendations** - Catering, Photography, Videography, Styling, and more!
- 💰 **Budget-Aware** - Automatically allocates budget across venue and services
- 📊 **7 Service Categories** - Complete event planning in one conversation

[View Conversational AI Guide →](ml/CONVERSATIONAL_AI_GUIDE.md) | [Implementation Details →](ml/IMPLEMENTATION_SUMMARY.md)

---

## Overview

**Gatherly (GEMS)** is an intelligent **Event Management and Venue Booking Platform** designed to optimize how venues are discovered, booked, and managed.  
It solves the common pain points of event organizers and venue owners by integrating **smart venue recommendations**, **dynamic pricing**, **real-time availability tracking**, **forecasting**, and **automated contract management** into a single system.

---

## 🧩 Table of Contents

1. [Introduction](#introduction)
2. [Key Issues](#key-issues)
3. [Solutions](#solutions)
4. [Target Clients](#target-clients)
5. [Smart Features Overview](#smart-features-overview)
6. [Development Plan](#development-plan)
7. [Tech Stack](#tech-stack)
8. [Database Schema](#database-schema)
9. [API Endpoints](#api-endpoints)
10. [Recommendation System](#recommendation-system)
11. [Dynamic Pricing & Forecasting](#dynamic-pricing--forecasting)
12. [System Architecture](#system-architecture)
13. [UI & UX Expectations](#ui--ux-expectations)
14. [Dashboard & Analytics](#dashboard--analytics)
15. [Testing & QA](#testing--qa)
16. [Deliverables per Cycle](#deliverables-per-cycle)
17. [Acceptance Criteria](#acceptance-criteria)
18. [Monitoring & Observability](#monitoring--observability)
19. [Example Scenarios](#example-scenarios)
20. [License](#license)

---

## Introduction

Gatherly (GEMS) aims to improve the **events and venue management industry** by combining intelligent recommendations, automated scheduling, and analytics.  
It benefits both **event organizers** and **venue managers** through automation, real-time data, and predictive insights.

---

## Key Issues

- Difficulty in finding suitable venues quickly.
- Manual management of schedules causing **double-bookings**.
- Venue mismatches (capacity or amenities).
- No tools for recommending venues based on **budget, location, and amenities**.
- Gaps in communication between organizers and managers.

---

## Solutions

| Feature                               | Description                                                                             |
| ------------------------------------- | --------------------------------------------------------------------------------------- |
| **Digital Venue Booking System**      | Real-time booking, availability tracking, and instant confirmation.                     |
| **Smart Venue Recommendation Engine** | Uses multi-criteria decision-making and collaborative filtering for best venue matches. |
| **Conflict Prevention Module**        | Prevents double-booking through instant calendar updates.                               |
| **Venue Profile Management**          | Venue owners showcase details like capacity, amenities, and pricing.                    |
| **Dynamic Pricing Engine**            | Adjusts venue pricing by season, day, and demand trends.                                |
| **Forecasting Engine**                | Predicts occupancy rates and suggests optimal rental prices.                            |
| **Resource Optimization**             | Auto-suggests alternative venues when conflicts occur.                                  |
| **Maps Integration**                  | Estimates travel times for guests via Google Maps API.                                  |
| **Dashboard Analytics**               | Venue trends, occupancy rates, and revenue insights.                                    |
| **In-app Chat Module**                | Real-time communication between organizers and venue managers.                          |
| **Automated Contracts**               | Auto-generated agreements with digital signatures.                                      |

---

## Target Clients

- **Event Organizers:** Companies or individuals managing weddings, concerts, corporate events, etc.
- **Venue Owners/Managers:** Gyms, hotels, convention centers, and coliseums.
- **Suppliers/Vendors:** Catering, AV, Lights & Sounds, decoration services.
- **Municipal/Community Centers:** Government facilities managing local events.

---

## Smart Features Overview

### Multi-Criteria Decision-Making (MCDM)

Each venue recommendation considers:

- Capacity match
- Price vs budget
- Amenities (catering, sound, parking, accessibility)
- Location/travel time
- Collaborative filtering (similar event behavior)

### Collaborative Filtering

Learns from previous event-venue patterns to suggest venues used by similar organizers.

### Dynamic Pricing & Forecasting

Adapts pricing by:

- **Season (peak/off-peak)**
- **Day (weekends/weekdays)**
- **Demand (number of inquiries)**

### Resource Optimization

Automatically recommends similar venues within the same category or price range if the requested one is unavailable.

### Analytics & Dashboard

Tracks:

- Occupancy rates
- Top venue types
- Event type trends
- Budget statistics
- Price-performance analysis

---

## Development Plan

**Goal:** Develop GEMS as a production-ready, intelligent, and scalable system.

**Phases:**

1. **Architecture Setup** – Backend, Frontend, Database.B
2. **Authentication & Roles** – Admin, Organizer, Venue Manager.
3. **Venue Management** – CRUD, amenities, availability.
4. **Booking System** – Conflict-free, atomic transactions.
5. **Recommendation Engine** – MCDM + collaborative filtering.
6. **Dynamic Pricing & Forecasting** – AI-based prediction of demand and pricing.
7. **Chat & Contracts** – Real-time communication and automated agreements.
8. **Dashboard & Analytics** – Insights for venue managers.
9. **Testing & Deployment** – CI/CD, QA, documentation.

---

## Tech Stack

| Layer                   | Technology                          |
| ----------------------- | ----------------------------------- |
| **Frontend**            | HTML, CSS (TailwindCSS), JavaScript |
| **Backend**             | PHP                                 |
| **Database**            | MySQL                               |
| **Chat System**         | Socket.IO                           |
| **Machine Learning**    | Python (scikit-learn)               |
| **Maps API**            | Google Maps                         |
| **Payment (Test Only)** | Test                                |
| **Deployment**          | Docker (optional)                   |
| **Version Control**     | Git & GitHub                        |

---

## Database Schema (Simplified)

| Table                  | Description                                  |
| ---------------------- | -------------------------------------------- |
| **users**              | User info and role.                          |
| **venues**             | Venue details, capacity, pricing, amenities. |
| **venue_availability** | Tracks available dates and bookings.         |
| **bookings**           | Stores confirmed reservations.               |
| **events**             | Event details and requirements.              |
| **price_adjustments**  | Dynamic pricing rules.                       |
| **contracts**          | Auto-generated agreements.                   |
| **cf_interactions**    | Collaborative filtering input data.          |

---

## API Endpoints (Sample)

| Method | Endpoint                  | Description                   |
| ------ | ------------------------- | ----------------------------- |
| `GET`  | `/api/venues`             | Search venues.                |
| `GET`  | `/api/venues/:id`         | Venue details + availability. |
| `POST` | `/api/bookings`           | Create new booking.           |
| `GET`  | `/api/recommendations`    | Return ranked venues.         |
| `POST` | `/api/contracts/generate` | Generate agreement.           |
| `POST` | `/api/price-simulate`     | Suggest optimal pricing.      |

---

## Recommendation System

**Formula Example:**

```text
score = (w_capacity * capacity_score)
      + (w_price * price_score)
      + (w_location * location_score)
      + (w_amenities * amenities_score)
      + (w_cf * collaborative_score)
```

**Example Input:**

```
INSERT INTO venues
(name, capacity, price, location_score, amenities_score, collaborative_score, availability_date, parking_score, stage_setup_score, accessibility_score)
VALUES
('Grand Pavilion', 200, 98000, 0.88, 0.95, 0.92, '2025-11-15', 0.90, 0.95, 0.90),
('City Gymnasium', 300, 110000, 0.80, 0.85, 0.89, '2025-11-15', 0.85, 0.90, 0.88),
('Community Hall', 120, 75000, 0.75, 0.80, 0.87, '2025-11-15', 0.80, 0.85, 0.82);
```

**Example Output:**

```
SELECT
    venue_id,
    name,
    price,
    availability_date,

    -- Calculate individual sub-scores
    (150 / capacity) AS capacity_match,  -- Example for 150 attendees
    (120000 / price) AS price_match,     -- Example for 120K budget

    -- Weighted suitability score formula
    (
        (0.25 * (150 / capacity)) +
        (0.20 * (120000 / price)) +
        (0.15 * location_score) +
        (0.15 * amenities_score) +
        (0.10 * collaborative_score) +
        (0.05 * parking_score) +
        (0.05 * stage_setup_score) +
        (0.05 * accessibility_score)
    ) * 100 AS suitability_score

FROM venues
WHERE availability_date = '2025-11-15'
ORDER BY suitability_score DESC
LIMIT 3;
```

**Result Example:**

| venue_id | name           | price  | suitability_score |
| -------- | -------------- | ------ | ----------------- |
| 1        | Grand Pavilion | 98000  | 92.3              |
| 2        | City Gymnasium | 110000 | 86.5              |
| 3        | Community Hall | 75000  | 81.7              |

---

## Dynamic Pricing & Forecasting

**Formula Example:**

```
dynamic_price = base_price × season_factor × day_factor × demand_factor
```

**Forecast Model:** Prophet / ARIMA to predict:

- Venue occupancy rate
- Optimal rental prices
- Seasonal demand spikes

---

## System Architecture

```
Frontend (HTML + CSS(TailwindCSS) + JavaScript)
        ↓
Backend (PHP - XAMPP / Python (FAST API) for ML)
        ↓
Database (MySQL)
        ↓
Python Modules (Recommendation + Forecasting)
        ↓
Integrations
    ├─ Google Maps API (Venue Accessibility & Travel Time)
    ├─ Payment Gateway (Test Only)
    ├─ Contract Generator (PDF)
```

---

## UI & UX Expectations

**Organizer Flow**

Search for venues

1. Get smart recommendations
2. Check availability calendar
3. Reserve and pay
4. Auto-generate contract
5. Chat with venue manager

**Manager Flow**

1. Manage venue profile & calendar
2. Set pricing rules (season/day)
3. Handle inquiries
4. View analytics dashboard

---

## Dashboard & Analytics

- Venue occupancy trends
- Event popularity statistics
- Revenue breakdown
- Price-performance metrics
- Forecasted demand and pricing insights

---

## Testing & QA

- **Unit Tests:** Availability, price, scoring
- **Integration Tests:** Booking flow with concurrency
- **E2E Tests:** UI booking flow
- **Load Tests:** Booking API under stress
- **ML Validation:** Precision/Recall for ranking

---

## Deliverables per Cycle

1. API & Architecture Docs
2. Backend Services
3. Frontend Components
4. ML Scripts
5. Automated Tests
6. Deployment Pipelines
7. Demo & Seed Data

---

## Acceptance Criteria

- No double-bookings under concurrency
- Recommendations explainable & ranked
- Dynamic pricing responsive to demand
- Chat & contracts functional
- Dashboard analytics live & accurate

---

## Monitoring & Observability

Metrics:

- Booking success rate
- Conflict incidents
- Forecast accuracy
- API response times
- Conversion rates

---

## Example Scenarios

**Example Input:**

```
Birthday Party – 150 guests, ₱120,000 budget, requires catering & sound system
```

**System Output:**

```

Top 3 venues with suitability scores, pricing adjustments, and travel time estimates.

```

---

## License

© 2025 Gatherly Developers
Developed for the Events and Venue Management Industry with ❤️
