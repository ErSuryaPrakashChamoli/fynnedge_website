---
paths:
  - 'app/Modules/**/Models/*.php'
---

# Models

## Models under app/Modules/** need an explicit newFactory() override
Laravel's default HasFactory resolution maps App\Modules\Foo\Models\Bar to Database\Factories\Modules\Foo\Models\BarFactory (mirrors the full sub-namespace after the app root, not just the class basename). This project keeps all factories flat in database/factories/ regardless of the model's namespace, so every Modules-namespaced model must add: `protected static function newFactory(): BarFactory { return BarFactory::new(); }`. Without it, `Model::factory()->create()` throws "Class ... not found" — this stayed hidden in Phase 5 because nothing called ::factory() on those models until Phase 6's Customer factory tripped over it.
