# Customer Launch Checklist

## 1. Account & Workspace
* [x] Self-service user registration enabled with bcrypt security.
* [x] Automatic transactional workspace provisioning with owner role.
* [x] Onboarding wizard (6 steps) guiding company and brand profile setup.
* [x] Load/Reset Demo Workspace Data available for sales demonstrations.

## 2. Billing & SaaS Plans
* [x] 4 standard plan tiers (Starter, Growth, Business, Agency) configured in DB.
* [x] 14-day free trial assigned automatically on registration.
* [x] Server-side plan limits enforced on users, automations, and missions.
* [x] Stripe/PayPal checkout flow and webhook handlers active.

## 3. Security & Access Control
* [x] Strict tenant isolation enforced on all Eloquent models via `workspace_id`.
* [x] Fine-grained RBAC with 9 pre-built role templates.
* [x] Role-aware privacy in Mr. Fox intelligence and executive health scores.
* [x] Unified Approval Center governing high-risk AI and automation actions.
* [x] Zero real production secrets committed in git repository.

## 4. Integrations
* [x] Unified Communications Inbox connecting Gmail, WhatsApp, and Slack.
* [x] Connection test endpoints with graceful error handling and status badges.
* [x] Brand Profiles feeding context to automated draft generation.

## 5. Operations & Monitoring
* [x] Background queue workers and Supervisor configuration ready.
* [x] Scheduled cron jobs defined for communications sync and invoice aging.
* [x] `/health/live` and `/health/ready` endpoints configured for load balancers.
* [x] `php artisan hiddenleaf:check` command available for pre-flight verification.
* [x] Automated backup and restore procedures documented.
