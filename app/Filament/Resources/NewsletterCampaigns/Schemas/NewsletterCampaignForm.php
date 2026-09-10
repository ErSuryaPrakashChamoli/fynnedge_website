<?php

namespace App\Filament\Resources\NewsletterCampaigns\Schemas;

use App\Models\Article;
use App\Modules\Newsletter\Models\NewsletterSegment;
use App\Modules\Newsletter\Models\NewsletterTemplate;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class NewsletterCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campaign')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Campaign name')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Internal only — subscribers never see this.'),
                        TextInput::make('subject')
                            ->label('Subject line')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('preview_text')
                            ->label('Preview text')
                            ->maxLength(150)
                            ->helperText('The grey line the inbox shows next to the subject. Left blank, mail clients pull the first words of the email instead.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Content')
                    ->description('The branded header, footer and unsubscribe link are added automatically — write only the body here.')
                    ->components([
                        Select::make('article_id')
                            ->label('Featured blog article')
                            ->relationship('article', 'title', fn ($query) => $query->published()->latest('published_at'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(self::populateFromArticle(...))
                            ->helperText('Selecting an article fills in the subject and body below from it. Everything stays editable afterwards — and nothing is ever sent automatically when an article is published.'),
                        Select::make('newsletter_template_id')
                            ->label('Start from a template')
                            ->options(fn (): array => NewsletterTemplate::query()->active()->pluck('name', 'id')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                                $template = $state ? NewsletterTemplate::query()->find($state) : null;

                                // Never clobber a body that already has content in it.
                                if ($template && blank(strip_tags((string) $get('content')))) {
                                    $set('content', $template->content);
                                }
                            }),
                        RichEditor::make('content')
                            ->label('Email body')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('cta_label')
                            ->label('Button label')
                            ->maxLength(60)
                            ->placeholder('Read the article'),
                        TextInput::make('cta_url')
                            ->label('Button link')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Clicks on this button are tracked. Leave blank for no button.'),
                    ]),

                Section::make('Audience & schedule')
                    ->columns(2)
                    ->components([
                        Select::make('newsletter_segment_id')
                            ->label('Send to')
                            ->options(fn (): array => NewsletterSegment::query()->active()->pluck('name', 'id')->all())
                            ->searchable()
                            ->placeholder('Everyone who is subscribed')
                            ->helperText('Only confirmed, still-subscribed people are ever included, whichever segment you pick.'),
                        DateTimePicker::make('scheduled_at')
                            ->label('Send at')
                            ->seconds(false)
                            ->minDate(now())
                            ->helperText('Leave blank to send manually from the campaign list.'),
                    ]),
            ]);
    }

    /**
     * Copies the article's own words into the campaign — once, at selection
     * time. The content is a snapshot on purpose: editing the article next
     * month must not rewrite an email that already went out.
     */
    private static function populateFromArticle(Set $set, Get $get, ?string $state): void
    {
        $article = $state ? Article::query()->with('seoMeta')->find($state) : null;

        if (! $article) {
            return;
        }

        $url = route('resources.show', $article);
        $imageUrl = $article->seoOgImageUrl();

        $set('cta_label', 'Read the full article');
        $set('cta_url', $url);

        if (blank($get('subject'))) {
            $set('subject', $article->title);
        }

        if (blank($get('name'))) {
            $set('name', $article->title);
        }

        if (blank($get('preview_text')) && filled($article->excerpt)) {
            $set('preview_text', str($article->excerpt)->limit(140)->toString());
        }

        if (blank(strip_tags((string) $get('content')))) {
            $set('content', self::articleBody($article, $imageUrl));
        }
    }

    private static function articleBody(Article $article, ?string $imageUrl): string
    {
        $image = $imageUrl
            ? '<p><img src="'.e($imageUrl).'" alt="" style="max-width:100%;height:auto;border-radius:8px;"></p>'
            : '';

        return $image
            .'<h2>'.e($article->title).'</h2>'
            .(filled($article->excerpt) ? '<p>'.e($article->excerpt).'</p>' : '');
    }
}
