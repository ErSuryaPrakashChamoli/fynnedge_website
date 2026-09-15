<?php

namespace App\Filament\Resources\VideoTestimonials\Schemas;

use App\Enums\LoanCategory;
use App\Enums\PublishStatus;
use App\Enums\VideoTestimonialSource;
use App\Models\VideoTestimonial;
use App\Support\Testimonials\VideoTestimonials;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VideoTestimonialForm
{
    /**
     * In kilobytes. config/livewire.php's temporary upload rule must allow at
     * least this much, or the upload fails before this field ever validates.
     */
    public const MAX_VIDEO_SIZE = 20480;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Video')
                    ->description('Short customer clips of 30–90 seconds, filmed upright on a phone, work best.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        ToggleButtons::make('video_source')
                            ->label('Video source')
                            ->options(VideoTestimonialSource::class)
                            ->default(VideoTestimonialSource::Upload)
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                        FileUpload::make('video_path')
                            ->label('Video file')
                            ->disk('public')
                            ->directory('video-testimonials')
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->maxSize(self::MAX_VIDEO_SIZE)
                            ->helperText('MP4 or WebM, up to 20MB. iPhone .mov files need converting to MP4 first. For longer videos, upload to YouTube and use a YouTube link instead.')
                            ->visible(fn (callable $get): bool => self::isSource($get('video_source'), VideoTestimonialSource::Upload))
                            ->required(fn (callable $get): bool => self::isSource($get('video_source'), VideoTestimonialSource::Upload))
                            ->columnSpanFull(),
                        TextInput::make('youtube_url')
                            ->label('YouTube link')
                            ->placeholder('https://www.youtube.com/watch?v=…')
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && VideoTestimonial::youtubeIdFrom((string) $value) === null) {
                                    $fail('Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=… , https://youtu.be/… or a /shorts/ link.');
                                }
                            })
                            ->helperText('Plays through youtube-nocookie.com, so no YouTube cookies are set until the visitor presses play. Shorts links play in a portrait frame.')
                            ->visible(fn (callable $get): bool => self::isSource($get('video_source'), VideoTestimonialSource::YouTube))
                            ->required(fn (callable $get): bool => self::isSource($get('video_source'), VideoTestimonialSource::YouTube))
                            ->columnSpanFull(),
                        FileUpload::make('poster_path')
                            ->label('Cover image')
                            ->image()
                            ->disk('public')
                            ->directory('video-testimonial-covers')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('Optional. Shown before the video plays — a smiling face works best. Portrait, 720 × 1280px. Without one, uploaded videos show their first frame and YouTube videos use YouTube\'s thumbnail.'),
                        TextInput::make('poster_alt')
                            ->label('Cover image alt text'),
                    ]),
                Section::make('Customer')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('role_location')
                            ->label('Role / location')
                            ->maxLength(255)
                            ->helperText('E.g. "Home Loan customer, Pune". Shown under the name.'),
                        TextInput::make('headline')
                            ->maxLength(80)
                            ->helperText('A short hook shown on the video card, e.g. "Home loan approved in 5 days". Leave blank to show "Name\'s story".')
                            ->columnSpanFull(),
                        Textarea::make('quote')
                            ->label('Short quote')
                            ->rows(2)
                            ->maxLength(200)
                            ->helperText('Optional. One or two lines shown under the card.')
                            ->columnSpanFull(),
                        Select::make('loan_category')
                            ->label('Loan type badge')
                            ->options(LoanCategory::class)
                            ->native(false)
                            ->helperText('Optional. Shown as a badge on the card.'),
                        Select::make('rating')
                            ->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'])
                            ->native(false),
                    ]),
                Section::make('Where it appears')
                    ->columnSpanFull()
                    ->components([
                        Select::make('placements')
                            ->label('Show on these pages')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(VideoTestimonials::options())
                            ->helperText('Pick "Every page on the website", or individual pages. Each group also has an "Every ..." option covering all pages of that type.'),
                        Toggle::make('show_as_floating')
                            ->label('Also pop it up in the corner of those pages')
                            ->helperText('A small, muted preview slides into the bottom-left corner a few seconds after the page loads; visitors tap it to watch with sound, or close it. One per page — if several qualify, the lowest sort order wins.'),
                    ]),
                Section::make('Publishing')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower shows first. You can also drag to reorder in the list.'),
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->helperText('Leave blank to publish immediately once status is Published.')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The video stops appearing publicly after this time.'),
                    ]),
            ]);
    }

    /**
     * The field's state is the enum on a freshly loaded record and its string
     * value once the admin has touched the toggle.
     */
    private static function isSource(mixed $state, VideoTestimonialSource $source): bool
    {
        if ($state instanceof VideoTestimonialSource) {
            return $state === $source;
        }

        return is_string($state) && VideoTestimonialSource::tryFrom($state) === $source;
    }
}
