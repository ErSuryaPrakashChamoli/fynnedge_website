<?php

use App\Filament\Imports\EmployerRatingImporter;
use App\Models\Lender;
use App\Models\User;
use App\Modules\Eligibility\Models\Employer;
use App\Modules\Eligibility\Models\EmployerCategory;
use App\Modules\Eligibility\Models\EmployerRating;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;

/**
 * Drives the Importer pipeline directly (as Filament's own import job does)
 * rather than through the multi-step upload/column-mapping UI wizard, which
 * isn't practical to exercise in a feature test.
 */
function runEmployerRatingImportRow(array $row): void
{
    $import = Import::create([
        'file_name' => 'employers.csv',
        'file_path' => 'imports/employers.csv',
        'importer' => EmployerRatingImporter::class,
        'total_rows' => 1,
        'user_id' => User::factory()->create()->id,
    ]);

    $columnMap = array_combine(array_keys($row), array_keys($row));

    $importer = new EmployerRatingImporter($import, $columnMap, []);
    $importer($row);
}

it('creates a new employer and rates them against an existing lender category', function () {
    $lender = Lender::factory()->create(['name' => 'ICICI Bank', 'slug' => 'icici-bank']);
    $category = EmployerCategory::factory()->create(['lender_id' => $lender->id, 'key' => 'A', 'label' => 'Category A']);

    runEmployerRatingImportRow([
        'employer_name' => 'Tata Consultancy Services',
        'lender_name' => 'ICICI Bank',
        'category_key' => 'A',
    ]);

    $employer = Employer::query()->where('name', 'Tata Consultancy Services')->first();
    expect($employer)->not->toBeNull();

    $rating = EmployerRating::query()
        ->where('employer_id', $employer->id)
        ->where('lender_id', $lender->id)
        ->first();

    expect($rating)->not->toBeNull();
    expect($rating->employer_category_id)->toBe($category->id);
});

it('reuses an existing employer by name rather than duplicating it', function () {
    $employer = Employer::factory()->create(['name' => 'Infosys']);
    $lender = Lender::factory()->create(['name' => 'Axis Bank', 'slug' => 'axis-bank']);
    EmployerCategory::factory()->create(['lender_id' => $lender->id, 'key' => 'B']);

    runEmployerRatingImportRow([
        'employer_name' => 'Infosys',
        'lender_name' => 'Axis Bank',
        'category_key' => 'B',
    ]);

    expect(Employer::query()->where('name', 'Infosys')->count())->toBe(1);
    expect(EmployerRating::query()->where('employer_id', $employer->id)->count())->toBe(1);
});

it('updates the category on re-import instead of duplicating the rating', function () {
    $lender = Lender::factory()->create(['name' => 'HDFC Bank', 'slug' => 'hdfc-bank']);
    $categoryA = EmployerCategory::factory()->create(['lender_id' => $lender->id, 'key' => 'A']);
    $categoryB = EmployerCategory::factory()->create(['lender_id' => $lender->id, 'key' => 'B']);
    $employer = Employer::factory()->create(['name' => 'Wipro']);
    EmployerRating::query()->create([
        'employer_id' => $employer->id,
        'lender_id' => $lender->id,
        'employer_category_id' => $categoryA->id,
    ]);

    runEmployerRatingImportRow([
        'employer_name' => 'Wipro',
        'lender_name' => 'HDFC Bank',
        'category_key' => 'B',
    ]);

    expect(EmployerRating::query()->where('employer_id', $employer->id)->count())->toBe(1);
    expect(EmployerRating::query()->where('employer_id', $employer->id)->value('employer_category_id'))->toBe($categoryB->id);
});

it('fails the row when the lender does not exist', function () {
    runEmployerRatingImportRow([
        'employer_name' => 'Some Company',
        'lender_name' => 'Nonexistent Bank',
        'category_key' => 'A',
    ]);
})->throws(RowImportFailedException::class);

it('fails the row when the category is not configured for that lender', function () {
    Lender::factory()->create(['name' => 'Kotak Mahindra Bank', 'slug' => 'kotak-mahindra-bank']);

    runEmployerRatingImportRow([
        'employer_name' => 'Some Company',
        'lender_name' => 'Kotak Mahindra Bank',
        'category_key' => 'Z',
    ]);
})->throws(RowImportFailedException::class);
