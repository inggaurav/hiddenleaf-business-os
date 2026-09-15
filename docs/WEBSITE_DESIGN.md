# HiddenLeaf BusinessOS — Marketing Website Design

> **Target:** `hiddenleaf.in` · Public marketing + plugin marketplace  
> **Audience:** Indian SMB owners (5–200 employees) in tier-1/2 cities, and agencies reselling to them  
> **Primary job:** Convince a business owner that one system can replace their 6 disconnected tools, then get them to book a demo or start a trial.

---

## 1. Positioning — what the site must communicate

The visitor is a business owner in Bhopal, Indore, Pune, or Nagpur running an operation with Tally for accounting, WhatsApp for sales, Excel for inventory, and a notebook for HR. They have been pitched Zoho and SAP. Zoho felt like ten separate products. SAP quoted them ₹15 lakh.

The site's job is not to sell "an ERP." It's to sell **one system where your business data already lives, and an AI that can actually read it.**

**Message hierarchy:**
1. One system, not ten tabs — accounting, sales, inventory, HR, POS in one place
2. Mr. Fox reads your real data — not a chatbot bolted on, an agent with 73 tools wired to your ledger
3. Buy only what you need — modules install as plugins, you pay for what you activate
4. Built for Indian compliance — GST, TDS, PF, ESI (once shipped)
5. Run it on your own server if you want — local LLM support, data never leaves your building

---

## 2. Design direction

### The core idea: **instrument panel, not brochure**

Most ERP marketing sites show smiling stock-photo teams pointing at laptops. HiddenLeaf should look like the product — dark, precise, data-dense. The hero should show a real interface fragment, not an illustration. A visitor should be able to tell within two seconds that this is a serious operational tool, not a landing page template.

**The one bold move:** the hero contains a live, self-running Mr. Fox conversation. Not a video, not a screenshot — an actual scripted terminal-style exchange that types itself out, showing a business question going in and real numbers coming out. Everything else on the page stays quiet.

### What we are explicitly avoiding
- Gradient mesh backgrounds
- Floating 3D shapes
- "Trusted by 10,000+ businesses" logo walls we don't have
- Stock photography of any kind
- Bento grids of identical rounded cards
- Numbered step markers (01 / 02 / 03) unless the content is genuinely sequential

---

## 3. Color

The marketing site inherits the product's dark palette so the transition from site to app is seamless.

```
Canvas          #060709   near-black, same as app --bg-0
Surface         #101318   section backgrounds
Card            #151921   raised panels
Border          rgba(255,255,255,0.07)

Text primary    #F3F4F6
Text secondary  #9CA3AF
Text tertiary   #6B7280

Fox purple      #8B5CF6   Mr. Fox only — CTA, agent UI, logo mark
Signal green    #10B981   live data, positive numbers, "active" states
Signal amber    #F59E0B   pending states in demo data
```

**Color discipline:** purple appears in exactly three places on the whole page — the logo mark, the primary CTA button, and the Mr. Fox response bubbles. Everywhere else is neutral grey with green for live numbers. This is what makes purple feel meaningful rather than decorative.

---

## 4. Typography

**Display + headings:** `Söhne` or `Inter Tight` — geometric, tight, technical. Falls back to system stack.  
**Body:** `Inter` at 400/500.  
**Numbers and code:** `JetBrains Mono` — used only inside the Mr. Fox demo and any figures that represent live data.

### Scale
| Role | Size | Weight | Tracking |
|---|---|---|---|
| Hero headline | 56px / 40px mobile | 700 | -0.03em |
| Section heading | 32px / 26px mobile | 600 | -0.02em |
| Sub-head | 20px | 500 | -0.01em |
| Body | 17px | 400 | 0 |
| Caption / label | 14px | 400 | 0 |
| Mono data | 15px | 400 | 0 |

Line length: 68 characters max for body copy. Hero headline is 3 lines maximum on desktop.

**No all-caps labels anywhere.** No eyebrow text above headings.

---

## 5. Page structure

```
┌─────────────────────────────────────────────────────┐
│ NAV   HiddenLeaf ·  Product  Modules  Pricing  Docs │
│                              [Sign in] [Book demo]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│  HERO — split, 55/45                                │
│  ┌──────────────────┐  ┌────────────────────────┐  │
│  │ Your business    │  │ ▸ Mr. Fox              │  │
│  │ runs on six      │  │                        │  │
│  │ different tabs.  │  │ > what's my cash       │  │
│  │                  │  │   position this month? │  │
│  │ HiddenLeaf is    │  │                        │  │
│  │ one system with  │  │ Reading ledger…        │  │
│  │ an AI that can   │  │                        │  │
│  │ actually read    │  │ ₹14,28,400 available   │  │
│  │ your data.       │  │ ₹3,10,200 in payables  │  │
│  │                  │  │ due within 14 days     │  │
│  │ [Book a demo]    │  │                        │  │
│  │  See modules →   │  │ ▸ 3 invoices overdue   │  │
│  └──────────────────┘  └────────────────────────┘  │
│                                                     │
├─────────────────────────────────────────────────────┤
│  THE PROBLEM — three columns, no cards              │
│  Tally doesn't talk to your CRM.                    │
│  Your inventory lives in a spreadsheet.             │
│  Nobody knows this month's real margin.             │
├─────────────────────────────────────────────────────┤
│  MODULES — horizontal scroll rail                   │
│  Accounting · Sales · Inventory · HRM · POS · CRM   │
│  Each: name, one line, "included / ₹X per month"    │
├─────────────────────────────────────────────────────┤
│  MR FOX SECTION — dark inset, full bleed            │
│  Split: left copy, right animated tool-call trace   │
│  "73 tools. Every one wired to your actual data."   │
├─────────────────────────────────────────────────────┤
│  COMPLIANCE — India-specific, quiet and factual     │
│  GST · TDS · PF · ESI · e-invoicing                 │
├─────────────────────────────────────────────────────┤
│  DEPLOYMENT — two options, side by side             │
│  Cloud (we host)  |  Self-hosted (your server,      │
│                       local LLM, data never leaves) │
├─────────────────────────────────────────────────────┤
│  PRICING — three tiers, module add-ons below        │
├─────────────────────────────────────────────────────┤
│  CTA — single line, one button                      │
├─────────────────────────────────────────────────────┤
│  FOOTER                                             │
└─────────────────────────────────────────────────────┘
```

