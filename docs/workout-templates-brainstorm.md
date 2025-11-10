# Workout Templates - Feature Brainstorming

## Current State Analysis

### What We Have Now
- **Programs**: Daily exercise planning (exercise + date + sets + reps + priority)
- Users manually add exercises to their program for each day
- Training progression service suggests weights based on history
- Recommendations engine suggests exercises
- Users can quick-add exercises from recommendations

### What's Missing
- No way to save/reuse workout routines
- No pre-built workout templates
- Users must manually recreate their routines each week
- No concept of multi-day training splits
- No template sharing between users

---

## User Needs (Based on Analysis)

From the lift logs analysis, we see:
- **Admin User**: Consistent with 3.4 exercises/day, 22 workout days/month
- **Stefan Swaans**: High-volume sessions (17.5 exercises/day)
- **New users**: Lower consistency (1-2 workout days)

**Pain Points:**
1. Recreating the same workout routine weekly is tedious
2. New users don't know what exercises to do
3. No easy way to follow structured programs (5x5, PPL, etc.)
4. Can't share successful routines with others

---

## Template Options to Consider

### Option 1: Simple Workout Templates
**Concept:** Save a collection of exercises as a reusable template

**Structure:**
```
Template
├── name (e.g., "Push Day", "Full Body A")
├── description
├── user_id (owner)
├── is_public (shareable)
└── exercises[]
    ├── exercise_id
    ├── sets
    ├── reps
    ├── order
    └── notes
```

**Pros:**
- Simple to implement
- Easy for users to understand
- Solves the "recreate weekly" problem

**Cons:**
- No concept of progression over time
- No multi-week programming
- Limited for advanced users

**Use Cases:**
- "My Monday Workout"
- "Upper Body"
- "Leg Day"

---

### Option 2: Training Programs (Multi-Week)
**Concept:** Structured programs with multiple weeks and progression

**Structure:**
```
Training Program
├── name (e.g., "Starting Strength", "5/3/1")
├── description
├── duration_weeks
├── user_id (creator)
├── is_public
└── weeks[]
    └── workouts[]
        ├── day_of_week
        ├── name
        └── exercises[]
            ├── exercise_id
            ├── sets
            ├── reps (can be formula: "5x5", "5/3/1")
            ├── intensity (% of 1RM)
            └── progression_rule
```

**Pros:**
- Supports periodization
- Can implement popular programs (5x5, PPL, etc.)
- Professional/structured approach

**Cons:**
- More complex to build
- Harder for casual users
- Requires more UI/UX work

**Use Cases:**
- "12-Week Strength Program"
- "Push/Pull/Legs Split"
- "Starting Strength"

---

### Option 3: Hybrid Approach (Recommended)
**Concept:** Start simple, expand later

**Phase 1: Workout Templates**
- Save collections of exercises
- One-click apply to any date
- Share templates with community
- Tag templates (push, pull, legs, full-body, etc.)

**Phase 2: Training Programs**
- Multi-week programs
- Progression rules
- Auto-scheduling

**Structure:**
```
workout_templates
├── id
├── name
├── description
├── user_id
├── is_public
├── tags (json: ["push", "strength", "beginner"])
├── created_at
└── updated_at

workout_template_exercises
├── id
├── workout_template_id
├── exercise_id
├── sets
├── reps
├── order
├── notes
└── rest_seconds (optional)
```

---

## Feature Breakdown

### Core Features (MVP)

1. **Create Template**
   - Name your template
   - Add exercises with sets/reps
   - Reorder exercises (drag & drop)
   - Save as private or public

2. **Apply Template**
   - Browse your templates
   - One-click apply to today (or any date)
   - Copies all exercises to programs table
   - Maintains order via priority field

3. **Manage Templates**
   - Edit existing templates
   - Delete templates
   - Duplicate templates
   - View usage stats

4. **Browse Public Templates**
   - See community templates
   - Filter by tags (push/pull/legs/full-body)
   - Filter by difficulty
   - Copy to your templates

### Advanced Features (Future)

5. **Template Variations**
   - "Heavy Day" vs "Light Day" versions
   - Swap exercises (e.g., barbell → dumbbell)

