# WorkDo Super Admin Experience Baseline

## 1. Landing & Dashboard
- **URL / Route**: `/dashboard` (`dashboard`)
- **Widgets / KPIs**: Total Companies, Active Plans, Total Revenue, Order Volume, Storage & Database Utilization.
- **Top Navigation**: Global search, Quick action (+), Notifications bell, Language selector, Theme toggle, User Profile dropdown.

## 2. Navigation Sidebar
1. **Dashboard** (`dashboard`, `manage-dashboard`)
2. **Users / Companies** (`users.index`, `manage-users`)
   - Company listing with quick login/impersonate, plan assignment, active toggle, user quota.
3. **Landing Page** (`landing-page.index`, `manage-landing-page`)
   - CMS for public homepage sections, banners, features, pricing tables, screenshots, testimonials.
4. **Helpdesk** (`manage-helpdesk-tickets`)
   - Today's Tickets (`helpdesk-tickets.today`)
   - All Tickets (`helpdesk-tickets.index`)
   - Categories (`helpdesk-categories.index`)
5. **Subscription** (`manage-plans`)
   - Subscription Setting / Plans (`plans.index`)
   - Coupons (`coupons.index`)
   - Bank Transfer Requests (`bank-transfer.index`)
   - Orders (`orders.index`)
6. **Email Templates** (`email-templates.index`, `manage-email-templates`)
7. **Notification Templates** (`notification-templates.index`, `manage-notification-templates`)
8. **Media Library** (`media-library`, `manage-media`)
9. **Add-ons Manager** (`add-ons.index`, `manage-add-on`)
10. **Settings** (`settings.index`, `manage-settings`)
    - Brand, System, Storage (Local/S3/Wasabi), Email (SMTP), Payment Gateways (Stripe/PayPal/Bank), Pusher, Cache, Cookie Consent, SEO.
