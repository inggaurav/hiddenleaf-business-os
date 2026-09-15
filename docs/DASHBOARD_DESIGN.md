# HiddenLeaf BusinessOS — Dashboard Design

> **Scope:** The main workspace dashboard and every module dashboard (HRM, Accounting, Sales, CRM, POS, Inventory, Procurement, Taskly)  
> **Read `DESIGN.md` first** — this document only covers dashboard-specific layout. All component, color, and typography rules come from DESIGN.md.

---

## 1. The problem this fixes

The current `Dashboard.tsx` is 350 lines showing everything at once: four metric cards, an AI insights grid, a six-month bar chart, a CRM funnel, Taskly stats, eight module cards, and an audit log. No user processes that. It reads as a report, not a workspace.

**The rule going forward:** a dashboard answers one question — *what needs my attention right now?* Everything else lives one click away in the module it belongs to.

---

## 2. Dashboard hierarchy

There are exactly two kinds of dashboard. Do not invent a third.

| Type | Route | Purpose | Max sections |
|---|---|---|---|
| **Workspace dashboard** | `/dashboard` | Cross-module attention triage. Where the owner lands each morning. | 4 |
| **Module dashboard** | `/hrm`, `/accounting`, `/crm` … | Operational state of one domain. Where a specialist works. | 5 |

---

## 3. Workspace dashboard (`/dashboard`)

### Layout

```
┌───────────────────────────────────────────────────────┐
│  Good morning, Gaurav          Bhopal HQ · Sep 14     │
│                                        [Ask Mr. Fox]  │
├───────────────────────────────────────────────────────┤
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐     │
│  │ Cash    │ │ Revenue │ │ Payable │ │ Overdue │     │
│  │ 14.2L   │ │ 8.4L    │ │ 3.1L    │ │ 3       │     │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘     │
├───────────────────────────────────────────────────────┤
│  NEEDS ATTENTION                    ┌───────────────┐ │
│  ○ 3 invoices overdue 14+ days      │ Mr. Fox       │ │
│  ○ 6 items below reorder point      │               │ │
│  ○ Payroll run PAY-2026-09 in draft │ Two things    │ │
│  ○ 4 leave requests pending         │ worth looking │ │
│                                     │ at today…     │ │
│                                     │               │ │
│                                     │ [Open chat]   │ │
│                                     └───────────────┘ │
├───────────────────────────────────────────────────────┤
│  YOUR MODULES                                         │
│  Accounting  Sales  Inventory  HRM  CRM  POS          │
│  (compact grid — icon, name, one live number)         │
└───────────────────────────────────────────────────────┘
```

### Section 1 — Greeting bar

Not a `SectionHeader`. A lighter treatment:

```tsx
<div className="flex items-end justify-between pb-5 border-b border-[var(--border-subtle)]">
  <div>
    <h1 className="text-xl font-bold text-[var(--text-primary)]">
      Good morning, {firstName}
    </h1>
    <p className="text-sm text-[var(--text-secondary)] mt-0.5">
      {workspaceName} · {formattedDate}
    </p>
  </div>
  <Button variant="intelligence" size="sm" icon={<Sparkles className="w-3.5 h-3.5" />}>
    Ask Mr. Fox
  </Button>
</div>
```

Greeting changes by hour: morning / afternoon / evening. No emoji.

### Section 2 — Four metrics, no more

Exactly four `MetricCard`s in `grid-cols-2 lg:grid-cols-4 gap-4`. These four are fixed regardless of which modules are active — they're the numbers every business owner checks:

| Metric | Source | Format |
|---|---|---|
| Cash position | `accounting_cash_position` | ₹ short form (14.2L) |
| Revenue this month | recognized sales | ₹ short form |
| Payables due 30d | vendor bills | ₹ short form |
| Overdue invoices | count | integer |

If a module isn't active, the card shows `—` with a "Enable Accounting" link, not a zero. Zeros lie.

**Indian number formatting:** use lakh/crore short form in metric cards (`₹14.2L`, `₹1.4Cr`), full digits in tables (`₹14,28,400`).

