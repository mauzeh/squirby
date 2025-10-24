
# Mobile Entry for food
* use the same styling as for logging life logs.
* assume real time logging (so no time field)
* large input field that autocompletes and holds both meals and ingredients
* select list shows top meals and ingredients before the user even begins typing.
* no changes to any models
* once the user selects an ingredient, a quantity and notes field is shown.
* once the user selects a meal, a portion and notes field is shown.
* use the same "-" and "+" buttons for the quantity and portion fields.
* for ingredients, the increment/decrement quantity depends on the unit:
  * g, ml: 10
  * kg, lbs: l 0.1
  * pc, servings: 0.25
* Don't include any option to create ingredients nor meals on mobile-entry form.
* Use the same date navigation as on lift-logs/mobile-entry.
* There are only user-specific ingredients. There are no global ingredients.
* The logging form is always at the top of the list. Below the form is a list of previously logged food logs for that day.
