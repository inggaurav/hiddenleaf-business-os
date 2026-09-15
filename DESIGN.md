# HiddenLeaf BusinessOS — Frontend Design System

> **Version:** 1.0 · **Stack:** React 19 + Inertia + Tailwind 4 + CSS Variables  
> **Last updated:** September 2026  
> **Rule:** If it's not in this document, don't invent it. Ask first.

---

## 1. Design Philosophy

HiddenLeaf BusinessOS is a professional ERP used by business owners and finance teams — not a consumer product. The UI must feel **precise, calm, and trustworthy**. Every visual decision serves legibility and speed, not aesthetics.

**Three principles:**

1. **Data over decoration.** Numbers are the product. A payroll run table or a recruitment pipeline must be scannable in under 3 seconds. No gradients, animations, or chrome that competes with data.
2. **Neutral surfaces, purposeful color.** Color is reserved for status signals (success/warning/danger) and MrFox AI identity (purple). Everything else is neutral.
3. **Consistent density.** ERP users switch between modules dozens of times per day. Every page uses the same spatial rhythm so nothing feels unfamiliar.

---

## 2. Color System

All colors are CSS custom properties. **Never hardcode hex values in components.**

### Background layers (dark mode default)
```
--bg-0: #060709          ← page canvas
--bg-1: #0B0D11          ← subtle tint
--bg-2: #101318          ← section dividers
--surface-1: #151921     ← cards, panels
--surface-2: #1B202B     ← card headers, elevated rows
--surface-3: #222937     ← tooltips, dropdowns
```

### Border scale
```
--border-subtle: rgba(255,255,255,0.07)   ← default card border
--border-medium: rgba(255,255,255,0.12)   ← hover, focus borders
--border-strong: rgba(255,255,255,0.20)   ← active states
--border-highlight: rgba(255,255,255,0.35) ← drag, selected
```

### Text scale
```
--text-primary: #F3F4F6    ← headings, values
--text-secondary: #9CA3AF  ← labels, descriptions
--text-tertiary: #6B7280   ← hints, placeholders, metadata
--text-disabled: #4B5563   ← disabled states
```

### Semantic colors
```
--success: #10B981    green  ← active, paid, approved, completed
--warning: #F59E0B    amber  ← pending, draft, in_progress
--danger:  #EF4444    red    ← rejected, failed, overdue, terminated
--info:    #3B82F6    blue   ← neutral informational
```

### Brand — MrFox only
```
--brand-primary: #8B5CF6      ← MrFox button, active nav, logo
--brand-glow: rgba(139,92,246,0.25)
--fox-purple: #A855F7
--fox-amber:  #F59E0B
--fox-cyan:   #06B6D4
```

> **Rule:** Purple (`--brand-primary`) is reserved for MrFox AI identity and active navigation state. Do NOT use purple for regular actions, primary buttons, or data visualization. Use neutral (`--text-primary`) or semantic colors instead.

### Light mode
Light mode automatically flips all CSS variables. Components written using `var(--*)` tokens work in both modes with zero extra code.

---

## 3. Typography

**Font stack:** `-apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Segoe UI", Roboto, sans-serif`

### Scale
| Role | Size | Weight | Line height | Usage |
|---|---|---|---|---|
| Page title | 20px / `text-xl` | 700 | 1.2 | One per page, `SectionHeader` |
| Section title | 16px / `text-base` | 600 | 1.3 | Card headings |
| Body | 14px / `text-sm` | 400 | 1.5 | Table rows, form labels, paragraphs |
| Caption | 12px / `text-xs` | 400 | 1.4 | Metadata, timestamps, hints |
| Micro | 10px / `text-[10px]` | 500 | 1.3 | Badge labels only — nowhere else |
| Metric value | 28px / `text-3xl` | 700 | 1 | KPI numbers in MetricCard |

> **Rule:** No text below 12px except inside Badge components. Navigation items must be 13px minimum (`text-[13px]`), not `text-xs`. Sidebar group labels must be 11px with `font-medium` — never `text-[10px]` uppercase.

### Numeric display
All financial and metric numbers use `font-variant-numeric: tabular-nums` (class: `tabular-nums`). This prevents layout shift as numbers update.

---

## 4. Spacing System

Use Tailwind's 4px base unit consistently.

| Token | px | Usage |
|---|---|---|
| `gap-1` | 4px | Icon gap inside buttons |
| `gap-2` | 8px | Label-to-input gap |
| `gap-3` | 12px | Between form fields in a row |
| `gap-4` | 16px | Between cards in a grid |
| `gap-6` | 24px | Between page sections |
| `p-4` | 16px | Card padding (mobile) |
| `p-5 sm:p-6` | 20–24px | Card padding (default, from Card component) |
| `px-4 sm:px-6` | 16–24px | Page horizontal padding |
| `py-2.5` | 10px | Table row vertical padding |

> **Rule:** Page `main` element uses `p-4 sm:p-6 md:p-8`. Never go above `p-8` for page padding.

---

## 5. Component Specifications

### Button

