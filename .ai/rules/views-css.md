---
paths:
  - 'resources/views/**,resources/css/app.css'
---

# Views Css

## Admin rich-text bodies use .rich-text, never prose
The typography plugin is not installed, so `prose`/`[&_h2]:` classes rendered editor HTML as flat text (headings body-size, no paragraph/heading/list gaps). Wrap every `{!! $x->body !!}` from a Filament RichEditor in `class="rich-text ... max-w-none text-ink-muted text-justify hyphens-auto"`. `.rich-text` in app.css (@layer components, so utilities in "Edit as HTML" markup still win) mirrors the editor's fi-prose spacing: 1rem between blocks, h1–h4 sizes, lists, links, tables, and keeps empty <p> as a blank line. Editor alignment buttons emit inline text-align, which overrides the wrapper's justify. Pinned by ContentAlignmentTest.
