<?php

namespace App\Filament\Resources\NewsletterSegments\Schemas;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use App\Modules\Newsletter\Models\NewsletterSegment;
use App\Modules\Newsletter\Models\NewsletterSubscriber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Criteria are edited as individual fields but stored in one `criteria` JSON
 * column, so adding a new kind of filter never needs a migration.
 *
 * Subscriber status is deliberately absent: every send is restricted to
 * confirmed, still-subscribed people regardless of segment. Offering "status"
 * here would imply a segment could mail an unsubscribed address.
 */
class NewsletterSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Segment')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')->label('Name')->required()->maxLength(120)->placeholder('Blog subscribers'),
                        Toggle::make('is_active')->label('Available when composing')->default(true),
                        TextInput::make('description')->label('Description')->maxLength(200)->columnSpanFull(),
                    ]),

                Section::make('Who this includes')
                    ->description('Leave everything blank to include every subscribed person. Filters combine — a subscriber must match all of them.')
                    ->columns(2)
                    ->components([
                        Select::make('criteria.sources')
                            ->label('Signed up from')
                            ->multiple()
                            ->options(SubscriptionSource::options())
                            ->placeholder('Anywhere'),
                        Select::make('criteria.categories')
                            ->label('Interested in')
                            ->multiple()
                            ->options(NewsletterCategory::options())
                            ->placeholder('Any topic')
                            ->helperText('Includes people who never changed their preferences — they are opted into everything by default.'),
                        DatePicker::make('criteria.subscribed_after')->label('Subscribed on or after'),
                        DatePicker::make('criteria.subscribed_before')->label('Subscribed on or before'),
                    ]),

                Section::make('Size')
                    ->components([
                        Text::make(fn (Get $get): string => self::matchCount($get('criteria') ?? [])),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private static function matchCount(array $criteria): string
    {
        $count = NewsletterSegment::applyCriteria(
            NewsletterSubscriber::query()->mailable(),
            $criteria,
        )->count();

        return $count.' subscriber(s) match this segment right now. The audience is re-evaluated at send time, so this number moves as people join and leave.';
    }
}