```tsx
<Button variant="neutral" size="sm">Save</Button>
<Button variant="danger" size="sm" icon={<Trash />}>Delete</Button>
<Button variant="intelligence" size="sm">Ask Mr. Fox</Button>
```

| Variant | Use | Color |
|---|---|---|
| `neutral` | Default action — create, save, filter | `--surface-2` bg, `--text-primary` text |
| `outline` | Secondary / cancel | transparent, `--border-medium` border |
| `danger` | Destructive — delete, reject, terminate | `#DC2626` bg |
| `ghost` | Table row actions, icon buttons | transparent hover only |
| `intelligence` | MrFox AI actions only | purple gradient |

> **Changed from old system:** The old `primary` variant was purple gradient and was used everywhere. This is now `neutral`. Purple gradient is `intelligence` and only for MrFox. This removes the purple overload problem.

**Sizes:** `sm` (12px text, 28px height), `md` (14px text, 36px height), `lg` (16px text, 44px height)

---

### Card

Three elevation levels matching the glass hierarchy:

```tsx
<Card>...</Card>          {/* level 0 — standard card */}
<Card level={1}>...</Card> {/* level 1 — slightly elevated, for sidebars */}
<Card level={2}>...</Card> {/* level 2 — glass purple tint, for AI/MrFox sections */}
```

**Card anatomy:**
- `padded` prop (default `true`): `p-5 sm:p-6`
- `rounded-2xl` border radius always
- `border border-[var(--border-subtle)]` always
- No box shadows (the glass hierarchy provides depth)

**Card with header pattern:**
```tsx
<Card padded={false}>
  <div className="px-5 py-4 border-b border-[var(--border-subtle)]">
    <h3 className="text-sm font-semibold text-[var(--text-primary)]">Title</h3>
    <p className="text-xs text-[var(--text-tertiary)] mt-0.5">Subtitle</p>
  </div>
  <div className="p-5">
    {/* content */}
  </div>
</Card>
```

---

### MetricCard

Updated to show larger values and clearer hierarchy:

```tsx
<MetricCard
  title="Active Employees"
  value="247"
  trend="+3 this month"
  trendDirection="up"
  icon={<Users className="w-4 h-4" />}
/>
```

- Value: `text-3xl font-bold tabular-nums` (28px — was 24px)
- Title: `text-xs font-medium uppercase tracking-wide text-[var(--text-tertiary)]`
- Trend: `text-xs` with emerald (up) / rose (down) / tertiary (neutral)
- Icon: 36px square background, neutral surface — not color-coded by module

---

### Badge & StatusBadge

```tsx
<Badge variant="success">Paid</Badge>
<Badge variant="warning" dot>Pending</Badge>
<StatusBadge status={employee.status} />
```

**StatusBadge auto-maps:**
- `active`, `paid`, `approved`, `completed`, `hired` → `success` (green)
- `pending`, `draft`, `in_progress`, `screening` → `warning` (amber)
- `rejected`, `failed`, `terminated`, `closed` → `danger` (red)
- `applied`, `open` → `info` (blue)
- Everything else → `neutral`

---

### Input & Select

Both components use `--surface-1` background, `--border-subtle` border, and `--brand-primary` focus ring. Always use the component wrappers — never raw `<input>` or `<select>` tags in pages.

```tsx
<Input label="Employee Name" placeholder="Rajesh Kumar" error={errors.name} />
<Select label="Department" options={departments} error={errors.department_id} />
```

---

### DataTable

```tsx
<DataTable
  data={employees.data}
  columns={columns}
  searchable
  searchPlaceholder="Search employees..."
  headerActions={<Button size="sm">Add Employee</Button>}
  emptyTitle="No employees yet"
  emptyDescription="Add your first employee to get started."
/>
```

**Column definition:**
```tsx
const columns: Column<Employee>[] = [
  { header: 'Name', accessorKey: 'name', mobileRole: 'primary' },
  { header: 'Department', accessorKey: 'department', mobileRole: 'secondary' },
  { header: 'Status', accessorKey: 'status', render: (row) => <StatusBadge status={row.status} />, mobileRole: 'status' },
  { header: 'Actions', render: (row) => <Button variant="ghost" size="sm">View</Button>, mobileRole: 'action' },
];
```

`mobileRole` controls responsive collapse: `primary` always shows, `secondary` hides below md, `status` shows as pill, `hidden` never shows on mobile.

---

### Modal

```tsx
<Modal isOpen={open} onClose={() => setOpen(false)} title="Add Employee" maxWidth="lg">
  <form>...</form>
</Modal>
```

- Always use Modal for create/edit forms — not inline expanded sections
- `maxWidth="md"` for simple forms (< 4 fields)
- `maxWidth="lg"` for complex forms (4–8 fields)
- `maxWidth="2xl"` for multi-step forms or detail views

---

### SectionHeader

One per page, at the top:

```tsx
<SectionHeader
  title="Employees"
  description="Manage workforce directory and compensation."
  actions={
    <Button size="sm" variant="neutral" icon={<UserPlus />} onClick={() => setOpen(true)}>
      Add employee
    </Button>
  }
/>
```

- `title`: sentence case, no trailing punctuation
- `description`: one line, present tense, 60 chars max
- `actions`: right-aligned, max 2 buttons

