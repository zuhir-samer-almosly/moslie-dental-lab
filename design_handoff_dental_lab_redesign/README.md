# Handoff: Dental Lab Redesign (مخبر الموصلي)

## Overview
Visual redesign of three pages of the **moslie-dental-lab** app (Laravel 12 + Inertia + React 19 + Tailwind v4 + shadcn/ui, Arabic RTL):

1. **Dashboard** (`resources/js/pages/dashboard.tsx`)
2. **Orders list** (`resources/js/pages/orders/index.tsx`)
3. **New order form** (`resources/js/components/order-form.tsx` + `resources/js/components/dental-chart.tsx`)

The goal is a calmer, more clinical look with a single teal accent replacing the current multi-color (blue/amber/violet/emerald/rose) tone system, slightly larger type, and softer card styling. **The page structure, routes, data shape, and component logic all stay exactly as they are** — this is a re-skin plus a few small layout upgrades, not a rewrite.

## About the Design Files
The `.dc.html` files in this bundle are **design references created in HTML** — prototypes showing intended look and behavior, NOT production code to copy. Recreate them in the existing codebase using its established patterns: Tailwind utility classes, the shadcn/ui components in `resources/js/components/ui/`, lucide-react icons, and the CSS-variable theme in `resources/css/app.css`.

## Fidelity
**High-fidelity.** Colors, spacing, radii, and typography are final. Match them exactly (via the theme variables below, not hardcoded hex in JSX).

## Recommended implementation route
Change the theme variables in `resources/css/app.css` first — that alone moves most of the app toward this design:

```css
:root {
    --background: #F6F8F8;          /* page background */
    --foreground: #122A27;          /* main text */
    --card: #FFFFFF;
    --card-foreground: #122A27;
    --primary: #0F766E;             /* teal accent — buttons, active nav */
    --primary-foreground: #FFFFFF;
    --secondary: #EFF5F4;           /* teal-tinted surface */
    --secondary-foreground: #0F766E;
    --muted: #F3F7F6;
    --muted-foreground: #6B807C;
    --border: #E3EAE9;
    --input: #DCE4E3;
    --ring: #0F766E;
    --destructive: #BE123C;
    --radius: 0.75rem;              /* was 0.625rem — cards use 16px, inputs 10px */
    --sidebar: #FFFFFF;
    --sidebar-foreground: #435955;
    --sidebar-primary: #0F766E;
    --sidebar-primary-foreground: #FFFFFF;
    --sidebar-accent: #EFF5F4;
    --sidebar-accent-foreground: #0F766E;
    --sidebar-border: #EDF2F1;
}
```

Then apply the per-page changes below.

## Design Tokens

### Colors
| Role | Hex |
|---|---|
| Accent / primary (teal) | `#0F766E` (hover `#0C5F59`) |
| Teal tint surface | `#EFF5F4` (rows hover `#F3F7F6`) |
| Page background | `#F6F8F8` |
| Card | `#FFFFFF` |
| Border | `#E3EAE9` (inner dividers `#EDF2F1`) |
| Text primary | `#122A27` |
| Text secondary | `#435955` |
| Text muted | `#6B807C` |
| Income / positive | `#047857` on tint `#E5F5EE` |
| Expense / destructive | `#BE123C` on tint `#FDECEE` |
| Outstanding / pending | `#B45309` on tint `#FEF3E2` |
| Delivered status | `#1D4ED8` on tint `#E8EEFC` |

Semantic rule: teal is the only "brand" color. Green = money in, red = money out/delete, amber = pending/outstanding, blue = delivered. No violet.

### Typography
- Font: `IBM Plex Sans Arabic` (already the app font) — weights 400/500/600/700.
- Page title: 26px / 700. Card title: 16px / 700. Body: 15px. Secondary/meta: 12–13px. Big stat values: 32px / 700 with `tabular-nums` and `letter-spacing: -0.5px`. Mini-stat values: 20px / 700.

