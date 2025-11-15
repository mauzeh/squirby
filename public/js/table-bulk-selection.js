/**
 * Table Bulk Selection JavaScript
 * Handles checkbox-based bulk selection for table components
 */

document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-templates');
    const checkboxes = document.querySelectorAll('.template-checkbox');
    const bulkForm = document.getElementById('bulk-delete-form');

    // Select all functionality
    if (selectAll) {
        selectAll.addEventListener('change', function (e) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
    }

    // Update select-all state when individual checkboxes change
    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
            const someChecked = Array.from(checkboxes).some((cb) => cb.checked);
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });

    // Make rows clickable to toggle checkbox
    const rows = document.querySelectorAll('.component-table-row.has-checkbox');
    rows.forEach(function (row) {
        row.style.cursor = 'pointer';

        row.addEventListener('click', function (e) {
            // Don't toggle if clicking on interactive elements
            if (e.target.closest('a, button, form, input, .table-expand-icon')) {
                return;
            }

            // Find the checkbox in this row
            const checkbox = row.querySelector('.template-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                // Trigger change event to update select-all state
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    });

    // Bulk delete form submission
    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const checkedBoxes = document.querySelectorAll('.template-checkbox:checked');

            if (checkedBoxes.length === 0) {
                alert('Please select at least one template to delete.');
                return false;
            }

            checkedBoxes.forEach(function (checkbox) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = checkbox.value;
                bulkForm.appendChild(input);
            });

            bulkForm.submit();
        });
    }
});

/**
 * Confirmation function for bulk delete
 * Called from the form's onsubmit attribute
 */
function confirmBulkDelete() {
    const count = document.querySelectorAll('.template-checkbox:checked').length;
    if (count === 0) {
        alert('Please select at least one template to delete.');
        return false;
    }
    return confirm('Are you sure you want to delete ' + count + ' template(s)?');
}