---

## 6. Page Layout Rules

### Standard page structure

```tsx
<AppShell title="HRM — Employees">
  <Head title="HRM — Employees" />

  {/* Flash messages render automatically in AppShell */}

  <div className="space-y-6">
    <SectionHeader title="..." description="..." actions={...} />

    {/* Optional: metric row */}
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <MetricCard ... />
    </div>

    {/* Main content */}
    <Card>
      <DataTable ... />
    </Card>
  </div>
</AppShell>
```

### Grid system
- Metrics: `grid-cols-2 lg:grid-cols-4` (always 2-column on mobile)
- Cards: `grid-cols-1 lg:grid-cols-3` for dashboard-style layouts
- Forms: `grid-cols-1 md:grid-cols-2` (never 3-col on mobile)
- Full width: `col-span-full` or no grid

### Page padding
AppShell handles this: `p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto`. Pages should not add their own outer padding.

---

## 7. Navigation Rules

### Sidebar
- Group labels: `text-[11px] font-medium uppercase tracking-wider text-[var(--text-tertiary)]`
- Nav items: `text-[13px] font-medium` (not `text-xs`)
- Active state: `bg-[var(--brand-primary)]/10 text-[var(--text-primary)] border border-[var(--brand-primary)]/20`
- Inactive: `text-[var(--text-secondary)] hover:text-[var(--text-primary)] hover:bg-white/[0.04]`
- All child items collapse by default — only the active module's group expands
- Max 2 levels of nesting — any deeper belongs in a tab on the page itself

### Header
- MrFox button: `bg-[var(--brand-primary)]/10 text-[var(--fox-purple)] border border-[var(--brand-primary)]/30` — the only purple element in the header
- All other header actions: neutral ghost or outline

---

## 8. Forms

### Rules
- Always use `<Modal>` for create/edit — never inline expanding divs
- Always use `Input`, `Select`, `Textarea` components — never bare HTML inputs
- Always show validation errors from Inertia's `errors` prop via the `error` prop on inputs
- Required fields: add `required` to the input — no asterisk (*) in labels (it's noise)
- Destructive actions (delete, terminate, reject): always require a confirm step — either a `window.confirm()` or a confirmation Modal

### Submit pattern
```tsx
const { post, processing, errors } = useForm({ name: '', email: '' });

<Button
  type="submit"
  variant="neutral"
  loading={processing}
>
  Save employee
</Button>
```

Use Inertia's `useForm` hook for all form state. Never use `useState` + manual `router.post()` for forms with more than 2 fields.

---

## 9. What NOT to do

| Don't | Do instead |
|---|---|
| `className="bg-purple-600 text-white"` on regular buttons | `<Button variant="neutral">` |
| `className="text-xs uppercase tracking-wider"` on nav items | Use `text-[13px]` for nav |
| Inline `style={{}}` for colors | Use `var(--*)` tokens |
| Raw `<input>` or `<select>` in pages | Use `<Input>` or `<Select>` |
| `bg-purple-*` anywhere except MrFox | Use `var(--brand-primary)` for MrFox only |
| Long description text in `SectionHeader` | Max 60 chars, present tense |
| `ModuleDashboard` generic component for real pages | Build real pages with `DataTable` |
| `text-[10px]` or smaller anywhere except Badge | Minimum 12px everywhere |
| Expanding inline form sections | Use `<Modal>` |
| Dashboard with 8+ sections visible at once | Split into tabs or module dashboards |

---

## 10. MrFox Identity Rules

MrFox has its own visual language. When anything touches MrFox AI:

- Background: `level={2}` Card (the purple-tinted glass)
- Button variant: `intelligence`
- Text accent: `text-[var(--fox-purple)]`
- Badge: `<Badge variant="purple">`
- Icon: always `<Sparkles />` from lucide

Everything else in the product is neutral. Purple = MrFox. Nowhere else.

---

## 11. File naming and location

| What | Where |
|---|---|
| Shared UI primitives | `resources/js/Components/UI/` |
| Layout components | `resources/js/Layouts/` |
| Navigation | `resources/js/Navigation/` |
| MrFox specific | `resources/js/Components/MrFox/` |
| HRM pages | `resources/js/Pages/HRM/{Module}/` |
| HRM plugin pages | `hrm-plugin/resources/js/Pages/HRM/{Module}/` |

Both page locations must stay in sync — the plugin pages are the canonical source, core pages are copied during build.

---

## 12. Antigravity prompts — required preamble

When giving a task to Antigravity involving any UI work, start with:

```
Before writing any component or page, read DESIGN.md in full.
Follow every rule there exactly. Key rules:
- Button variant "neutral" replaces old "primary" for regular actions
- "intelligence" variant is only for MrFox AI buttons
- Never use purple outside MrFox AI context
- Use Input/Select/Textarea components, never raw HTML inputs
- Use Modal for all create/edit forms
- DataTable for all list views
- MetricCard value at text-3xl (28px), not text-2xl
- Nav items text-[13px], not text-xs
- Check DESIGN.md section 9 "What NOT to do" before finishing
```
