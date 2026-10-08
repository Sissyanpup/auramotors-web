---
name: Obsidian & Champagne Luxury Atelier
colors:
  surface: '#121315'
  surface-dim: '#121315'
  surface-bright: '#38393b'
  surface-container-lowest: '#0d0e10'
  surface-container-low: '#1b1c1e'
  surface-container: '#1f2022'
  surface-container-high: '#292a2c'
  surface-container-highest: '#343537'
  on-surface: '#e3e2e5'
  on-surface-variant: '#d0c5af'
  inverse-surface: '#e3e2e5'
  inverse-on-surface: '#303033'
  outline: '#99907c'
  outline-variant: '#4d4635'
  surface-tint: '#e9c349'
  primary: '#f2ca50'
  on-primary: '#3c2f00'
  primary-container: '#d4af37'
  on-primary-container: '#554300'
  inverse-primary: '#735c00'
  secondary: '#c7c6c4'
  on-secondary: '#303130'
  secondary-container: '#464746'
  on-secondary-container: '#b5b5b3'
  tertiary: '#eec98f'
  on-tertiary: '#422c01'
  tertiary-container: '#d0ae76'
  on-tertiary-container: '#594113'
  error: '#ffb4ab'
  on-error: '#690005'
  error-container: '#93000a'
  on-error-container: '#ffdad6'
  primary-fixed: '#ffe088'
  primary-fixed-dim: '#e9c349'
  on-primary-fixed: '#241a00'
  on-primary-fixed-variant: '#574500'
  secondary-fixed: '#e3e2e0'
  secondary-fixed-dim: '#c7c6c4'
  on-secondary-fixed: '#1b1c1b'
  on-secondary-fixed-variant: '#464746'
  tertiary-fixed: '#ffdeaa'
  tertiary-fixed-dim: '#e5c187'
  on-tertiary-fixed: '#271900'
  on-tertiary-fixed-variant: '#5b4314'
  background: '#121315'
  on-background: '#e3e2e5'
  surface-variant: '#343537'
typography:
  display-lg:
    fontFamily: Bodoni Moda
    fontSize: 56px
    fontWeight: '400'
    lineHeight: 64px
    letterSpacing: -0.02em
  display-lg-mobile:
    fontFamily: Bodoni Moda
    fontSize: 36px
    fontWeight: '400'
    lineHeight: 44px
    letterSpacing: -0.01em
  headline-lg:
    fontFamily: Bodoni Moda
    fontSize: 40px
    fontWeight: '500'
    lineHeight: 48px
    letterSpacing: -0.01em
  headline-lg-mobile:
    fontFamily: Bodoni Moda
    fontSize: 28px
    fontWeight: '500'
    lineHeight: 36px
    letterSpacing: 0em
  headline-md:
    fontFamily: Bodoni Moda
    fontSize: 28px
    fontWeight: '400'
    lineHeight: 36px
    letterSpacing: 0em
  headline-sm:
    fontFamily: Manrope
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
    letterSpacing: 0.01em
  body-lg:
    fontFamily: Manrope
    fontSize: 18px
    fontWeight: '300'
    lineHeight: 28px
    letterSpacing: 0em
  body-md:
    fontFamily: Manrope
    fontSize: 15px
    fontWeight: '400'
    lineHeight: 24px
    letterSpacing: 0.01em
  body-sm:
    fontFamily: Manrope
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0.02em
  label-lg:
    fontFamily: Geist
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
    letterSpacing: 0.06em
  label-md:
    fontFamily: Geist
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.12em
  label-sm:
    fontFamily: Geist
    fontSize: 10px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.16em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1.5rem
  gutter-mobile: 0.75rem
  margin: 3rem
  margin-mobile: 1.25rem
  space-xs: 0.375rem
  space-sm: 0.75rem
  space-md: 1.25rem
  space-lg: 2rem
  space-xl: 3.5rem
---

## Brand & Style

The design system establishes an exclusive, ultra-luxury automotive sanctuary tailored for high-net-worth collectors, connoisseurs, and bespoke vehicle enthusiasts. The experience must balance the tactile precision of Swiss horology with the atmospheric presence of an exclusive private members' showroom.

The visual style merges **Dark Obsidian Glassmorphism** with **Atmospheric Minimal Luxury**. Surfaces are defined by deep charcoal and obsidian blacks, bathed in micro-reflections, brushed titanium boundaries, and warm champagne luminescence. Interactivity relies on soft luminous glows, whisper-thin hairlines, and effortless transitions that evoke whisper-quiet electric hypercars and grand tourers.

## Colors

The color palette commands authority through controlled luminosity against deep, non-reflective foundations:

- **Primary (`#D4AF37` / Champagne Gold):** Reserved strictly for primary action targets, reserve triggers, bespoke badges, verification insignia, and key pricing highlights.
- **Secondary (`#E5E4E2` / Titanium Silver):** Used for structural technical readouts, secondary callouts, hairline dividers, and high-tier vehicle chassis metadata.
- **Tertiary (`#8A6D3B` / Burnished Bronze):** Subdued metallic accent used for decorative borders, muted states, and elevated member tier indicators.
- **Neutral (`#0B0C0E` / Deep Obsidian):** The foundational bedrock. Layered across tonal variants (`#121418` for elevated surfaces, `#1A1D24` for glass card bases).

All states rely on luminescence rather than harsh tint shifts: hovered gold elements cast a radial champagne bloom, while inactive states sink cleanly into deep graphite.