---

## 6. Section-by-section specification

### Hero

**Layout:** Two column, 55/45 split on desktop. Stacks on mobile with the demo panel below the copy.

**Left column:**
- Headline, 3 lines, 56px, left-aligned, `--text-primary`
- Sub-paragraph, 17px, `--text-secondary`, max 2 sentences
- Primary CTA: `Book a demo` — purple fill, 44px height
- Secondary: `See modules` — text link with chevron, `--text-secondary`

**Right column — the Mr. Fox demo panel:**
- Card at `--surface-1`, `border-radius: 16px`, 1px `--border-subtle`
- Panel header: small purple dot + "Mr. Fox" in 14px
- Body: monospace, typed-out conversation
- Question appears in `--text-secondary`, response in `--text-primary`
- Numbers render in green `#10B981`
- Runs once on page load, then holds the final state. Does not loop.
- Respects `prefers-reduced-motion` — shows final state immediately

This is the one animated element on the entire page.

### The problem section

Three short statements, not cards. Just text in three columns with generous space between. Each is one sentence, 20px, `--text-secondary`, with the operative phrase in `--text-primary`.

No icons. No boxes. No borders. The restraint here is what makes the hero feel expensive.

### Modules rail

Horizontal scrolling rail on desktop, vertical stack on mobile. Each module is a narrow panel:
- Module name, 20px, 600
- One line of what it does, 15px, `--text-secondary`
- Price: "Included" or "₹1,200/month" in 14px mono
- A thin green line at the top if the module is live, amber if coming soon

The rail scrolls with visible overflow — the cut-off card on the right edge signals there is more.

### Mr. Fox section

Full-bleed dark inset (slightly darker than page: `#0B0D11`). This is the only section with a different background, which is what makes it read as the centrepiece.

**Left:** the argument in three short paragraphs. Key line: *"Every AI tool asks you to connect your data. Mr. Fox is already inside it."*

**Right:** a static tool-call trace, styled like a terminal log:
```
hr_payroll_run_summary        → PAY-2026-09-01  247 employees
accounting_cash_position      → ₹14,28,400
crm_pipeline_value            → ₹42,10,000 across 18 deals
inventory_low_stock           → 6 items below reorder point
```
Monospace, tool names in `--text-tertiary`, results in `--text-primary` with figures in green.

### Compliance section

Deliberately plain. A single row of labels with a short line under each. This section builds trust through specificity, not visual weight. If GST/TDS/PF/ESI aren't shipped yet, mark them honestly — "shipping Q4" in amber. Never claim compliance you don't have.

### Deployment section

Two panels side by side, equal weight. Cloud on the left, self-hosted on the right. The self-hosted panel gets a subtle green border because that's the differentiator most competitors can't match.

### Pricing

Three tiers as columns. The middle tier is marked with a 2px purple border — the only place other than the CTA where purple appears outside the Fox context, and it's justified because it's directing a decision.

Below the tiers: a simple table of module add-on prices. Plain rows, no cards.

---

## 7. Responsive rules

| Breakpoint | Behaviour |
|---|---|
| `< 640px` | Single column throughout. Hero demo panel moves below copy. Modules rail becomes vertical stack. Nav collapses to hamburger. |
| `640–1024px` | Two column where content allows. Modules rail scrolls horizontally. |
| `> 1024px` | Full layout as specified. Max content width 1200px, centered. |

Hero headline drops from 56px to 40px below 768px. Body stays 17px everywhere — do not shrink body text on mobile.

---

## 8. Motion

**One orchestrated moment:** the Mr. Fox hero demo typing on page load. Duration 4 seconds total, then holds.

Everything else:
- Link and button hover: 150ms color transition only, no transforms
- No scroll-triggered fade-ins
- No parallax
- No card hover lifts

`@media (prefers-reduced-motion: reduce)` disables the typing animation and shows the completed state.

---

## 9. Copy voice

Plain, direct, specific to Indian SMB reality. Use rupees, not dollars. Use real business situations.

**Good:**
> "Your accountant closes the books on the 12th. By then the month is already gone."

**Bad:**
> "Unlock powerful insights with our AI-driven business intelligence platform."

**Rules:**
- Never use: leverage, seamless, unlock, empower, revolutionize, game-changing
- Sentence case for all headings and buttons
- Buttons say what happens: "Book a demo", not "Get started"
- Numbers in copy are always real or clearly labelled as example data

---

## 10. Build notes

- Static site or Next.js — does not need to share the app's React codebase
- Reuse the app's CSS custom properties so the visual identity stays consistent
- Hero demo: plain JS typing effect, no library
- Total page weight target: under 400KB, no web fonts above 2 families
- Lighthouse: 95+ on performance, 100 on accessibility
