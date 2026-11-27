<?php

namespace App\Http\Controllers\Admin\Exam;

use App\Exam\Paper;
use App\Exam\QuestionImport;
use App\Exam\QuestionRules;
use App\Http\Controllers\Controller;
use App\Membership\ApplicationSteps;
use App\Models\ExamQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The exam's question bank: how many questions each area has, the
 * questions themselves, adding and editing them one by one, retiring them,
 * and importing many from the spreadsheet template.
 */
class QuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $area = array_key_exists((string) $request->query('area'), ApplicationSteps::AREAS) ? (string) $request->query('area') : '';
        $status = in_array($request->query('status'), ['retired', 'all'], true) ? (string) $request->query('status') : 'active';
        $search = trim((string) $request->query('search'));

        $counts = ExamQuestion::query()->active()->select('area', DB::raw('count(*) as total'))->groupBy('area')->pluck('total', 'area');

        $questions = ExamQuestion::query()
            ->with('updatedBy:id,first_name,last_name')
            ->when($area !== '', fn (Builder $query) => $query->where('area', $area))
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('retired_at'))
            ->when($status === 'retired', fn (Builder $query) => $query->whereNotNull('retired_at'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('question', 'like', "%{$search}%")->orWhere('scenario', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ExamQuestion $question) => $this->present($question));

        return Inertia::render('admin/exam/questions/index', [
            'areas' => collect(ApplicationSteps::AREAS)->map(fn (array $info, string $key) => [
                'value' => $key,
                'label' => $info['label'],
                'advanced' => $info['advanced'],
                'count' => (int) ($counts[$key] ?? 0),
            ])->values(),
            'target' => ExamQuestion::TARGET_PER_AREA,
            'readiness' => Paper::readiness(),
            'questions' => $questions,
            'filters' => ['area' => $area, 'status' => $status, 'search' => $search],
            'importResult' => $request->session()->get('import_result'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/exam/questions/form', [
            'question' => null,
            'areas' => $this->areaOptions(),
            'defaultArea' => array_key_exists((string) $request->query('area'), ApplicationSteps::AREAS) ? (string) $request->query('area') : '',
            'target' => ExamQuestion::TARGET_PER_AREA,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $question = ExamQuestion::query()->create([...$data, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Question added to {$question->areaLabel()}."]);

        return $request->boolean('add_another')
            ? to_route('admin.exam.questions.create', ['area' => $question->area])
            : to_route('admin.exam.questions.index', ['area' => $question->area]);
    }

    public function edit(ExamQuestion $question): Response
    {
        return Inertia::render('admin/exam/questions/form', [
            'question' => $this->present($question),
            'areas' => $this->areaOptions(),
            'defaultArea' => $question->area,
            'target' => ExamQuestion::TARGET_PER_AREA,
        ]);
    }

    public function update(Request $request, ExamQuestion $question): RedirectResponse
    {
        $question->update([...$this->validated($request), 'updated_by' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Question saved.']);

        return to_route('admin.exam.questions.index', ['area' => $question->area]);
    }

    /**
     * Retire a question (the exam stops using it) or bring it back.
     */
    public function retire(Request $request, ExamQuestion $question): RedirectResponse
    {
        $retire = $request->boolean('retired');
        $question->forceFill(['retired_at' => $retire ? now() : null, 'updated_by' => $request->user()->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $retire ? 'Question retired. The exam will not use it.' : 'Question back in use.']);

        return back();
    }

    public function template(): StreamedResponse
    {
        $csv = QuestionImport::template();

        return response()->streamDownload(fn () => print ($csv), 'oasis-exam-questions-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']],
            ['file.required' => 'Choose the CSV file.', 'file.mimes' => 'Upload the sheet saved as CSV (File, Download or Save as, CSV).'],
        );

        $result = QuestionImport::run($request->file('file'), $request->user());

        if ($result['errors'] === []) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Imported {$result['imported']} questions".($result['skipped'] ? ", skipped {$result['skipped']} already in the bank." : '.')]);
        }

        return back()->with('import_result', $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate(QuestionRules::rules(), QuestionRules::messages());
        $data['options'] = array_values(array_map('trim', $data['options']));
        if ($data['correct_option'] >= count($data['options'])) {
            $request->validate(['correct_option' => ['lt:0']], ['correct_option.lt' => 'Mark one of the options as the right answer.']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ExamQuestion $question): array
    {
        return [
            'id' => $question->id,
            'area' => $question->area,
            'area_label' => $question->areaLabel(),
            'advanced' => $question->isAdvanced(),
            'scenario' => $question->scenario,
            'question' => $question->question,
            'options' => $question->options,
            'correct_option' => $question->correct_option,
            'explanation' => $question->explanation,
            'retired' => $question->retired_at !== null,
            'updated' => $question->updated_at->format('j M Y'),
            'updated_by' => $question->updatedBy?->name,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, advanced: bool}>
     */
    private function areaOptions(): array
    {
        // Questions in use per area, so the form can say how far each area has to go.
        $counts = ExamQuestion::query()->active()->selectRaw('area, count(*) as total')->groupBy('area')->pluck('total', 'area');

        return collect(ApplicationSteps::AREAS)
            ->map(fn (array $info, string $key) => ['value' => $key, 'label' => $info['label'], 'advanced' => $info['advanced'], 'count' => (int) ($counts[$key] ?? 0)])
            ->values()
            ->all();
    }
}
