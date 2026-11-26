<?php

namespace App\Exam;

use App\Membership\ApplicationSteps;
use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Many questions at once from the spreadsheet template, saved as CSV from
 * Excel or Google Sheets. Every row is checked first: if any row has a
 * problem nothing is imported and each problem is listed with its row
 * number, so the committee fixes the sheet and uploads it again. Rows
 * already in the bank, and the template's example rows, are skipped.
 */
final class QuestionImport
{
    public const COLUMNS = ['area', 'scenario', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'correct', 'explanation'];

    public const MAX_ROWS = 1000;

    /** The template's example rows start with this, so they are never imported. */
    public const EXAMPLE_PREFIX = 'EXAMPLE:';

    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public static function run(UploadedFile $file, ?User $user): array
    {
        $rows = self::read($file);
        if (is_string($rows)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => [$rows]];
        }

        $existing = ExamQuestion::query()->get(['area', 'question'])
            ->map(fn (ExamQuestion $question) => self::fingerprint($question->area, $question->question))
            ->flip();

        $questions = [];
        $errors = [];
        $skipped = 0;

        foreach ($rows as $line => $row) {
            if (trim(implode('', $row)) === '' || str_starts_with(trim($row['question'] ?? ''), self::EXAMPLE_PREFIX)) {
                continue;
            }

            $area = self::area($row['area'] ?? '');
            $options = [];
            foreach (['a', 'b', 'c', 'd', 'e', 'f'] as $letter) {
                $text = trim($row["option_{$letter}"] ?? '');
                if ($text !== '') {
                    $options[] = $text;
                }
            }
            $correct = strtoupper(trim($row['correct'] ?? ''));
            $correctIndex = strlen($correct) === 1 ? ord($correct) - ord('A') : -1;

            $data = [
                'area' => $area ?? trim($row['area'] ?? ''),
                'scenario' => trim($row['scenario'] ?? '') ?: null,
                'question' => trim($row['question'] ?? ''),
                'options' => $options,
                'correct_option' => $correctIndex,
                'explanation' => trim($row['explanation'] ?? '') ?: null,
            ];

            $validator = Validator::make($data, QuestionRules::rules(), QuestionRules::messages());
            $problems = $validator->errors()->unique();
            if ($correctIndex < 0 || $correctIndex >= count($options)) {
                $problems[] = 'The right answer must be the letter of a filled-in option (A to '.ExamQuestion::letter(max(0, count($options) - 1)).').';
            }
            if ($problems !== []) {
                foreach (array_unique($problems) as $problem) {
                    $errors[] = "Row {$line}: {$problem}";
                }

                continue;
            }

            $key = self::fingerprint($data['area'], $data['question']);
            if ($existing->has($key)) {
                $skipped++;

                continue;
            }
            $existing->put($key, true);
            $questions[] = $data;
        }

        if ($errors !== []) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => array_slice($errors, 0, 50)];
        }

        DB::transaction(function () use ($questions, $user) {
            foreach ($questions as $data) {
                ExamQuestion::query()->create([...$data, 'created_by' => $user?->id, 'updated_by' => $user?->id]);
            }
        });

        return ['imported' => count($questions), 'skipped' => $skipped, 'errors' => []];
    }

    /**
     * The template as CSV: the header row and two example rows to copy.
     */
    public static function template(): string
    {
        $handle = self::memory();
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS, escape: '');
        fputcsv($handle, [
            'Research methodology', '', self::EXAMPLE_PREFIX.' Which research design best tests whether one thing causes another?',
            'A cross-sectional survey', 'A randomised controlled trial', 'A case study', 'A content analysis', '', '',
            'B', 'Random assignment to groups is what lets a trial separate cause from coincidence.',
        ], escape: '');
        fputcsv($handle, [
            'Literature review', 'You are reviewing studies on mobile money use among traders in Lafia. Two studies disagree on how often traders use it.',
            self::EXAMPLE_PREFIX.' What should the review do with the disagreement?',
            'Leave out the study that disagrees with most others', 'Report both and look for differences in method or sample that explain it', 'Average the two results', 'Pick the more recent study', '', '',
            'B', 'A good review explains disagreements rather than hiding them.',
        ], escape: '');
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<int, array<string, string>>|string the rows keyed by sheet row number, or the problem
     */
    private static function read(UploadedFile $file): array|string
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        // Excel's plain "CSV" is Windows-1252, not UTF-8 (curly quotes, the naira sign aside).
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $handle = self::memory();
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle, escape: '');
        if (! is_array($header)) {
            return 'The file is empty.';
        }
        $header = array_map(fn ($column) => Str::snake(trim((string) $column)), $header);
        $missing = array_diff(['area', 'question', 'option_a', 'option_b', 'correct'], $header);
        if ($missing !== []) {
            return 'The first row must be the template\'s column names. Missing: '.implode(', ', $missing).'. Download the template and paste your questions into it.';
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            $line++;
            if ($values === [null]) {
                continue;
            }
            $rows[$line] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                return 'The file has more than '.self::MAX_ROWS.' questions. Split it into smaller files.';
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return resource
     */
    private static function memory()
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Could not open a temporary stream.');
        }

        return $handle;
    }

    /**
     * The area key from what the sheet says: its key or its name, in any case.
     */
    private static function area(string $value): ?string
    {
        $value = Str::lower(trim($value));
        foreach (ApplicationSteps::AREAS as $key => $area) {
            if ($value === $key || $value === Str::lower($area['label'])) {
                return $key;
            }
        }

        return null;
    }

    private static function fingerprint(string $area, string $question): string
    {
        return $area.'|'.Str::lower(preg_replace('/\s+/', ' ', trim($question)) ?? $question);
    }
}