## Typography

Typography balances high-contrast editorial elegance with precision telemetry:

- **Editorial Presence (`Bodoni Moda`):** Utilized for vehicle marques, model tiers, hero exhibition headings, and milestone price anchors. Its dramatic contrast speaks to high fashion and bespoke coachbuilding.
- **Modern Clarity (`Manrope`):** Delivers clean legibility across narrative descriptions, vehicle histories, provenance reports, and service documentation.
- **Technical Telemetry (`Geist`):** Delivers monospaced-adjacent precision for performance metrics (0-60 mph, displacement, horsepower, torque), chassis codes, VIN representations, and member access hashes. It must always render with upper-case tracking for labels.

## Layout & Spacing

The layout embraces high-impact visual curation with generous breathing room inspired by architectural galleries:

- **Desktop (12 Columns):** Employs a 12-column fluid grid bounded by a maximum content container of `1440px`. Exterior margins sit at `margin` (`3rem`) with `1.5rem` gutters, ensuring large vehicle imagery and glass cards never feel cramped.
- **Tablet (8 Columns):** Fluid 8-column layout with `2rem` outer margins. Specifications and gallery splits collapse into modular 4-column side-by-side units.
- **Mobile (4 Columns):** Single-column vertical flow with `1.25rem` outer margins. Data panels shift to swipeable horizon carousels or stacked micro-spec accordions.

Content rhythm enforces substantial vertical gaps between distinct models or gallery modules (`space-xl`), allowing each hypercar or classic to command total user focus.

## Elevation & Depth

Visual depth avoids generic drop shadows, deploying multi-stop glass refraction and champagne edge lighting:

- **Base Layer (Obsidian Zero):** Solid `#0B0C0E` background infused with ambient, low-opacity gold and indigo radial gradients placed behind hero vehicles.
- **Surface Level 1 (Glass Cards & Modules):** `rgba(18, 20, 24, 0.7)` background with a 1px border of `rgba(229, 228, 226, 0.08)`, paired with a `24px` backdrop blur (`backdrop-filter: blur(24px)`).
- **Surface Level 2 (Interactive Floating Modules & Modals):** `rgba(26, 29, 36, 0.85)` background with a 1px border of `rgba(212, 175, 55, 0.25)` and an ambient, low-density champagne shadow (`0 20px 50px -10px rgba(0, 0, 0, 0.7), 0 0 30px 0 rgba(212, 175, 55, 0.08)`).
- **Surface Level 3 (Active Overlays & Inspect Drawers):** `rgba(14, 16, 20, 0.95)` with dual-edge illumination: `inset 0 1px 0 0 rgba(255, 255, 255, 0.15)`.

## Shapes

The design language favors architectural, disciplined silhouettes:

- Standard elements utilize **Soft** radii (`0.25rem` / `4px`), delivering crisp edges without aggressive sharpness.
- Vehicle preview frames, interactive filters, and data cards utilize `rounded-lg` (`0.5rem` / `8px`).
- Badges, technical pill tags, and ticker highlights retain strict `rounded-xl` (`0.75rem` / `12px`) geometries to maintain an aerodynamic, streamlined character.

## Components

### Buttons
- **Primary Action (Acquisition / Concierge Inquiry):** Solid champagne gold (`#D4AF37`) fill with deep obsidian typography (`#0B0C0E`), subtle bevel top-highlight (`inset 0 1px 0 rgba(255, 255, 255, 0.4)`), and an ambient hover bloom (`box-shadow: 0 0 20px rgba(212, 175, 55, 0.35)`).
- **Secondary Action (Technical Dossier / Compare):** Smoked translucent surface (`rgba(255, 255, 255, 0.04)`) bounded by a 1px titanium silver border (`rgba(229, 228, 226, 0.2)`). Text renders in titanium silver with micro-letterspacing.

### Prominent Ticker Bar
- Anchored directly below global navigation or spanning section breaks. Features a smoked obsidian background (`rgba(11, 12, 14, 0.9)`), brushed bronze top and bottom borders (`rgba(138, 109, 59, 0.3)`), and a smooth horizontal marquee display showing auction closes, private drop announcements, and real-time bid pulses in `label-sm` tracking.

### Inventory Glass Cards
- Crafted using Surface Level 1 specifications with an aspect-ratio container dedicated to wide vehicle profiles.
- Image containers feature a bottom gradient blend (`linear-gradient(to top, rgba(18, 20, 24, 0.95), transparent)`).
- Card footer stacks technical stats (Engine, Output, 0-60) in a three-column micro-grid using `Geist` tabular figures beneath the `Bodoni Moda` vehicle name.

### Luxury Status Badges
- Ultra-condensed capsule tags (`label-sm`).
- **Verified Provenance:** Frosted obsidian pill with an animated champagne dot indicator and 1px metallic border.
- **Reserved / Allocation Tier:** Burnished bronze background with tone-on-tone gold border and soft luminescence.

### Member Profile Highlights & ID Codes
- Private client widgets featuring an engraved tactile card aesthetic.
- Displays alphanumeric client identity (e.g., `MEMBER // 084-ATX`) rendered in high-tracking `Geist` typography alongside a holographic micro-seal and fractional champagne edge highlight.

### Inputs & Selectors
- Dark recessed troughs (`rgba(0, 0, 0, 0.4)`) with 1px border transitions from neutral charcoal to luminous champagne upon focus.
- Labels float in all-caps titanium silver (`label-md`).