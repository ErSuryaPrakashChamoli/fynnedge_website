<?php

namespace App\Filament\Resources\SocialVideos;

use App\Filament\RelationManagers\RestorableAuditLogsRelationManager;
use App\Filament\Resources\SocialVideos\Pages\CreateSocialVideo;
use App\Filament\Resources\SocialVideos\Pages\EditSocialVideo;
use App\Filament\Resources\SocialVideos\Pages\ListSocialVideos;
use App\Filament\Resources\SocialVideos\Schemas\SocialVideoForm;
use App\Filament\Resources\SocialVideos\Tables\SocialVideosTable;
use App\Models\SocialVideo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Videos from FynnEdge's own Instagram, Facebook, LinkedIn and YouTube posts,
 * pinned to pages the same way video testimonials are. Rendered by
 * x-site.social-videos; the section's wording is the "social_videos"
 * Marketing section.
 */
class SocialVideoResource extends Resource
{
    protected static ?string $model = SocialVideo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Social media videos';

    protected static ?string $modelLabel = 'social media video';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SocialVideoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SocialVideosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RestorableAuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialVideos::route('/'),
            'create' => CreateSocialVideo::route('/create'),
            'edit' => EditSocialVideo::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
