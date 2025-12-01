# Admin Module - Complete Rewrite Documentation

## Overview

Complete rewrite of the Gatherly EMS admin panel with enhanced features, modern UI, and comprehensive system management capabilities.

## Implementation Date

December 1, 2025

## Database Migrations Required

### 1. Run Supplier Module Migration

```bash
mysql -u root -p sad_db < db/admin_suppliers_migration.sql
```

Or for production:

```bash
mysql -u gatherly_sys -p gatherly_sad_db < db/admin_suppliers_migration.sql
```

## New Admin Pages

### Core Management

1. **admin-dashboard.php** - System overview with analytics
2. **admin-chats.php** - Omnipotent chat system (view/join all conversations)
3. **manage-users.php** - Enhanced user management with bulk operations
4. **manage-venues.php** - Venue approval and review system
5. **manage-events.php** - Event management and oversight
6. **reports.php** - Advanced analytics with PDF/CSV export

### New Modules

7. **manage-suppliers.php** - Supplier directory management
8. **profile.php** - Admin profile management
9. **settings.php** - System-wide settings

## Key Features

### Dashboard

- Real-time system statistics
- User growth charts
- Revenue analytics
- Quick action buttons
- System health monitoring

### User Management

- Role-based filtering (organizer/manager/admin)
- Bulk activate/deactivate
- User activity tracking
- Account suspension
- Export user data

### Chat System

- View ALL conversations (organizer ↔ manager)
- Join any conversation as admin
- Broadcast messages
- Export chat history
- Message moderation

### Venue Management

- Approval workflow for new venues
- Featured venue toggle
- Verification badges
- Quality rating system
- Bulk operations

### Event Management

- Multi-status tabs (pending/confirmed/completed/canceled)
- Admin override capabilities
- Payment tracking
- Contract status
- Event timeline view

### Reports & Analytics

- Revenue reports (by venue, type, location)
- Event analytics
- User engagement metrics
- Venue utilization rates
- **Styled PDF Export** with charts
- **CSV Export** for data analysis
- Date range filtering

### Supplier Management (NEW)

- 10 supplier categories
- Package management
- Booking tracking
- Rating system
- Featured suppliers

## Security Enhancements

### All Pages Include:

- Prepared statements (SQL injection prevention)
- CSRF token protection
- Input validation and sanitization
- Role-based access control
- Audit logging
- Session security

## UI/UX Standards

### Design System:

- TailwindCSS utility classes
- Font Awesome 7.0.1 icons
- Chart.js 4.4.0 for visualizations
- Responsive design (mobile-first)
- Consistent color scheme (Indigo primary)
- Modal dialogs for actions
- Toast notifications

### Navigation:

- Sidebar layout (default)
- Mobile-responsive hamburger menu
- Profile dropdown
- Active page highlighting

## File Structure

```
public/pages/admin/
├── admin-dashboard.php       # ✅ System dashboard
├── admin-chats.php          # ✅ Omnipotent chat
├── manage-users.php         # ✅ User management
├── manage-venues.php        # ✅ Venue management
├── manage-events.php        # ✅ Event management
├── reports.php              # ✅ Analytics & reports
├── manage-suppliers.php     # ✅ Supplier management
├── profile.php              # ✅ Admin profile
└── settings.php             # ✅ System settings
```

## Dependencies

### Required:

- PHP 7.4+
- MySQL 5.7+
- TailwindCSS (via CDN)
- Font Awesome 7.0.1
- Chart.js 4.4.0
- jsPDF 2.5.1 (for PDF export)

### Database Tables Used:

- users
- events
- venues
- locations
- chat
- amenities
- payments
- event_contracts
- suppliers (NEW)
- supplier_packages (NEW)
- supplier_bookings (NEW)

## API Endpoints

### Chat API (admin-chats.php):

- `?action=get_all_conversations` - Get all system chats
- `?action=get_messages&user1={id}&user2={id}` - Get specific conversation
- `?action=send_message` - Send message as admin
- `?action=broadcast_message` - Send to all users
- `?action=export_chat&conversation_id={id}` - Export chat as PDF

### Reports API (reports.php):

- `?action=export_pdf&type={report_type}` - Export styled PDF
- `?action=export_csv&type={report_type}` - Export CSV
- Various report type parameters

## Configuration

### Environment Setup:

Ensure `config/database.php` has correct settings:

```php
define('DEPLOYMENT_ENV', 'production'); // or 'development'
```

### Admin Permissions:

Only users with `role = 'administrator'` can access admin pages.

## Testing Checklist

- [ ] All pages load without errors
- [ ] Database queries use prepared statements
- [ ] PDF export generates correctly
- [ ] CSV export downloads properly
- [ ] Charts render with data
- [ ] Mobile responsive design works
- [ ] Search and filters function
- [ ] Bulk operations work
- [ ] Chat system sends/receives messages
- [ ] Supplier CRUD operations work
- [ ] All forms validate input
- [ ] Session security prevents unauthorized access

## Performance Optimizations

- Indexed database queries
- Pagination for large datasets
- Lazy loading for charts
- Cached query results where appropriate
- Optimized image loading

## Future Enhancements

### Planned Features:

- Email notification system
- SMS integration
- Two-factor authentication
- Advanced audit logging
- API rate limiting
- Automated backups
- System health monitoring
- Performance metrics dashboard

## Support & Maintenance

### Common Issues:

**Error 500 on Reports Page:**

- Check database column names match schema
- Verify all JOINs have correct table aliases
- Ensure date filters use intval() for SQL injection prevention

**Chat Not Loading:**

- Verify database.php is loaded correctly
- Check encryption keys are set
- Ensure chat table exists

**PDF Export Failing:**

- Check jsPDF library is loaded
- Verify charts are rendered before export
- Ensure sufficient server memory

## Credits

Built by: Adrian (LinuxAdona)
Framework: Custom PHP/MySQL
Design: TailwindCSS
Repository: Gatherly-EMS_2025

---

Last Updated: December 1, 2025
Version: 2.0.0 (Complete Rewrite)
