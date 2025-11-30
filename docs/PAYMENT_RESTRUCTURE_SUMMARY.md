# Payment System Restructuring - Implementation Summary

## Overview

The payment system has been restructured to remove upfront payment during event creation and instead allow payments after event confirmation/approval.

## Database Changes

### New Table: `event_payments`

Created to track all payment transactions:

- `payment_id`: Auto-increment primary key
- `event_id`: Foreign key to events table
- `amount_paid`: Payment amount
- `payment_type`: enum('full', 'downpayment', 'partial')
- `payment_method`: Default 'gcash'
- `reference_no`: GCash 13-digit reference number
- `payment_status`: enum('pending', 'verified', 'rejected')
- `payment_date`: Timestamp of payment
- `verified_by`: Foreign key to users (admin/manager who verifies)
- `verified_at`: Timestamp of verification
- `notes`: Optional notes

### Updated Table: `events`

Added columns:

- `total_paid`: decimal(10,2) - Tracks total amount paid
- `payment_status`: enum('unpaid', 'partial', 'paid') - Payment status

## File Changes

### 1. Database Migration File

**File**: `db/payment_update.sql`

- Creates `event_payments` table
- Adds payment tracking columns to `events` table
- Run this SQL file to update your database

### 2. Create Event Page

**File**: `public/pages/organizer/create-event.php`
**Changes**:

- Removed GCash payment modal completely
- Removed payment validation from form submission
- Updated success message to inform users about payment after confirmation
- Form now submits directly without payment step

### 3. Create Event Handler

**File**: `src/services/create-event-handler.php`
**Changes**:

- Removed GCash reference validation
- Removed payment record insertion
- Events are created with status 'pending' without payment requirement

### 4. Payment Processing Handler (NEW)

**File**: `src/services/process-payment.php`
**Purpose**: Handles payment submissions from organizers
**Features**:

- Validates payment type (full, downpayment, partial)
- Validates payment amount against remaining balance
- Enforces 30% minimum for downpayment
- Inserts payment record with 'pending' status
- Updates event's total_paid and payment_status

### 5. Organizer Bookings Page

**File**: `public/pages/organizer/bookings.php`
**Changes**:

- Updated SQL query to include payment fields (total_paid, payment_status)
- Added Payment column to table showing amount paid and status
- Added Action column with "Pay Now" button for confirmed events
- Added payment modal with:
  - QR code display
  - Payment summary (total, paid, remaining)
  - Payment type selection (Full/Downpayment 30%/Partial)
  - Dynamic amount calculation
  - GCash reference number input
  - Real-time validation

## Payment Flow

### New Event Creation Flow:

1. Organizer creates event (no payment required)
2. Event status: "pending"
3. Payment status: "unpaid"
4. Manager/Admin reviews and confirms/rejects event

### Payment Flow (After Confirmation):

1. Organizer views bookings
2. For confirmed events with unpaid/partial status, "Pay Now" button appears
3. Organizer clicks "Pay Now" → Payment modal opens
4. Organizer selects payment type:
   - **Full Payment**: Pays remaining balance
   - **Downpayment**: Pays 30% minimum of total cost
   - **Partial**: Pays custom amount
5. Organizer scans QR code and pays via GCash
6. Organizer enters 13-digit GCash reference number
7. Payment submitted with status "pending"
8. Payment awaits verification by manager/admin
9. Event payment_status updates:
   - "partial" if amount < total cost
   - "paid" if amount >= total cost

## Payment Options

### Full Payment

- Pays the complete remaining balance
- One-time payment
- Event marked as fully paid

### Downpayment (30%)

- Minimum 30% of total event cost
- Allows organizers to secure booking
- Remaining balance can be paid later

### Partial Payment

- Custom amount up to remaining balance
- Flexible payment installments
- Organizers can pay any amount they choose

## Validation Rules

1. **Reference Number**: Must be exactly 13 digits
2. **Payment Amount**:
   - Must be greater than 0
   - Cannot exceed remaining balance
   - Downpayment must be ≥ 30% of total cost
3. **Payment Type**: Must be one of: full, downpayment, partial
4. **Event Access**: Only event organizer can make payments
5. **Event Status**: Payment only allowed for "confirmed" events

## UI/UX Features

### Payment Modal

- QR code for GCash scanning
- Payment summary showing total, paid, and remaining amounts
- Three-button payment type selector
- Auto-calculation based on payment type
- Real-time validation feedback
- Disabled state during processing

### Bookings Table

- Payment status badge (Unpaid/Partial/Paid) with color coding
- Amount paid display
- Conditional "Pay Now" button (only for confirmed & unpaid/partial events)

## Security Features

1. Session-based authentication
2. Event ownership verification
3. SQL injection prevention (prepared statements)
4. Transaction rollback on errors
5. Input validation and sanitization
6. CSRF protection through session validation

## Next Steps (Optional Enhancements)

1. **Manager/Admin Payment Verification**:

   - Create interface for managers to verify payments
   - Update payment_status from 'pending' to 'verified'/'rejected'
   - Send notifications to organizers

2. **Payment History**:

   - View all payments made for an event
   - Download payment receipts
   - Payment timeline view

3. **Payment Reminders**:

   - Automated reminders for unpaid bookings
   - Deadline enforcement for payments
   - Grace period configuration

4. **Multiple Payment Methods**:
   - Bank transfer option
   - Credit/debit card integration
   - PayPal integration

## Testing Checklist

- [ ] Run `payment_update.sql` to update database
- [ ] Test event creation without payment
- [ ] Test payment modal for confirmed events
- [ ] Test full payment option
- [ ] Test downpayment (30%) option
- [ ] Test partial payment option
- [ ] Test payment validation (amount, reference number)
- [ ] Test payment submission and database updates
- [ ] Verify payment status updates correctly
- [ ] Test multiple partial payments until full payment
- [ ] Test that payment button disappears when fully paid
- [ ] Test that payment only works for confirmed events

## Migration Steps

1. **Backup Database**: Always backup before schema changes
2. **Run SQL Migration**: Execute `db/payment_update.sql`
3. **Test in Development**: Verify all functionality works
4. **Deploy to Production**: Update files and run migration
5. **Monitor**: Check for any errors or issues

## Support

If you encounter any issues:

1. Check browser console for JavaScript errors
2. Check PHP error logs for backend errors
3. Verify database schema matches migration file
4. Ensure all file paths are correct
5. Clear browser cache and reload