### Section 3 — Needs attention + Mr. Fox

Two-column, `lg:grid-cols-3` with attention taking 2 columns and Mr. Fox taking 1.

**Needs attention list:**
- Plain rows, not cards. `divide-y divide-[var(--border-subtle)]`
- Each row: status dot, text, right-aligned action link
- Dot color: amber for pending, rose for overdue, blue for informational
- Maximum 6 items. If there are more, the last row reads "12 more items →"
- Empty state: "Nothing needs your attention." with a green check — this is a good outcome, present it as one

```tsx
<div className="divide-y divide-[var(--border-subtle)]">
  {items.map(item => (
    <Link href={item.href} className="flex items-center justify-between py-3 group">
      <div className="flex items-center gap-3 min-w-0">
        <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${dotColor(item.severity)}`} />
        <span className="text-sm text-[var(--text-primary)] truncate">{item.label}</span>
      </div>
      <ChevronRight className="w-4 h-4 text-[var(--text-tertiary)] group-hover:text-[var(--text-secondary)]" />
    </Link>
  ))}
</div>
```

**Mr. Fox panel:**
- `<Card level={2}>` — the purple glass card, used nowhere else on this page
- Shows 2–3 sentences of generated insight, not a list
- One button: `Open chat`
- If insights haven't loaded, show a skeleton, not a spinner

### Section 4 — Module grid

Compact. Each tile shows the module name and one live number — not a description paragraph.

```tsx
<div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
  {modules.map(m => (
    <Link href={m.href}>
      <div className="p-4 rounded-xl bg-[var(--surface-1)] border border-[var(--border-subtle)] hover:border-[var(--border-medium)] spring-transition">
        <div className="flex items-center gap-2.5 mb-2">
          <m.icon className="w-4 h-4 text-[var(--text-tertiary)]" />
          <span className="text-sm font-medium text-[var(--text-primary)]">{m.name}</span>
        </div>
        <div className="text-lg font-bold tabular-nums text-[var(--text-primary)]">
          {m.value}
        </div>
        <div className="text-xs text-[var(--text-tertiary)]">{m.valueLabel}</div>
      </div>
    </Link>
  ))}
</div>
```

Module icons are all `--text-tertiary` — no per-module color coding. Color-coded module icons were part of why the old dashboard felt noisy.

### What was removed and where it went

| Removed from `/dashboard` | Now lives at |
|---|---|
| 6-month revenue bar chart | `/accounting/dashboard` |
| CRM lead funnel | `/crm/dashboard` |
| Taskly project stats | `/taskly/dashboard` |
| Audit log feed | `/settings/audit` |
| Module description paragraphs | Module tiles show a number instead |
| AI insights grid (6 cards) | Condensed to one Mr. Fox summary panel |

---

## 4. Module dashboards

Every module dashboard follows the same five-section skeleton. This consistency is the point — an accountant switching to HRM should not have to relearn the page.

```
1. SectionHeader        title + description + primary action
2. Metric row           4 metrics max, grid-cols-2 lg:grid-cols-4
3. Primary work surface The main table or board for this module
4. Secondary panel      Optional — pending items, recent activity
5. Quick links          Optional — sub-sections of this module
```

### Example — HRM dashboard (`/hrm`)

```tsx
<AppShell title="HRM">
  <Head title="HRM — Dashboard" />
  <div className="space-y-6">

    {/* 1 */}
    <SectionHeader
      title="HRM"
      description="Workforce, payroll, and hiring."
      actions={<Button size="sm" icon={<UserPlus />}>Add employee</Button>}
    />

    {/* 2 — four metrics */}
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <MetricCard title="Active employees" value="247" icon={<Users />} />
      <MetricCard title="Present today" value="231" trend="94%" trendDirection="up" icon={<UserCheck />} />
      <MetricCard title="On leave" value="8" icon={<CalendarOff />} />
      <MetricCard title="Open positions" value="5" icon={<Briefcase />} />
    </div>

    {/* 3 + 4 */}
    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div className="lg:col-span-2">
        <Card padded={false}>
          <CardHeader title="Needs action" subtitle="Items waiting on you" />
          <CardBody>{/* attention list */}</CardBody>
        </Card>
      </div>
      <Card padded={false}>
        <CardHeader title="This month" />
        <CardBody>{/* payroll status, new joiners, exits */}</CardBody>
      </Card>
    </div>

    {/* 5 */}
    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
      {/* Recruitment, Onboarding, Payroll, Training … */}
    </div>
  </div>