### Spacing & shape
- Cards: radius 16px, `1px solid #E3EAE9`, **no shadow** (shadow only on the primary CTA: `0 2px 6px rgba(15,118,110,0.25)`).
- Card padding: 22–24px. Grid gaps: 16px. Section gap on page: 24–28px.
- Inputs/selects: padding `12px 14px`, radius 10px, border `#DCE4E3`.
- Primary button: teal bg, radius 12px, padding `13px 22px`, 15px/600.
- Icon chips: 40–42px square, radius 12px, tinted bg + colored icon (e.g. `#E5F5EE` + `#047857`).
- Status badges: pill (radius 99px), 12px/600, `4px 12px`, tint bg + dark tone text.

## Screens

### 1. Sidebar (all pages) — `app-sidebar.tsx`
- Keep `side="right"`, same nav items and lucide icons.
- White bg, `border-left: 1px solid #E3EAE9` (RTL), width ~264px.
- Header: 42px teal rounded-12 square with white tooth icon + lab name 17px/700 + subtitle "إدارة مالية المختبر" 12px muted.
- Items: padding `11px 12px`, radius 10px, 15px. Active: solid teal bg, white text, 600. Hover: `#EFF5F4` bg, teal text.
- Footer user row: 36px circle avatar `#E0F0EE` bg / teal initial letter, name + role "مدير المختبر".

### 2. Dashboard — `dashboard.tsx`
Keep the existing section order and props (`stats`, `recentOrders`, `recentPayments`). Changes:

- **Header**: greeting "مرحباً، {user} 👋" (26px/700) + subtitle "هذا ملخص أداء المختبر لشهر {month}". CTA "إضافة طلب جديد" = teal primary button (this replaces the separate ملخص heading row; the التفاصيل link can live on the net-profit card or stay as is).
- **MoneyCard ×3**: label row (15px muted) with icon chip on the far side; value 32px/700 tabular; hint 13px muted below.
  - الدخل: value + chip in green (`#047857` / `#E5F5EE`), trending-up icon.
  - المصروفات: red (`#BE123C` / `#FDECEE`), trending-down icon. Hint keeps the salary/materials/expenses breakdown.
  - صافي الربح: **inverted card** — solid `#0F766E` bg, white value, `#C7E5E1` label/hint, icon chip `rgba(255,255,255,0.15)`. (When net < 0 keep the white card with red value instead.)
- **MiniStat ×4**: unchanged layout (icon chip + value 20px/700 + label 13px). Outstanding uses amber chip/value; the other three use the teal tint chip (`#EFF5F4` / `#0F766E`) with neutral values. Hover: border turns teal.
- **Recent orders / payments**: same two-column cards. Header 18px padding, border-bottom `#EDF2F1`, "عرض الكل ←" link is teal 13px/600. Rows: 38px circular avatar — orders use teal tint with the dentist's first letter, payments use green tint with a credit-card icon. Status pill per the badge spec. Payment amounts green with `+` prefix.
- **Quick actions**: same 4 links, white cards with icon chips (order=teal, payment=green, salary=red, material=amber). Hover: teal border + `#F3F7F6` bg.

### 3. Orders list — `orders/index.tsx`
Keep the rowspan-grouped table logic (dentist/status/actions cells span all item rows). Changes:

- **Header**: title + "{n} طلبات في {month}" + teal CTA.
- **Toolbar** (one row, wraps): 
  - Month stepper: white pill container (radius 12, padding 6px) with two 36px ghost icon buttons + month label 15px/600 between them (replaces the outline-button + `<input type=month>` combo).
  - **NEW — search input**: white, radius 12, search icon, placeholder "ابحث باسم الطبيب أو المريض..." (client-side filter over dentist + patient names).
  - **NEW — status filter segmented control**: الكل / قيد الانتظار / مكتمل / تم التسليم; active segment = solid teal.
