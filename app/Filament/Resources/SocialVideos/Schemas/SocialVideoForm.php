<?php

namespace App\Filament\Resources\SocialVideos\Schemas;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use App\Enums\SocialPlatform;
use App\Enums\VideoTestimonialSource;
use App\Filament\Forms\Components\CoverFramePicker;
use App\Filament\Resources\VideoTestimonials\Schemas\VideoTestimonialForm;
use App\Filament\Schemas\HindiTranslationSection;
use App\Models\SocialVideo;
use App\Support\SocialVideos\SocialVideos;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SocialVideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Video')
                    ->description('Upload the same clip you posted. Short, upright reels of up to 90 seconds work best.')
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
                            ->directory('social-videos')
                            ->acceptedFileTypes(['video/mp4', 'video/webm'])
                            ->maxSize(VideoTestimonialForm::MAX_VIDEO_SIZE)
                            ->live()
                            ->helperText('MP4 or WebM, up to 20MB. iPhone .mov files need converting to MP4 first. For longer videos, upload to YouTube and use a YouTube link instead.')
                            ->visible(fn (Get $get): bool => self::isSource($get, VideoTestimonialSource::Upload))
                            ->required(fn (Get $get): bool => self::isSource($get, VideoTestimonialSource::Upload))
                            ->columnSpanFull(),
                        TextInput::make('youtube_url')
                            ->label('YouTube link')
                            ->placeholder('https://www.youtube.com/shorts/…')
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && SocialVideo::youtubeIdFrom((string) $value) === null) {
                                    $fail('Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=… , https://youtu.be/… or a /shorts/ link.');
                                }
                            })
                            ->helperText('Plays through youtube-nocookie.com, so no YouTube cookies are set until the visitor presses play. Shorts links play in a portrait frame.')
                            ->visible(fn (Get $get): bool => self::isSource($get, VideoTestimonialSource::YouTube))
                            ->required(fn (Get $get): bool => self::isSource($get, VideoTestimonialSource::YouTube))
                            ->columnSpanFull(),
                    ]),
                Section::make('Cover')
                    ->description('What visitors see on the card before the video plays. Pick a moment from the video, or upload your own cover image.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        CoverFramePicker::make('cover_time')
                            ->label('Pick a moment from the video')
                            ->helperText('Play or drag the video to the moment you want, pause, then press "Use this frame". The preview is cropped exactly like the card on the website.')
                            ->rules(['nullable', 'numeric', 'min:0', 'max:3600'])
                            ->visible(fn (Get $get): bool => self::isSource($get, VideoTestimonialSource::Upload))
                            ->columnSpanFull(),
                        FileUpload::make('poster_path')
                            ->label('Or upload a cover image')
                            ->image()
                            ->disk('public')
                            ->directory('social-video-covers')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('Optional, e.g. a designed thumbnail or the post\'s own cover. Landscape 1280 × 800px (16:10); upright images are cropped to the middle. When set, it is shown instead of the frame picked above. Without either, uploaded videos show their first frame and YouTube videos use YouTube\'s thumbnail.'),
                        TextInput::make('poster_alt')
                            ->label('Cover image alt text')
                            ->maxLength(255),
                    ]),
                Section::make('Post details')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('platform')
                            ->label('Posted on')
                            ->options(SocialPlatform::class)
                            ->default(SocialPlatform::Instagram)
                            ->native(false)
                            ->required()
                            ->helperText('Shown as a badge on the card, and names the "View on …" link.'),
                        TextInput::make('post_url')
                            ->label('Link to the original post')
                            ->placeholder('https://www.instagram.com/reel/…')
                            ->maxLength(255)
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! preg_match('#^https?://#i', (string) $value)) {
                                    $fail('The post link must start with https:// (or http://).');
                                }
                            })
                            ->helperText('Optional. Adds a "View on Instagram" link under the video, so visitors can like, comment and follow.'),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(80)
                            ->helperText('A short hook shown on the card and in the player, e.g. "3 ways to cut your EMI". About 30 characters fit on the card\'s one line; the rest shows in the player.')
                            ->columnSpanFull(),
                        TextInput::make('tagline')
                            ->label('Short info')
                            ->maxLength(60)
                            ->placeholder('e.g. Home loan tips for first-time buyers')
                            ->helperText('A few words about the video, shown in bold under the title. About 25 characters fit on one line; with no post link it can run to two lines (about 50). Leave blank to show the platform name.')
                            ->columnSpanFull(),
                        Textarea::make('caption')
                            ->rows(2)
                            ->maxLength(200)
                            ->helperText('Optional. One short line from the post\'s caption, shown at the foot of the card (about 40 characters fit).')
                            ->columnSpanFull(),
                    ]),
                Section::make('Where it appears')
                    ->description('The section\'s heading and intro are edited under Content → Marketing sections, placement "Social media videos section".')
                    ->columnSpanFull()
                    ->components([
                        Select::make('placements')
                            ->label('Show on these pages')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(SocialVideos::options())
                            ->default([FaqPlacement::Home->value])
                            ->helperText('Pick "Every page on the website", or individual pages. Each group also has an "Every ..." option covering all pages of that type. On the homepage the section sits just above the customer video testimonials.'),
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
                            ->visible(fn (Get $get): bool => $get->enum('status', PublishStatus::class, isNullable: true) === PublishStatus::Published),
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The video stops appearing publicly after this time.'),
                    ]),
                HindiTranslationSection::make([
                    TextInput::make('title_hi')
                        ->label('Title (Hindi)')
                        ->maxLength(80)
                        ->columnSpanFull(),
                    TextInput::make('tagline_hi')
                        ->label('Short info (Hindi)')
                        ->maxLength(60)
                        ->columnSpanFull(),
                    Textarea::make('caption_hi')
                        ->label('Caption (Hindi)')
                        ->rows(2)
                        ->maxLength(200)
                        ->columnSpanFull(),
                    TextInput::make('poster_alt_hi')
                        ->label('Cover image alt text (Hindi)')
                        ->maxLength(255),
                ]),
            ]);
    }

    private static function isSource(Get $get, VideoTestimonialSource $source): bool
    {
        return $get->enum('video_source', VideoTestimonialSource::class, isNullable: true) === $source;
    }
}
