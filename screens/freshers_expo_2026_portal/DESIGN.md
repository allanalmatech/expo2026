---
name: Freshers Expo 2026 Portal
colors:
  surface: '#f9f9f9'
  surface-dim: '#dadada'
  surface-bright: '#f9f9f9'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3f3'
  surface-container: '#eeeeee'
  surface-container-high: '#e8e8e8'
  surface-container-highest: '#e2e2e2'
  on-surface: '#1a1c1c'
  on-surface-variant: '#5a4136'
  inverse-surface: '#2f3131'
  inverse-on-surface: '#f1f1f1'
  outline: '#8e7164'
  outline-variant: '#e2bfb0'
  surface-tint: '#a04100'
  primary: '#a04100'
  on-primary: '#ffffff'
  primary-container: '#ff6b00'
  on-primary-container: '#572000'
  inverse-primary: '#ffb693'
  secondary: '#5f5e5e'
  on-secondary: '#ffffff'
  secondary-container: '#e2dfde'
  on-secondary-container: '#636262'
  tertiary: '#5d5f5f'
  on-tertiary: '#ffffff'
  tertiary-container: '#989999'
  on-tertiary-container: '#2f3132'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#ffdbcc'
  primary-fixed-dim: '#ffb693'
  on-primary-fixed: '#351000'
  on-primary-fixed-variant: '#7a3000'
  secondary-fixed: '#e5e2e1'
  secondary-fixed-dim: '#c8c6c5'
  on-secondary-fixed: '#1c1b1b'
  on-secondary-fixed-variant: '#474746'
  tertiary-fixed: '#e2e2e2'
  tertiary-fixed-dim: '#c6c6c7'
  on-tertiary-fixed: '#1a1c1c'
  on-tertiary-fixed-variant: '#454747'
  background: '#f9f9f9'
  on-background: '#1a1c1c'
  surface-variant: '#e2e2e2'
typography:
  display-lg:
    fontFamily: Montserrat
    fontSize: 48px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Montserrat
    fontSize: 32px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-lg-mobile:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '700'
    lineHeight: '1.2'
  headline-md:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.3'
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: '1.2'
    letterSpacing: 0.05em
  button:
    fontFamily: Montserrat
    fontSize: 16px
    fontWeight: '600'
    lineHeight: '1.2'
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 48px
  xl: 80px
  container-max: 1280px
  gutter: 24px
---

## Brand & Style

The design system is engineered for the **Freshers Expo 2026**, serving a dual audience of energetic university students and professional business vendors. The brand personality is high-energy yet highly organized, bridging the gap between a vibrant campus event and a functional commercial marketplace.

The design style is **Corporate Modern with a High-Contrast Edge**. It leverages the intensity of the primary orange against a deep black foundation to create a sense of urgency and excitement, while maintaining a clean, systematic structure that ensures vendors can manage inventory and logistics without friction. The interface prioritizes clarity, utilizing generous white space and professional finishing to ensure accessibility for all users.

## Colors

The palette is anchored by **Vivid Orange (#FF6B00)**, used strategically for primary actions and brand emphasis. **Deep Black (#1A1A1A)** provides the structural weight, used for typography and high-emphasis backgrounds.

- **Primary Orange:** Reserved for "hero" interactions—Submit, Register, and Book Stall.
- **Deep Black:** Used for navigation bars, primary headings, and buttons that require a secondary but strong visual weight.
- **Pure White:** The primary canvas for content areas to ensure maximum readability and a "bazaar" feel that isn't overwhelming.
- **Soft Grays:** Utilized for background layering (e.g., `#F4F4F4`) and subtle borders (`#E5E5E5`) to separate stall listings without using heavy lines.

## Typography

This design system employs a pairing of **Montserrat** for headings and **Inter** for body text. 

- **Montserrat** provides a bold, geometric confidence necessary for event branding. Headlines should utilize tight letter-spacing to maintain a modern, "impact" aesthetic.
- **Inter** is used for all functional data, stall descriptions, and management forms. Its high x-height ensures legibility even when vendors are viewing the portal on mobile devices in bright outdoor light.
- Large display sizes (48px+) are reserved for landing pages, while management dashboards should prioritize `headline-md` for section titles.

## Layout & Spacing

The layout follows a **12-column fluid grid** for desktop, transitioning to a **4-column grid** for mobile. 

- **Management Dashboard:** Uses a "fixed sidebar / fluid content" model. The sidebar remains at 280px while the content area expands.
- **Bazaar Marketplace:** Uses a responsive CSS grid for stall cards, allowing items to reflow from 4 columns on desktop to 1 column on mobile.
- **Rhythm:** A strictly enforced 8px baseline grid ensures vertical alignment. Use `md` (24px) for standard padding within cards and `lg` (48px) for section vertical spacing.

## Elevation & Depth

Visual hierarchy is achieved through a combination of **Tonal Layering** and **Ambient Shadows**.

1.  **Level 0 (Background):** The main canvas, usually Pure White or Soft Gray.
2.  **Level 1 (Cards/Containers):** Pure White surfaces with a very soft, diffused shadow (0px 4px 20px rgba(0,0,0,0.05)).
3.  **Level 2 (Interactive/Hover):** When a user hovers over a stall card or button, the shadow deepens and the element lifts slightly (0px 8px 30px rgba(0,0,0,0.1)).
4.  **Level 3 (Overlays):** Modals and dropdowns use a crisp border (1px solid #1A1A1A) paired with a high-contrast shadow to ensure they pop against the underlying content.

Avoid heavy blurs or glassmorphism to maintain the "clean and professional" requirement; keep surfaces opaque.

## Shapes

The shape language is **friendly and approachable**. A standard radius of `0.5rem` (8px) is applied to all primary UI elements, including input fields, buttons, and cards. 

- **Small elements (tags/chips):** Use the `rounded-lg` (16px) setting to create a softer, more pill-like appearance for status indicators.
- **Containers:** Large dashboard containers or section blocks should use `rounded-xl` (24px) to frame the content comfortably.
- **Buttons:** Maintain the standard `rounded-lg` for a modern, tactile feel that invites interaction.

## Components

### Buttons
- **Primary:** Background Orange (#FF6B00), text White (#FFFFFF). Bold Montserrat. 
- **Secondary:** Background Black (#1A1A1A), text White (#FFFFFF).
- **Ghost:** Transparent background, Black or Orange border (2px), matching text.

### Stall Cards
Stall cards are the core component. They feature a top-aligned image, a title in `headline-md`, and a status chip. The entire card surface should be interactive, using the Level 2 elevation on hover.

### Input Fields
Forms should be clean with a 1px border (#E5E5E5). On focus, the border changes to Orange (#FF6B00) with a 2px thickness. Labels are always visible above the field in `label-md`.

### Status Chips
Small, rounded-pill indicators for stall availability:
- **Available:** Green background (soft tint), Green text.
- **Booked:** Gray background, Dark Gray text.
- **Pending:** Orange background (soft tint), Orange text.

### Navigation
A top-tier sticky navigation bar using the Deep Black background for high contrast. Links should transition to Orange on hover.