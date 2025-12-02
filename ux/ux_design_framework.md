# Fitness App Design System & UI Specification

**Version:** 1.0
**Platform:** iOS / Mobile Web (PWA)
**Theme:** System Dark Mode (`#000000`)

## 1\. Core Design Principles

This application adheres to **Apple’s Human Interface Guidelines (HIG)** to ensure the app feels native, fast, and ergonomic.

1.  **Context Over Recall:** The app never asks the user to remember what they did last time. It provides "Smart Defaults" and historical notes explicitly.
2.  **Thumb-Driven Ergonomics:** Primary interactions (Navigation, Logging, Adding) are placed in the bottom 50% of the screen for easy one-handed use.
3.  **Visual Categorization:** Exercises are color-coded by equipment type to allow for rapid scanning of lists.
4.  **Action-Oriented:** The UI minimizes taps. We use "Steppers" for small adjustments and "Smart Suggestions" to skip search steps.

-----

## 2\. Global Style Guide

### 2.1 Color Palette

We use a high-contrast dark mode palette. Colors have semantic meaning—do not use them purely for decoration.

| Role | Color Name | Hex Code | Usage |
| :--- | :--- | :--- | :--- |
| **Background** | True Black | `#000000` | Main screen background. |
| **Surface** | Dark Grey | `#1c1c1e` | Cards, List Items, Search Bars. |
| **Surface (Alt)**| Elevated Grey| `#2c2c2e` | Modals, Inset Grouped Cards. |
| **Primary** | Apple Blue | `#0a84ff` | Buttons, Active States, Links, Today's Date. |
| **Success** | Neon Green | `#30d158` | Checkmarks, Bodyweight Category, Volume Increases. |
| **Heavy/Warn** | System Red | `#ff3b30` | Barbell Category, Heavy Lifts. |
| **Caution/Note**| Sunflower | `#ffcc00` | Historical Notes, "Attention" items. |
| **Neutral** | System Grey | `#8e8e93` | Subtitles, Machine Category, Inactive Dates. |
| **Text** | White | `#ffffff` | Primary Text. |

### 2.2 Typography

Use the native system font stack for maximum legibility and native feel.

```css
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
```

  * **Headers (H1):** 28px–32px, Bold (`700`).
  * **Section Titles:** 20px, Bold (`700`).
  * **Body Text:** 17px, Regular (`400`) or Medium (`500`).
  * **Subtitles/Metadata:** 13px, Regular, Color `#8e8e93`.

### 2.3 Iconography

**Library:** FontAwesome 6 (Solid style).
**Styling:** Icons are always enclosed in a 40x40px rounded square container (`border-radius: 8px`) to create a consistent visual rhythm in lists.

-----

## 3\. Screen Specifications

### 3.1 Screen: "Today" (Dashboard)

The landing page. Focuses on the current status and quick entry.

  * **Date Navigation:** Uses a **Week Strip** (Mon-Sun) instead of "Prev/Next" buttons.
      * *Active Day:* Blue text, Blue circle background.
      * *Interaction:* Tapping the Calendar Icon opens the full Calendar Page.
  * **Summary Widget:** A gradient card showing "Total Volume" (or primary metric) to give instant gratification.
  * **Logged List:**
      * Displays exercises chronologically.
      * **Visual Check:** Green checkmark icon on the right confirms the log is saved.
      * **Border Coding:** Left-border stroke (4px) matches the Exercise Category color (see Section 4).
  * **Primary Action:** A **Floating Action Button (FAB)** in the bottom right.
      * *Style:* Circle (56x56px), Blue (`#0a84ff`), Drop Shadow.
      * *Icon:* `fa-plus` (White).

### 3.2 Screen: Exercise Selection

The "Search" tool triggered by the FAB. Designed for speed.

  * **Search Bar:** Native iOS style. Grey background (`#1c1c1e`), anchored at the top.
  * **Smart Suggestions (Horizontal Scroll):**
      * Immediately below search.
      * **Logic:** Shows frequent exercises OR exercises programmed for today's specific workout split.
      * *Metadata:* Shows "Last performed: [Time] ago".
  * **Categorized List:**
      * Grouped by Body Part (Legs, Back, etc.).
      * **Action:** Tap the `(+)` icon to open the Log Screen for that movement.

### 3.3 Screen: Log Movement

The data entry screen. Uses the **"Inset Grouped"** pattern common in iOS Settings and Contacts.

  * **Header:** Simple H1 text (e.g., "Back Squat"). No icons in header.
  * **Component: Past Notes (Feedforward):**
      * *Placement:* Top of screen (must be read before lifting).
      * *Style:* Grey card with a Yellow vertical accent bar.
      * *Typography:* Italicized text to represent a "voice" from the past.
  * **Component: Smart Inputs:**
      * **Visual Grouping:** Weight, Reps, and Sets are stacked in a single rounded card.
      * **Steppers:** `+` and `-` buttons for quick adjustment.
      * **Smart Plan Indicator:** Values pre-filled by the algorithm are colored **Blue**. If the user edits them, they turn **White**.
      * **Progression Tag:** A small green badge (e.g., `↑ 5lbs`) next to the weight label to highlight progress.

### 3.4 Screen: Calendar

A dedicated full-page view for navigation and history visualization.

  * **Layout:** Standard Month Grid.
  * **Data Visualization:** "Activity Dots" under dates.
      * *Green Dot:* Workout completed.
      * *Empty:* Rest day.
  * **Interaction:** Tapping a date navigates the user to the **"Today"** view for that specific historical date.

-----

## 4\. Exercise Categorization Logic

To improve scannability, every exercise is assigned a category. This dictates the Icon and the Color used throughout the app.

| Category | Definition | Color | Icon Class |
| :--- | :--- | :--- | :--- |
| **Barbell** | Heavy compound lifts (Squat, Deadlift, Bench). | **Red** (`#ff3b30`) | `fa-weight-hanging` |
| **Bodyweight** | Gymnastics, Cardio, Monostructural (Run, Pull-up). | **Green** (`#30d158`) | `fa-person-running` |
| **Dumbbell** | Kettlebell or Dumbbell accessory work. | **Blue** (`#0a84ff`) | `fa-dumbbell` |
| **Machine** | Isolation, Cables, Levers. | **Grey** (`#8e8e93`) | `fa-gears` |

-----

## 5\. Reusable CSS Classes (Utility)

Use these snippets to maintain consistency across the app.

**1. The "Card" Container**

```css
.ios-card {
  background-color: #1c1c1e; /* or #2c2c2e for inset */
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  overflow: hidden;
}
```

**2. The Flex Row (List Items)**

```css
.flex-row-center {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
```

**3. The Primary Button**

```css
.primary-btn {
  background-color: #0a84ff;
  color: white;
  width: 100%;
  padding: 16px;
  border-radius: 12px;
  border: none;
  font-size: 17px;
  font-weight: 600;
}
```

**4. Animations (Slide Up/Down)**

```css
@keyframes slideIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-enter {
  animation: slideIn 0.3s ease-out;
}
```