6. **Smart Scheduling**
   - "Apply this template every Monday"
   - Rest day recommendations
   - Deload weeks

7. **Program Builder**
   - Multi-week programs
   - Progression schemes (linear, wave, etc.)
   - Auto-adjust based on performance

8. **Social Features**
   - Follow other users' templates
   - Rate/review templates
   - Comments and modifications
   - Template marketplace

---

## UI/UX Considerations

### Where Templates Live

**Option A: New Top-Level Section**
```
Lifts
├── Mobile Entry (current)
├── History
├── Program (current daily view)
├── Templates (NEW)
└── Exercises
```

**Option B: Integrated into Program**
```
Program View
├── [Date Navigation]
├── [Apply Template Button] ← NEW
├── [Add Exercise]
└── [Exercise List]
```

**Recommendation:** Option B for MVP, Option A for full feature

### User Flows

**Creating a Template:**
1. User has a good workout in their program
2. Click "Save as Template"
3. Name it, add description
4. Choose public/private
5. Done!

**Using a Template:**
1. Navigate to program for a date
2. Click "Apply Template"
3. See list of templates (yours + public)
4. Click one → exercises populate
5. Adjust as needed

**Browsing Templates:**
1. Go to Templates section
2. Filter by tags/difficulty
3. Preview exercises
4. "Copy to My Templates" or "Apply to Date"

---

## Technical Implementation

### Database Schema

```sql
-- Phase 1: Simple Templates
CREATE TABLE workout_templates (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT false,
    tags JSON, -- ["push", "strength", "beginner"]
    times_used INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE workout_template_exercises (
    id BIGINT PRIMARY KEY,
    workout_template_id BIGINT REFERENCES workout_templates(id) ON DELETE CASCADE,
    exercise_id BIGINT REFERENCES exercises(id) ON DELETE CASCADE,
    sets INT NOT NULL,
    reps INT NOT NULL,
    order INT NOT NULL,
    notes TEXT,
    rest_seconds INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Phase 2: Training Programs (Future)
CREATE TABLE training_programs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_weeks INT,
    is_public BOOLEAN DEFAULT false,
    difficulty_level INT, -- 1-5
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE training_program_weeks (
    id BIGINT PRIMARY KEY,
    training_program_id BIGINT REFERENCES training_programs(id) ON DELETE CASCADE,
    week_number INT NOT NULL,
    notes TEXT
);

CREATE TABLE training_program_workouts (
    id BIGINT PRIMARY KEY,
    training_program_week_id BIGINT REFERENCES training_program_weeks(id) ON DELETE CASCADE,
    workout_template_id BIGINT REFERENCES workout_templates(id),
    day_of_week INT, -- 1-7
    name VARCHAR(255),
    order INT
);
```

### Key Methods

```php
// WorkoutTemplate Model
public function applyToDate(Carbon $date, User $user)
{
    // Copy template exercises to programs table
    foreach ($this->exercises as $templateExercise) {
        Program::create([
            'user_id' => $user->id,
            'exercise_id' => $templateExercise->exercise_id,
            'date' => $date,
            'sets' => $templateExercise->sets,
            'reps' => $templateExercise->reps,
            'priority' => $templateExercise->order,
        ]);
    }
    
    $this->increment('times_used');
}

public function duplicate(User $user)
{
    // Create a copy for another user
}

// WorkoutTemplateController
public function store(Request $request)
{
    // Create template from current program
    // Or create from scratch
}

public function apply(WorkoutTemplate $template, Request $request)
{
    // Apply template to specified date
}
```

---

## Popular Template Examples

### Beginner Templates
1. **Full Body A**
   - Back Squat: 3x5
   - Bench Press: 3x5
   - Deadlift: 1x5
   - Pull-Ups: 3x8

2. **Full Body B**
   - Front Squat: 3x5
   - Strict Press: 3x5
   - Romanian Deadlift: 3x8
   - Rows: 3x8

### Intermediate Templates
3. **Push Day**
   - Bench Press: 4x6
   - Strict Press: 3x8
   - Dips: 3x10
   - Tricep Extensions: 3x12