</AppShell>
```

### Per-module metric sets

Each module picks exactly four. These are fixed — don't let them drift.

| Module | Metrics |
|---|---|
| **HRM** | Active employees · Present today · On leave · Open positions |
| **Accounting** | Cash position · Receivables · Payables · Net margin |
| **Sales** | Revenue MTD · Invoices sent · Paid · Overdue |
| **CRM** | Open leads · Pipeline value · Deals won MTD · Conversion rate |
| **Inventory** | Stock value · Low stock items · Out of stock · Transfers pending |
| **POS** | Sales today · Transactions · Average bill · Returns |
| **Procurement** | Open POs · Bills due · Vendors · Spend MTD |
| **Taskly** | Active projects · Open tasks · Overdue · Hours logged |

---

## 5. Rules that apply to every dashboard

### Never do
- More than 4 metric cards in the top row
- More than 5 sections on one dashboard
- A chart on the workspace dashboard (charts belong in module dashboards and reports)
- Long description paragraphs on module tiles
- Per-module accent colors on icons
- A "welcome" or "getting started" card that never dismisses
- Zeros where the real answer is "module not enabled"

### Always do
- Show `—` and an enable link when a module is off
- Use `tabular-nums` on every figure
- Make every attention-list row clickable to the exact record
- Provide an empty state with an action, not just "no data"
- Load metrics server-side in the Inertia response — no client-side fetch waterfall on the primary numbers

### Loading behaviour
Metrics come from the controller in the initial Inertia payload — they render immediately. Only the Mr. Fox insights panel fetches client-side, and it shows a skeleton while loading. Never show a full-page spinner on a dashboard.

### Empty workspace
A brand-new workspace with no data shows a different dashboard entirely: a short setup checklist with 4 steps (add your company details, enable your first module, invite your team, import your data). Once any module has data, the normal dashboard takes over permanently.

---

## 6. Indian formatting standards

| Context | Format | Example |
|---|---|---|
| Metric card value | Lakh/crore short | `₹14.2L`, `₹1.4Cr` |
| Table cell | Full Indian grouping | `₹14,28,400` |
| Percentage | One decimal | `94.2%` |
| Date | Short, no year if current | `14 Sep`, `14 Sep 2025` |
| Time | 12-hour with am/pm | `2:30 pm` |
| Employee count | Plain integer | `247` |

Helper functions live in `resources/js/lib/format.ts`:
```ts
formatINR(1428400)        // "₹14,28,400"
formatINRShort(1428400)   // "₹14.2L"
formatDate(date)          // "14 Sep"
formatPercent(0.942)      // "94.2%"
```

Use these everywhere. Never call `Intl.NumberFormat` with `en-US` and `USD` — the current `Dashboard.tsx` does this and it's wrong for the market.

---

## 7. Migration checklist

- [ ] Replace `Dashboard.tsx` with the 4-section workspace layout
- [ ] Move revenue chart to `/accounting/dashboard`
- [ ] Move CRM funnel to `/crm/dashboard`
- [ ] Move audit log to `/settings/audit`
- [ ] Condense AI insights grid into one Mr. Fox panel
- [ ] Replace `formatCurrency` USD with `formatINR` / `formatINRShort`
- [ ] Delete `ModuleDashboard.tsx` usage from `HRM/Index.tsx` and `CRM/Index.tsx`
- [ ] Build the eight module dashboards using the five-section skeleton
- [ ] Add the empty-workspace setup checklist variant
- [ ] Remove per-module icon colors from the module grid
