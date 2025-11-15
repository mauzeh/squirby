/**
 * Table Bulk Selection JavaScript
 * Handles checkbox-based bulk selection for table components
 */

document.addEventListener('DOMContentLoaded', function () {
    // Select all functionality (component-based)
    const selectAllCheckboxes = document.querySelectorAll('.select-all-checkbox');
    selectAllCheckboxes.forEach(function (selectAll) {
        const checkboxSelector = selectAll.dataset.checkboxSelector || '.template-checkbox';
        const checkboxes = document.querySelectorAll(checkboxSelector);
        
        // Select all functionality
        selectAll.addEventListener('change', function (e) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
        
        // Update select-all state when individual checkboxes change
        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
                const someChecked = Array.from(checkboxes).some((cb) => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    });
    
    // Legacy support for old implementation
    const selectAll = document.getElementById('select-all-templates');
    const checkboxes = document.querySelectorAll('.template-checkbox');
    const bulkForm = document.getElementById('bulk-delete-form');

    // Select all functionality (legacy)
    if (selectAll && !selectAll.classList.contains('select-all-checkbox')) {
        selectAll.addEventListener('change', function (e) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = e.target.checked;
            });
        });
        
        // Update select-all state when individual checkboxes change
        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
                const someChecked = Array.from(checkboxes).some((cb) => cb.checked);
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            });
        });
    }

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

    // Bulk delete form submission (legacy support)
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
    
    // Bulk action form submission (new component-based approach)
    const bulkActionForms = document.querySelectorAll('.bulk-action-form');
    bulkActionForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const checkboxSelector = form.dataset.checkboxSelector || '.template-checkbox';
            const inputName = form.dataset.inputName || 'selected_ids';
            const emptyMessage = form.dataset.emptyMessage || 'Please select at least one item.';
            const confirmMessage = form.dataset.confirmMessage;
            
            const checkedBoxes = document.querySelectorAll(checkboxSelector + ':checked');
            
            if (checkedBoxes.length === 0) {
                alert(emptyMessage);
                return false;
            }
            
            // Show confirmation if configured
            if (confirmMessage) {
                const message = confirmMessage.replace(':count', checkedBoxes.length);
                if (!confirm(message)) {
                    return false;
                }
            }
            
            // Add hidden inputs for selected IDs
            checkedBoxes.forEach(function (checkbox) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName + '[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
            
            form.submit();
        });
    });
});


