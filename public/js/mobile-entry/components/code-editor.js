/**
 * Code Editor Component
 * 
 * Enhanced textarea with syntax highlighting overlay
 * Simplified approach with proper alignment
 */

(function() {
    'use strict';
    
    /**
     * Initialize all code editors on the page
     */
    function initCodeEditors() {
        const editors = document.querySelectorAll('.component-code-editor');
        
        editors.forEach(editorContainer => {
            const textarea = editorContainer.querySelector('.code-editor-textarea');
            const wrapper = editorContainer.querySelector('.code-editor-wrapper');
            
            if (!textarea || !wrapper) return;
            
            // Get configuration from data attributes
            const config = {
                mode: textarea.dataset.mode || 'text',
                lineNumbers: textarea.dataset.lineNumbers === 'true',
            };
            
            // Initialize enhanced textarea with syntax highlighting
            if (config.mode === 'wod-syntax') {
                initWodSyntaxEditor(textarea, wrapper, config);
            }
        });
    }
    
    /**
     * Initialize WOD syntax editor with highlighting
     */
    function initWodSyntaxEditor(textarea, wrapper, config) {
        // Set fixed styles on textarea first
        textarea.style.fontFamily = "'Courier New', Courier, monospace";
        textarea.style.fontSize = '14px';
        textarea.style.lineHeight = '1.6';
        textarea.style.padding = '8px';
        
        // Add line numbers if enabled
        if (config.lineNumbers) {
            textarea.style.paddingLeft = '55px';
            addLineNumbers(textarea, wrapper);
        }
        
        // Add syntax highlighting overlay
        addSyntaxHighlighting(textarea, wrapper);
        
        // Enhance textarea behavior
        enhanceTextarea(textarea);
        
        // Add autocomplete (if available)
        if (window.CodeEditorAutocomplete) {
            window.CodeEditorAutocomplete.init(textarea, wrapper);
        }
    }
    
    /**
     * Add line numbers to the editor
     */
    function addLineNumbers(textarea, wrapper) {
        const lineNumbersDiv = document.createElement('div');
        lineNumbersDiv.className = 'code-editor-line-numbers';
        lineNumbersDiv.style.cssText = `
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 45px;
            background-color: #252525;
            border-right: 1px solid #3e3e3e;
            color: #858585;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.6;
            padding: 8px 0;
            text-align: right;
            user-select: none;
            overflow: hidden;
            z-index: 1;
        `;
        
        function updateLineNumbers() {
            const lines = textarea.value.split('\n').length;
            let html = '';
            for (let i = 1; i <= lines; i++) {
                html += `<div style="padding: 0 8px;">${i}</div>`;
            }
            lineNumbersDiv.innerHTML = html;
        }
        
        textarea.addEventListener('input', updateLineNumbers);
        textarea.addEventListener('scroll', () => {
            lineNumbersDiv.scrollTop = textarea.scrollTop;
        });
        
        wrapper.style.position = 'relative';
        wrapper.insertBefore(lineNumbersDiv, textarea);
        updateLineNumbers();
    }
    
    /**
     * Add syntax highlighting overlay
     */
    function addSyntaxHighlighting(textarea, wrapper) {
        const overlay = document.createElement('div');
        overlay.className = 'code-editor-overlay';
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.6;
            padding: 8px;
            padding-left: ${textarea.style.paddingLeft || '8px'};
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow: hidden;
            z-index: 2;
        `;
        
        // Make textarea text transparent but keep caret visible
        textarea.style.color = 'transparent';
        textarea.style.caretColor = '#f2f2f2';
        textarea.style.position = 'relative';
        textarea.style.zIndex = '3';
        textarea.style.background = 'transparent';
        
        // Update overlay on input
        function updateOverlay() {
            overlay.innerHTML = highlightWodSyntax(textarea.value);
        }
        
        textarea.addEventListener('input', updateOverlay);
        textarea.addEventListener('scroll', () => {
            overlay.scrollTop = textarea.scrollTop;
            overlay.scrollLeft = textarea.scrollLeft;
        });
        
        if (!wrapper.style.position || wrapper.style.position === 'static') {
            wrapper.style.position = 'relative';
        }
        wrapper.insertBefore(overlay, textarea);
        updateOverlay();
    }
    
    /**
     * Enhance textarea with better editing features
     */
    function enhanceTextarea(textarea) {
        // Tab key for indentation
        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const spaces = '  '; // 2 spaces
                
                textarea.value = textarea.value.substring(0, start) + spaces + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + spaces.length;
                textarea.dispatchEvent(new Event('input'));
            }
        });
    }
    

    /**
     * Highlight WOD syntax
     */
    function highlightWodSyntax(text) {
        if (!text) return '';
        
        // Escape HTML
        let highlighted = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        
        // Split into lines for line-by-line processing
        const lines = highlighted.split('\n');
        const processedLines = lines.map(line => {
            // Comments (// or --) - must be first to avoid other highlighting
            if (/^\s*(\/\/|--)\s*/.test(line)) {
                return `<span class="cm-wod-comment">${line}</span>`;
            }
            
            // Headers (# Block Name)
            if (/^#{1,3}\s+/.test(line)) {
                return `<span class="cm-wod-header">${line}</span>`;
            }
            
            // Special formats (AMRAP, EMOM, For Time, Rounds)
            if (/^(AMRAP|EMOM|For Time|Rounds)(\s+\d+\w*)?:/i.test(line)) {
                return line.replace(/^(AMRAP|EMOM|For Time|Rounds)(\s+\d+\w*)?:/i, 
                    '<span class="cm-wod-special-format">$1$2:</span>');
            }
            
            // Process exercises and schemes
            let processed = line;
            
            // Loggable exercises [[Exercise]]
            processed = processed.replace(/(\[\[)([^\]]+)(\]\])/g, 
                '<span class="cm-wod-bracket">$1</span><span class="cm-wod-exercise-loggable">$2</span><span class="cm-wod-bracket">$3</span>');
            
            // Info exercises [Exercise]
            processed = processed.replace(/(\[)([^\]]+)(\])/g, 
                '<span class="cm-wod-bracket">$1</span><span class="cm-wod-exercise-info">$2</span><span class="cm-wod-bracket">$3</span>');
            
            // Rep schemes after colon (: 3x8, : 5-5-5, etc.)
            processed = processed.replace(/:\s*(\d+[-x]\d+[-\d]*)/g, 
                ': <span class="cm-wod-scheme">$1</span>');
            
            // Standalone rep schemes at start of line (for AMRAP/EMOM content)
            processed = processed.replace(/^(\s*)(\d+)\s+(<span)/g, 
                '$1<span class="cm-wod-scheme">$2</span> $3');
            
            return processed;
        });
        
        return processedLines.join('\n');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCodeEditors);
    } else {
        initCodeEditors();
    }
    
})();
