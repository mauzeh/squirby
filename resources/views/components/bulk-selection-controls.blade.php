@props(['config'])

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Select all functionality
        const selectAllLiftLogs = document.getElementById('select-all-lift-logs');
        if (selectAllLiftLogs) {
            selectAllLiftLogs.addEventListener('change', function(e) {
                document.querySelectorAll('.lift-log-checkbox').forEach(function(checkbox) {
                    checkbox.checked = e.target.checked;
                });
            });
        }

        // Bulk delete form handling
        const deleteSelectedForm = document.getElementById('delete-selected-form');
        if (deleteSelectedForm) {
            deleteSelectedForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var form = e.target;
                var checkedLogs = document.querySelectorAll('.lift-log-checkbox:checked');

                if (checkedLogs.length === 0) {
                    alert('Please select at least one lift log to delete.');
                    return;
                }

                checkedLogs.forEach(function(checkbox) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'lift_log_ids[]';
                    input.value = checkbox.value;
                    form.appendChild(input);
                });

                form.submit();
            });
        }
    });
</script>