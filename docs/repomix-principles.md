# Repomix Principles

Repomix (formerly Repopack) is a tool designed to pack an entire code repository into a single, structured, AI-friendly format (XML, Markdown, JSON, or plain text). This facilitates sending codebase context to Large Language Models (LLMs) such as Claude, GPT, or Gemini (which powers NotebookLM).

## Core Principles

### 1. Structured Context Isolation
* **AI-Friendly Boundaries**: Repomix wraps each file in structured tags (typically XML, e.g., `<file path="...">...</file>`). This provides clear, unambiguous delimiters for the LLM, preventing the model from confusing file contents with metadata or code with surrounding text.
* **Avoid Syntax Collisions**: Internal XML tags are less likely to conflict with syntax (like markdown code fence backticks) inside the source files.

### 2. Token Optimization and Compaction
* **Tree-sitter Code Compression**: Using the `--compress` option parses source code files with Tree-sitter, extracting structural metadata (class, interface, and function signatures) while removing their implementation bodies. This retains complete architectural context while reducing size by 70–80%.
* **Empty Line and Comment Removal**: Stripping code comments, docstrings, and empty lines removes boilerplate tokens that are unnecessary for structural understanding.
* **Targeted Exclusions**: Specifying custom pattern ignores prevents large assets, database seeders, binary assets, and package manager lock files (e.g., `composer.lock`, `package-lock.json`) from bloating the context.

### 3. Git-Aware Context Collection
* **Gitignore Respect**: Repomix automatically honors `.gitignore` rules, preventing temporary logs, caches, build files, and local environments from being processed.
* **Prioritization**: Ranks files according to modification frequency in Git by default, placing active files at the top of the context where the LLM can easily prioritize them.

### 4. Bounded Context and Splitting
* **Split Constraints**: When a repository exceeds token budgets or size limits of a target LLM, Repomix can divide the output into multiple parts (using `splitOutput`).
* **Grouping Integrity**: It guarantees that a single file or directory structure is never chopped in half mid-file, keeping directories grouped together within parts to retain local context.

### 5. Automated Security Analysis
* **Credential Scans**: Scans the codebase for sensitive credentials (such as API keys, private tokens, passwords, and `.env` files) and prevents them from being accidentally bundled and sent to public or third-party AI APIs.

---

## Best Practices for NotebookLM
* **Target Single-File Limits**: NotebookLM restricts sources to **500,000 words** and **200 MB** per file. 
* **Extension Compatibility**: NotebookLM does not directly accept the `.xml` extension. Using `.txt` or `.md` as the file extension while retaining `"style": "xml"` internally ensures the file is accepted by NotebookLM while keeping the XML structure intact for Gemini.