4. **Pull Day**
   - Deadlift: 4x5
   - Pull-Ups: 4x8
   - Rows: 4x8
   - Bicep Curls: 3x12

5. **Leg Day**
   - Back Squat: 4x6
   - Romanian Deadlift: 3x8
   - Lunges: 3x10
   - Leg Curls: 3x12

### Advanced Templates
6. **5/3/1 Week 1**
   - Main Lift: 5@65%, 5@75%, 5+@85%
   - Assistance work

---

## Metrics to Track

### Template Analytics
- Times used (per template)
- Completion rate (did user finish the workout?)
- Average duration
- User ratings
- Most popular templates
- Template effectiveness (strength gains)

### User Analytics
- Templates created
- Templates used
- Favorite templates
- Consistency with template usage

---

## Migration Path

### For Existing Users
1. Analyze their program history
2. Suggest creating templates from repeated patterns
3. "We noticed you do this workout often. Save it as a template?"

### For New Users
1. Onboarding: "Choose a template to get started"
2. Show popular beginner templates
3. Guide them through first workout

---

## Competitive Analysis

### What Others Do

**Strong App:**
- Pre-built programs (5x5, PPL, etc.)
- Custom routines
- Exercise substitutions
- Rest timer

**JEFIT:**
- Massive template library
- Community sharing
- Detailed exercise database
- Progress tracking

**Fitbod:**
- AI-generated workouts
- Adapts to equipment
- Recovery-aware

**Our Advantage:**
- Already have exercise intelligence
- Training progression service
- Recommendation engine
- Can combine all three for smart templates

---

## Recommendation: Start Here

### Phase 1 (MVP) - 2-3 weeks
1. **Workout Templates** (simple version)
   - Create/edit/delete templates
   - Apply template to date
   - Private templates only
   - Basic UI in program view

2. **Seed Data**
   - 5-10 pre-built templates
   - Cover main training styles (full-body, PPL, upper/lower)

3. **User Testing**
   - Get feedback from current users
   - Iterate on UX

### Phase 2 - 1-2 weeks
4. **Public Templates**
   - Share templates
   - Browse community templates
   - Tags and filtering

5. **Template Analytics**
   - Track usage
   - Show popular templates

### Phase 3 - Future
6. **Training Programs** (multi-week)
7. **Smart Scheduling**
8. **AI Recommendations** (use existing recommendation engine)

---

## Questions to Answer

1. **Should templates include weight suggestions?**
   - Pro: More complete template
   - Con: Weight is personal, progression service handles this
   - **Recommendation:** No, let progression service handle it

2. **Can templates include exercises user doesn't have?**
   - Pro: Discover new exercises
   - Con: Confusion if exercise doesn't exist
   - **Recommendation:** Yes, but show warning and offer to create

3. **Should we support exercise substitutions?**
   - Example: "Barbell Bench Press OR Dumbbell Bench Press"
   - **Recommendation:** Phase 2 feature

4. **How to handle bodyweight exercises in templates?**
   - Sets/reps make sense
   - Weight doesn't apply
   - **Recommendation:** Template stores sets/reps, weight is optional

5. **Should templates be versioned?**
   - User updates template, what happens to past uses?
   - **Recommendation:** No versioning for MVP, just update in place

---

## Success Metrics

### User Engagement
- % of users who create templates
- % of users who use templates
- Average templates per user
- Template usage frequency

### Retention
- Do users with templates have better retention?
- Do they log more consistently?

### Growth
- Template sharing rate
- New users starting with templates
- Template marketplace activity (future)

---

## Next Steps

1. **Validate with users**: Show mockups, get feedback
2. **Prioritize features**: Which are must-have vs nice-to-have?
3. **Design database schema**: Finalize structure
4. **Create wireframes**: UI/UX design
5. **Build MVP**: Start with Phase 1
6. **Iterate**: Based on user feedback

---

## Open Questions for Discussion

- Should templates be tied to specific days of the week?
- How do we handle exercise aliases in templates?
- Should templates support supersets/circuits?
- Do we need a template approval process for public templates?
- Should we integrate with the recommendation engine?
- How do templates interact with the training progression service?
