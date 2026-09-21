---
paths:
  - 'app/Filament/Resources/Articles/**,app/Models/Article.php,resources/views/home.blade.php'
---

# Models Views

## Home page "Latest articles" is opt-out via articles.show_on_home
HomeController passes latestArticles = published()->shownOnHome()->newestFirst()->limit(3). show_on_home defaults to true (column default + form Toggle default), so new articles appear automatically and admins switch individual ones off (form toggle or the inline "Home page" ToggleColumn in ArticlesTable). The section hides itself when empty. The card markup is shared as x-site.article-card with /resources — edit it there, not in either page.
