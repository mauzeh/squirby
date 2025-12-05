/**
 * Code Editor Autocomplete
 * 
 * Exercise name autocomplete for WOD syntax editor
 */

(function() {
    'use strict';
    
    /**
     * Add autocomplete for exercise names
     */
    window.CodeEditorAutocomplete = {
        init: function(textarea, wrapper) {
            let exercises = [];
            let autocompleteDiv = null;
            let selectedIndex = -1;
            let exercisesLoaded = false;
            
            // Fetch exercise names immediately
            fetch('/api/exercises/autocomplete')
                .then(res => {
                    console.log('Autocomplete response status:', res.status);
                    return res.json();
                })
                .then(data => { 
                    console.log('Autocomplete data received:', data);
                    exercises = Array.isArray(data) ? data : [];
                    exercisesLoaded = true;
                    console.log('Loaded exercises for autocomplete:', exercises.length);
                })
                .catch(err => console.error('Failed to load exercises:', err));
            
            // Find the component-code-editor parent
            const editorComponent = textarea.closest('.component-code-editor');
            if (!editorComponent) {
                console.error('Could not find .component-code-editor parent');
                return;
            }
            
            // Ensure component has position relative for absolute positioning
            if (!editorComponent.style.position || editorComponent.style.position === 'static') {
                editorComponent.style.position = 'relative';
            }
            
            // Create autocomplete dropdown
            autocompleteDiv = document.createElement('div');
            autocompleteDiv.className = 'code-editor-autocomplete';
            autocompleteDiv.style.cssText = `
                position: absolute;
                background: #2d2d2d;
                border: 1px solid #3e3e3e;
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
                display: none;
                z-index: 9999;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
                min-width: 200px;
            `;
            editorComponent.appendChild(autocompleteDiv);
            
            // Handle input for autocomplete
            textarea.addEventListener('input', () => {
                console.log('Input event - exercisesLoaded:', exercisesLoaded, 'exercises.length:', exercises.length);
                
                // Skip if exercises haven't loaded yet
                if (!exercisesLoaded || exercises.length === 0) {
                    console.log('Skipping autocomplete - not ready');
                    return;
                }
                
                const cursorPos = textarea.selectionStart;
                const textBeforeCursor = textarea.value.substring(0, cursorPos);
                
                // Check if we're inside brackets
                const bracketMatch = textBeforeCursor.match(/\[{1,2}([^\]]*?)$/);
                console.log('Bracket match:', bracketMatch);
                
                if (bracketMatch && bracketMatch[1].length > 0) {
                    const query = bracketMatch[1].toLowerCase();
                    console.log('Searching for:', query);
                    const matches = exercises.filter(ex => 
                        ex.toLowerCase().includes(query)
                    ).slice(0, 10);
                    console.log('Found matches:', matches.length);
                    
                    if (matches.length > 0) {
                        showAutocomplete(matches, textarea, autocompleteDiv);
                        selectedIndex = -1;
                    } else {
                        hideAutocomplete(autocompleteDiv);
                    }
                } else {
                    hideAutocomplete(autocompleteDiv);
                }
            });
            
            // Handle keyboard navigation
            textarea.addEventListener('keydown', (e) => {
                if (autocompleteDiv.style.display === 'none') return;
                
                const items = autocompleteDiv.querySelectorAll('.autocomplete-item');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    updateSelection(items, selectedIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, -1);
                    updateSelection(items, selectedIndex);
                } else if (e.key === 'Enter' && selectedIndex >= 0) {
                    e.preventDefault();
                    items[selectedIndex].click();
                } else if (e.key === 'Escape') {
                    hideAutocomplete(autocompleteDiv);
                }
            });
            
            // Hide on blur (with delay for click)
            textarea.addEventListener('blur', () => {
                setTimeout(() => hideAutocomplete(autocompleteDiv), 200);
            });
            
            function showAutocomplete(matches, textarea, autocompleteDiv) {
                console.log('showAutocomplete called with', matches.length, 'matches');
                autocompleteDiv.innerHTML = '';
                
                matches.forEach((exercise) => {
                    const item = document.createElement('div');
                    item.className = 'autocomplete-item';
                    item.textContent = exercise;
                    item.style.cssText = `
                        padding: 8px 12px;
                        cursor: pointer;
                        color: #f2f2f2;
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 14px;
                    `;
                    
                    item.addEventListener('mouseenter', () => {
                        item.style.backgroundColor = '#3e3e3e';
                    });
                    
                    item.addEventListener('mouseleave', () => {
                        item.style.backgroundColor = 'transparent';
                    });
                    
                    item.addEventListener('click', () => {
                        insertExercise(textarea, exercise);
                        hideAutocomplete(autocompleteDiv);
                    });
                    
                    autocompleteDiv.appendChild(item);
                });
                
                // Position the autocomplete below the textarea
                const textareaRect = textarea.getBoundingClientRect();
                const editorComponent = textarea.closest('.component-code-editor');
                const componentRect = editorComponent.getBoundingClientRect();
                
                // Position relative to component
                autocompleteDiv.style.left = '10px';
                autocompleteDiv.style.top = (textareaRect.bottom - componentRect.top + 5) + 'px';
                
                console.log('Before setting display - current value:', autocompleteDiv.style.display);
                autocompleteDiv.style.display = 'block';
                console.log('After setting display - new value:', autocompleteDiv.style.display);
                console.log('Computed display:', window.getComputedStyle(autocompleteDiv).display);
                
                // Force a reflow
                autocompleteDiv.offsetHeight;
                
                console.log('Final check - display:', autocompleteDiv.style.display);
                console.log('Autocomplete element:', autocompleteDiv);
            }
            
            function hideAutocomplete(autocompleteDiv) {
                autocompleteDiv.style.display = 'none';
            }
            
            function updateSelection(items, selectedIndex) {
                items.forEach((item, index) => {
                    if (index === selectedIndex) {
                        item.style.backgroundColor = '#3e3e3e';
                    } else {
                        item.style.backgroundColor = 'transparent';
                    }
                });
                
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    items[selectedIndex].scrollIntoView({ block: 'nearest' });
                }
            }
            
            function insertExercise(textarea, exercise) {
                const cursorPos = textarea.selectionStart;
                const textBeforeCursor = textarea.value.substring(0, cursorPos);
                const textAfterCursor = textarea.value.substring(cursorPos);
                
                // Find the start of the current exercise name
                const bracketMatch = textBeforeCursor.match(/\[{1,2}([^\]]*?)$/);
                if (bracketMatch) {
                    const startPos = cursorPos - bracketMatch[1].length;
                    textarea.value = textarea.value.substring(0, startPos) + exercise + textAfterCursor;
                    textarea.selectionStart = textarea.selectionEnd = startPos + exercise.length;
                    textarea.dispatchEvent(new Event('input'));
                    textarea.focus();
                }
            }
            
            function getCaretCoordinates(textarea) {
                const div = document.createElement('div');
                const style = window.getComputedStyle(textarea);
                
                div.style.cssText = `
                    position: absolute;
                    visibility: hidden;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    font-family: ${style.fontFamily};
                    font-size: ${style.fontSize};
                    line-height: ${style.lineHeight};
                    padding: ${style.padding};
                    width: ${textarea.offsetWidth}px;
                `;
                
                const textBeforeCursor = textarea.value.substring(0, textarea.selectionStart);
                div.textContent = textBeforeCursor;
                
                const span = document.createElement('span');
                span.textContent = '|';
                div.appendChild(span);
                
                document.body.appendChild(div);
                
                const rect = textarea.getBoundingClientRect();
                const spanRect = span.getBoundingClientRect();
                
                const coords = {
                    left: spanRect.left - rect.left + textarea.scrollLeft,
                    top: spanRect.top - rect.top + textarea.scrollTop
                };
                
                document.body.removeChild(div);
                return coords;
            }
        }
    };
    
})();
