<?php

namespace Tests\Feature\Exam;

use App\Enums\UserRole;
use App\Exam\QuestionImport;
use App\Models\ExamQuestion;
use App\Models\PrepMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role = UserRole::Registrar): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function question(array $overrides = []): array
    {
        return [
            'area' => 'methodology',
            'scenario' => '',
            'question' => 'Which research design best tests whether one thing causes another?',
            'options' => ['A cross-sectional survey', 'A randomised controlled trial', 'A case study', 'A content analysis'],
            'correct_option' => 1,
            'explanation' => 'Random assignment separates cause from coincidence.',
            ...$overrides,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, bool $windows = false): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, QuestionImport::COLUMNS, escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        rewind($handle);
        $content = (string) stream_get_contents($handle);
        if ($windows) {
            $content = mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
        }

        return UploadedFile::fake()->createWithContent('questions.csv', $content);
    }

    public function test_only_membership_staff_keep_the_bank(): void
    {
        $editor = $this->staff(UserRole::Editor);

        foreach ([route('admin.exam.questions.index'), route('admin.exam.questions.create'), route('admin.exam.materials.index'), route('admin.exam.questions.template')] as $url) {
            $this->actingAs($editor)->get($url)->assertForbidden();
            $this->actingAs($this->staff())->get($url)->assertOk();
        }
        $this->actingAs($editor)->post(route('admin.exam.questions.store'), $this->question())->assertForbidden();
    }

    public function test_adding_editing_and_retiring_a_question(): void
    {
        $registrar = $this->staff();

        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question(['correct_option' => -1]))
            ->assertSessionHasErrors(['correct_option' => 'Mark the right answer.']);
        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question(['correct_option' => 5]))
            ->assertSessionHasErrors('correct_option');
        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question(['options' => ['Yes', 'yes']]))
            ->assertSessionHasErrors('options.1');
        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question(['area' => 'astrology']))
            ->assertSessionHasErrors('area');

        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question())
            ->assertRedirect(route('admin.exam.questions.index', ['area' => 'methodology']));
        $this->actingAs($registrar)->post(route('admin.exam.questions.store'), $this->question(['question' => 'Another?', 'add_another' => true]))
            ->assertRedirect(route('admin.exam.questions.create', ['area' => 'methodology']));

        $question = ExamQuestion::query()->oldest('id')->firstOrFail();
        $this->assertSame(1, $question->correct_option);
        $this->assertSame('Research methodology', $question->areaLabel());
        $this->assertFalse($question->isAdvanced());

        $this->actingAs($registrar)->put(route('admin.exam.questions.update', $question), $this->question(['options' => ['Survey', 'Trial'], 'correct_option' => 1]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Survey', 'Trial'], $question->refresh()->options);

        $this->actingAs($registrar)->patch(route('admin.exam.questions.retire', $question), ['retired' => true]);
        $this->assertNotNull($question->refresh()->retired_at);

        // The ledger counts questions in use only.
        $this->actingAs($registrar)->get(route('admin.exam.questions.index'))
            ->assertInertia(fn ($page) => $page
                ->where('target', 30)
                ->where('areas.3.value', 'methodology')
                ->where('areas.3.count', 1)
                ->has('questions.data', 1));
        $this->actingAs($registrar)->get(route('admin.exam.questions.index', ['status' => 'retired']))
            ->assertInertia(fn ($page) => $page->has('questions.data', 1)->where('questions.data.0.retired', true));
    }

    public function test_importing_the_committees_spreadsheet(): void
    {
        $registrar = $this->staff();

        $template = $this->actingAs($registrar)->get(route('admin.exam.questions.template'));
        $template->assertOk()->assertDownload('oasis-exam-questions-template.csv');
        $this->assertStringContainsString('EXAMPLE:', $template->streamedContent());

        // The template itself imports nothing: its rows are examples.
        $this->actingAs($registrar)->post(route('admin.exam.questions.import'), [
            'file' => UploadedFile::fake()->createWithContent('template.csv', QuestionImport::template()),
        ])->assertSessionHas('import_result.imported', 0);

        $this->actingAs($registrar)->post(route('admin.exam.questions.import'), ['file' => $this->csv([
            ['Literature review', '', 'What is a systematic review?', 'Any review', 'A review with a stated search and selection method', '', '', '', '', 'B', ''],
            ['methodology', 'A survey of traders in Lafia.', 'What sampling suits it?', 'Convenience', 'Stratified random', 'Snowball', '', '', '', 'b', 'Strata by trade.'],
            ['', '', '', '', '', '', '', '', '', '', ''],
        ])])->assertSessionHas('import_result', ['imported' => 2, 'skipped' => 0, 'errors' => []]);

        $this->assertSame(2, ExamQuestion::query()->count());
        $sampling = ExamQuestion::query()->where('question', 'What sampling suits it?')->sole();
        $this->assertSame(['Convenience', 'Stratified random', 'Snowball'], $sampling->options);
        $this->assertSame(1, $sampling->correct_option);
        $this->assertSame('A survey of traders in Lafia.', $sampling->scenario);

        // Already in the bank: skipped, not doubled.
        $this->actingAs($registrar)->post(route('admin.exam.questions.import'), ['file' => $this->csv([
            ['Literature review', '', 'What is a systematic review?', 'Any review', 'A review with a stated search and selection method', '', '', '', '', 'B', ''],
        ])])->assertSessionHas('import_result', ['imported' => 0, 'skipped' => 1, 'errors' => []]);
    }

    public function test_a_sheet_with_problems_imports_nothing_and_says_which_rows(): void
    {
        $registrar = $this->staff();

        $this->actingAs($registrar)->post(route('admin.exam.questions.import'), ['file' => $this->csv([
            ['Methodology', '', 'Fine question?', 'Yes', 'No', '', '', '', '', 'A', ''],
            ['Astrology', '', 'Wrong area?', 'Yes', 'No', '', '', '', '', 'A', ''],
            ['Methodology', '', 'Answer missing?', 'Yes', 'No', '', '', '', '', 'D', ''],
            ['Methodology', '', 'One option?', 'Only', '', '', '', '', '', 'A', ''],
        ])]);

        $errors = session('import_result.errors');
        $this->assertSame(0, ExamQuestion::query()->count());
        $this->assertContains('Row 3: Choose one of the 14 research areas.', $errors);
        $this->assertContains('Row 4: The right answer must be the letter of a filled-in option (A to B).', $errors);
        $this->assertContains('Row 5: Give at least two options.', $errors);

        $this->actingAs($registrar)->post(route('admin.exam.questions.import'), [
            'file' => UploadedFile::fake()->createWithContent('wrong.csv', "name,email\nAda,ada@example.test\n"),
        ]);
        $this->assertStringStartsWith('The first row must be the template', session('import_result.errors.0'));
    }

    public function test_excels_plain_csv_keeps_its_curly_quotes(): void
    {
        $this->actingAs($this->staff())->post(route('admin.exam.questions.import'), ['file' => $this->csv([
            ['Methodology', '', 'What does “validity” mean?', 'Measuring what you mean to', 'Getting the same result twice', '', '', '', '', 'A', ''],
        ], windows: true)])->assertSessionHas('import_result.imported', 1);

        $this->assertSame('What does “validity” mean?', ExamQuestion::query()->value('question'));
    }

    public function test_prep_materials_files_and_links(): void
    {
        Storage::fake('local');
        $registrar = $this->staff();

        $this->actingAs($registrar)->post(route('admin.exam.materials.store'), ['title' => 'Guide', 'kind' => 'file'])
            ->assertSessionHasErrors(['file' => 'Choose the file to upload.']);
        $this->actingAs($registrar)->post(route('admin.exam.materials.store'), ['title' => 'Guide', 'kind' => 'link', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');

        $this->actingAs($registrar)->post(route('admin.exam.materials.store'), [
            'title' => 'Methodology primer', 'area' => 'methodology', 'kind' => 'file',
            'file' => UploadedFile::fake()->create('primer.pdf', 300, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($registrar)->post(route('admin.exam.materials.store'), [
            'title' => 'OASIS exam overview video', 'kind' => 'link', 'url' => 'https://www.youtube.com/watch?v=abc',
        ])->assertSessionHasNoErrors();

        $file = PrepMaterial::query()->where('title', 'Methodology primer')->sole();
        Storage::disk('local')->assertExists((string) $file->path);
        $this->actingAs($registrar)->get(route('admin.exam.materials.show', $file))->assertOk()->assertDownload('primer.pdf');
        $this->actingAs($this->staff(UserRole::Editor))->get(route('admin.exam.materials.show', $file))->assertForbidden();
        auth()->guard('web')->logout();
        $this->get(route('admin.exam.materials.show', $file))->assertRedirect(route('login'));

        $this->actingAs($registrar)->patch(route('admin.exam.materials.update', $file), ['published' => false]);
        $this->assertFalse($file->refresh()->published);

        $this->actingAs($registrar)->delete(route('admin.exam.materials.destroy', $file));
        Storage::disk('local')->assertMissing((string) $file->path);
        $this->assertSame(1, PrepMaterial::query()->count());
    }
}
