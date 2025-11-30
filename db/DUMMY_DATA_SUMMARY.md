# Database Dummy Data Population Summary

**Date:** November 30, 2025  
**Purpose:** Populate all empty tables with realistic test data for analytics and chart testing

## 📊 Tables Populated

### 1. **chat** - 23 messages

- 3 active conversations between organizers and managers
- Conversation 1: Organizer Adrian (ID: 6) ↔ Manager Linux (ID: 2) - 18th Birthday Planning
- Conversation 2: Organizer Maricris (ID: 9) ↔ Manager Linux (ID: 2) - Corporate Team Building
- Conversation 3: Organizer Adrian (ID: 6) ↔ Manager Dore (ID: 8) - Wedding Planning
- Message status: Mix of read (1) and unread (0) messages for realistic testing

### 2. **event_contracts** - 28 contracts

- **23 Approved Contracts** - For all completed events
- **3 Approved Contracts** - For confirmed events (events 6, 10, 18)
- **2 Pending Contracts** - For pending events (events 1, 2)
- Contract status types: 'approved' and 'pending'
- All contracts include event description, date, and total cost

### 3. **event_payments** - 34 payment records

- **Full Payments:** 15 records for completed events
- **Split Payments (Downpayment + Partial):** 14 pairs (28 records total)
  - 50% downpayment followed by 50% partial payment
- **Confirmed Event Downpayments:** 3 records (events 6, 10, 18)
- Payment methods: bank_transfer, gcash, paymaya
- Payment status: verified (with verified_by and verified_at timestamps)
- Payment types: full, downpayment, partial
- Reference numbers: Unique transaction IDs (TXN/GC/PM format)

### 4. **pricing_demand_forecast** - 48 monthly forecasts

Monthly demand predictions for 5 key venues:

- **Venue 1** (Shepherd's Events Garden): 12 months of forecasts
- **Venue 2** (Lima Park Hotel Batangas): 12 months of forecasts
- **Venue 4** (La Solana Splendido): 12 months of forecasts
- **Venue 5** (South Peak Garden): 12 months of forecasts

**Metrics per forecast:**

- Month/Year
- Predicted bookings (0-3 per month)
- Predicted revenue (₱0 - ₱235,000)
- Confidence score (0.58 - 0.92)
- Created timestamps throughout 2024-2025

### 5. **pricing_market_analysis** - 40 market analysis records

Competitive market data across multiple cities and capacity ranges:

- **Batangas City:** 10 records (various capacity ranges)
- **Tanauan City:** 2 records
- **Santa Rosa:** 2 records
- **Calamba:** 4 records
- **San Pablo:** 2 records
- **Tagaytay:** 10 records (multiple capacity ranges)
- **Nasugbu:** 2 records
- **Lipa:** 2 records
- **Imus:** 2 records
- **Lucena:** 2 records
- **Alfonso:** 2 records

**Analysis includes:**

- Capacity range (min/max)
- Market average price
- Market average bookings
- Competitor count
- Analysis date (October & November 2025)

## 📈 Data Statistics by Table

| Table Name              | Row Count | Description                        |
| ----------------------- | --------- | ---------------------------------- |
| chat                    | 23        | Organizer-Manager conversations    |
| event_contracts         | 28        | Event contracts (approved/pending) |
| event_payments          | 34        | Payment transactions               |
| pricing_demand_forecast | 48        | Monthly demand predictions         |
| pricing_market_analysis | 40        | Market competitive analysis        |
| events                  | 28        | Event bookings                     |
| venues                  | 19        | Venue listings                     |
| users                   | 5         | System users                       |
| amenities               | 11        | Available amenities                |
| venue_amenities         | 91        | Venue-amenity relationships        |
| prices                  | 19        | Venue pricing data                 |
| services                | 3         | Event services                     |
| suppliers               | 3         | Service suppliers                  |
| locations               | 18        | Geographic locations               |
| parking                 | 15        | Parking facilities                 |
| ai_conversations        | 1         | AI chat sessions                   |
| ai_messages             | 3         | AI chat messages                   |
| event_services          | 5         | Event-service bookings             |
| payments                | 1         | Legacy payment records             |
| pricing_ml_history      | 5         | ML pricing calculations            |

## 🎯 Use Cases Enabled

### Analytics & Charts

- **Revenue Analytics:** Full payment history across 28 events (₱2.1M+ total)
- **Booking Trends:** Monthly demand forecasts for visualization
- **Market Analysis:** Competitive positioning across 11 cities
- **Payment Methods:** Distribution analysis (bank_transfer, gcash, paymaya)
- **Event Types:** Wedding (9), Corporate (5), Birthday (6), Party (4), Test (2)

### Chat System Testing

- 3 active conversation threads
- 23 messages with realistic timestamps
- Read/unread status tracking
- Organizer-Manager interactions

### Contract Management

- 28 contracts covering all events
- Approved/pending workflow testing
- Contract text with event details

### ML Pricing Analytics

- 48 monthly demand forecasts for trend analysis
- 40 market analysis records for competitive insights
- Confidence scoring for prediction reliability
- Historical data from Dec 2024 - Nov 2025

## 📅 Data Time Range

- **Historical Events:** December 2024 - September 2025 (completed)
- **Confirmed Events:** October 2025 (upcoming)
- **Pending Events:** November 2025
- **Forecasts:** January 2025 - December 2025
- **Market Analysis:** October-November 2025

## 💡 Testing Scenarios

1. **Dashboard Analytics:** Revenue charts, booking trends, event status distribution
2. **ML Pricing Insights:** Demand forecasting visualization, market positioning
3. **Payment Tracking:** Payment status reports, method distribution, verification workflow
4. **Chat Interface:** Message threading, read status, conversation history
5. **Contract Workflow:** Approval process, status tracking
6. **Forecast Accuracy:** Compare predicted vs actual bookings

## 🔄 How to Re-populate

If you need to reset and re-populate the data:

```bash
# From project root
/opt/lampp/bin/mysql -u root sad_db < db/populate_dummy_data.sql
```

The script automatically truncates the following tables before inserting:

- chat
- event_contracts
- event_payments
- pricing_demand_forecast
- pricing_market_analysis

## ✅ Verification Queries

Check populated data:

```sql
-- Row counts
SELECT 'chat', COUNT(*) FROM chat
UNION ALL SELECT 'event_contracts', COUNT(*) FROM event_contracts
UNION ALL SELECT 'event_payments', COUNT(*) FROM event_payments
UNION ALL SELECT 'pricing_demand_forecast', COUNT(*) FROM pricing_demand_forecast
UNION ALL SELECT 'pricing_market_analysis', COUNT(*) FROM pricing_market_analysis;

-- Sample event with payment data
SELECT
    e.event_name,
    e.event_type,
    e.status,
    v.venue_name,
    ep.amount_paid,
    ep.payment_type,
    ep.payment_status
FROM events e
LEFT JOIN venues v ON e.venue_id = v.venue_id
LEFT JOIN event_payments ep ON e.event_id = ep.event_id
LIMIT 10;
```

## 📝 Notes

- All monetary values are in Philippine Pesos (₱)
- Timestamps use MySQL `current_timestamp()` default
- Payment reference numbers follow patterns: TXN/GC/PM + date + sequence
- Confidence scores range from 0.58 to 0.92 (realistic ML prediction confidence)
- Market analysis covers 11 cities in Southern Luzon region
- Event statuses: pending, confirmed, completed
- Payment statuses: pending, verified, rejected

---

**Generated by:** GitHub Copilot  
**Date:** November 30, 2025  
**Database:** sad_db (MariaDB 10.4.32)
