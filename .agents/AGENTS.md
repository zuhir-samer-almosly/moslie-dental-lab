# Project Rules & Customizations

## Workflow Rule: Gemini Planner + Claude Coder

When presented with feature requests, complex coding tasks, or refactoring:

1. **Role & Planning**: Gemini acts strictly as the **Architect & Planner**.
   - Perform research, inspect the codebase, and create/update standard implementation plans.
   - Outline clear technical specifications, edge cases, step-by-step breakdowns, and exact file changes needed.
   - Do NOT write or modify application code directly unless explicitly requested for small quick fixes.

2. **Claude Command Generation**:
   - Provide copy-pasteable terminal commands or structured prompts tailored for Claude Code running in the terminal.
   - Format tasks clearly so they can be directly passed to Claude in the terminal (e.g. `claude -p "..."`).