- **Table**: header row bg `#F8FAFA`, 13px/600 muted labels; cells `14px 12px` padding (18px at edges); row dividers `#EDF2F1` (lighter `#F3F7F6` between items of the same order); order-group cells keep a vertical `border-inline-start #EDF2F1`.
  - **Teeth column**: replace `TeethOdontogram` here with compact number chips — 26×24px, radius 6, `#EFF5F4` bg, teal 12px/600 tabular numbers (e.g. `14 15 16`). "—" muted when none.
  - Previous-balance note stays above the dentist name: 11px muted with the amount in amber 700.
  - Actions: two 34px square icon buttons radius 9 — edit (neutral border, teal on hover) and delete (red `#F5D5DB` border, `#FDECEE` bg on hover).
  - Status pills per badge spec (replaces the solid default `<Badge>`).
- **NEW — footer summary bar**: `#F8FAFA`, border-top, "{n} طلبات · {m} عناصر" right, "الإجمالي: {total} دينار" left (total 700).

### 4. New order form — `order-form.tsx` + `dental-chart.tsx`
Keep all existing logic (dentist price-list autofill, teeth-driven readonly quantity, item add/remove, running totals). Changes:

- **Header**: 40px back-arrow icon button (links to /orders) + title "إضافة طلب جديد" + breadcrumb line "الطلبات ← طلب جديد".
- Form column max-width ~920px, split into cards:
  - **Card "معلومات الطلب"**: dentist + status selects in a 2-col grid, notes textarea, and the price-autofill hint restyled as a teal info strip (`#EFF5F4` bg, radius 10, info icon, 13px teal text) — replaces the 💡 emoji line.
  - **Items header row**: "العناصر" 16px/700 + "إضافة عنصر" as teal *outline* button (teal border/text, `#EFF5F4` hover).
  - **Item card** (one per item): "عنصر {n}" label in teal 15px/700 + red delete icon button top-left; fields in 2-col grid (النوع, اسم المريض, التاريخ, الكمية, السعر, ملاحظات). Quantity shows "(حسب الأسنان المختارة)" hint and `#F3F7F6` readonly bg when teeth are selected. Subtotal line bottom-left, value 700.
  - **Total bar card**: "المجموع الكلي: {total} دينار" (total in teal 700) + submit "حفظ الطلب" teal button with check icon.
- **Dental chart** (`dental-chart.tsx`): keep the SVG tooth paths and all selection logic. Restyle only:
  - Container: radius 14, `#FBFCFC` bg, `#E3EAE9` border; header row "الأسنان (n مختارة)" + "مسح الكل" ghost link (red on hover).
  - Jaw quick-select buttons: white, radius 8, 12px/600, teal border+text on hover.
  - Tooth states: unselected crown fill `#EDF2F1` stroke `#B4C2BF`, roots `#C9D4D1`; **selected crown fill `#0F766E`** stroke `#0C5F59`; tooth number 10px/600, `#8CA09C` → teal when selected. Dashed `#DCE4E3` separator between jaws.

## Interactions & Behavior
- All existing behavior is preserved: Inertia form submission, delete confirms, month navigation via `router.get`, price autofill on dentist/type change, teeth selection driving quantity.
- New behaviors to add on the orders page: client-side text search (dentist/patient) and status filter (filter the `orders` array before render; keep it client-side, no backend change needed).
- Transitions: color/border transitions ~150–200ms; no entrance animations.
- Hover states are specified per component above; focus rings should use `--ring` teal.

## State Management
No new server state. Orders page adds two local `useState`s: `search: string`, `statusFilter: 'all' | Order['status']`.

## Assets
No image assets. All icons are lucide-react (already installed): LayoutGrid, Users, ClipboardList, CreditCard, FileText, Wallet, UserCog, Package, PiggyBank, CalendarRange, Plus, Pencil, Trash2, TrendingUp, TrendingDown, Coins, Search, ChevronLeft/Right, ArrowRight, Check, Info. The sidebar logo tooth glyph is an inline SVG (see `Dashboard.dc.html` aside header).

## Files
Design references (open in a browser; they link to each other via the sidebar):
- `Dashboard.dc.html` — dashboard
- `Orders.dc.html` — orders list
- `New Order.dc.html` — new order form with interactive dental chart
- `support.js` — runtime for the prototypes (ignore; not part of the design)
