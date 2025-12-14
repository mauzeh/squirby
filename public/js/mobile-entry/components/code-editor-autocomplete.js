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
                
                // Position the autocomplete near the cursor
                const coords = getCaretCoordinates(textarea);
                const editorComponent = textarea.closest('.component-code-editor');
                const componentRect = editorComponent.getBoundingClientRect();
                const textareaRect = textarea.getBoundingClientRect();
                
                const autocompleteWidth = 300;
                
                // Calculate initial position: textarea position + caret position within textarea
                let left = (textareaRect.left - componentRect.left) + coords.left;
                const top = (textareaRect.top - componentRect.top) + coords.top + 20; // 20px below cursor
                
                // Check if autocomplete would overflow to the right
                const rightEdge = left + autocompleteWidth;
                const componentWidth = componentRect.width;
                
                if (rightEdge > componentWidth) {
                    // Flip to the left of the cursor
                    left = left - autocompleteWidth;
                    // Ensure it doesn't go off the left edge
                    if (left < 0) {
                        left = 0;
                    }
                    console.log('Autocomplete would overflow - flipping to left');
                }
                
                autocompleteDiv.style.left = left + 'px';
                autocompleteDiv.style.top = top + 'px';
                autocompleteDiv.style.display = 'block';
                autocompleteDiv.style.width = autocompleteWidth + 'px';
                
                console.log('Positioning autocomplete - textarea offset:', {
                    textareaLeft: textareaRect.left - componentRect.left,
                    textareaTop: textareaRect.top - componentRect.top
                });
                console.log('Positioning autocomplete - caret coords:', coords);
                console.log('Positioning autocomplete - final position:', { left, top, rightEdge, componentWidth });
            }
            
            function hideAutocomplete(autocompleteDiv) {
                console.log('hideAutocomplete called');
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
                
                // Find the start of the current exercise name and determine bracket type
                const bracketMatch = textBeforeCursor.match(/\[{1,2}([^\]]*?)$/);
                if (bracketMatch) {
                    const fullMatch = bracketMatch[0];
                    const openingBrackets = fullMatch.substring(0, fullMatch.length - bracketMatch[1].length);
                    const closingBrackets = openingBrackets.replace(/\[/g, ']'); // Convert [ to ]
                    
                    const startPos = cursorPos - bracketMatch[1].length;
                    const exerciseWithClosing = exercise + closingBrackets;
                    
                    textarea.value = textarea.value.substring(0, startPos) + exerciseWithClosing + textAfterCursor;
                    textarea.selectionStart = textarea.selectionEnd = startPos + exerciseWithClosing.length;
                    textarea.dispatchEvent(new Event('input'));
                    textarea.focus();
                }
            }
            
            function getCaretCoordinates(textarea) {
                const div = document.createElement('div');
                const style = window.getComputedStyle(textarea);
                
                // Copy all relevant styles from textarea
                div.style.cssText = `
                    position: absolute;
                    visibility: hidden;
                    white-space: pre-wrap;
                    word-wrap: break-word;
                    font-family: ${style.fontFamily};
                    font-size: ${style.fontSize};
                    line-height: ${style.lineHeight};
                    padding: ${style.padding};
                    border: ${style.border};
                    width: ${textarea.clientWidth}px;
                    top: 0;
                    left: 0;
                `;
                
                const textBeforeCursor = textarea.value.substring(0, textarea.selectionStart);
                div.textContent = textBeforeCursor;
                
                const span = document.createElement('span');
                span.textContent = '|';
                div.appendChild(span);
                
                document.body.appendChild(div);
                
                const spanRect = span.getBoundingClientRect();
                const divRect = div.getBoundingClientRect();
                
                // Calculate position relative to the div (which mimics the textarea)
                // Then adjust for textarea scroll
                const coords = {
                    left: spanRect.left - divRect.left - textarea.scrollLeft,
                    top: spanRect.top - divRect.top - textarea.scrollTop
                };
                
                document.body.removeChild(div);
                
                console.log('getCaretCoordinates:', coords, 'scrollLeft:', textarea.scrollLeft, 'scrollTop:', textarea.scrollTop);
                return coords;
            }
        }
    };
    
})();
