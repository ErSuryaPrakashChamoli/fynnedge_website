---
paths:
  - 'app/Filament/Resources/Articles/**,app/Models/Article.php,resources/views/resources/**'
---

# Views Resources

## Articles have two image paths: a cover (image_path) and inline editor images
articles.image_path/image_alt (added 2026-09-21) is the optional cover: FileUpload on disk 'public' under articles/, read back via Article::imageUrl(), rendered on the /resources card (aspect-video) and above the article body, and used as the og:image / JSON-LD image fallback when no SEO social image is set. It is registered in MediaRegistry as "Article Cover". Images placed inside the body come from the editor's attach button (HtmlBodyEditor, directory articles/inline) and live only as <img src> in the HTML — they are NOT in MediaRegistry, so Media Governance cannot report them. Admin table (ArticlesTable) defaults to created_at desc so the article just created is on top.
