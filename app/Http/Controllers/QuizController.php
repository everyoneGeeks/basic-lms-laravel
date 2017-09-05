<?php

namespace App\Http\Controllers;

use App\UsersQuiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Quiz;
use App\Question;
use App\UsersProblemAnswer;
use Carbon\Carbon;

class QuizController extends Controller
{
    /**
     * QuizController constructor.
     */
    public function __construct()
    {
        $this->middleware('permission:create-quiz', ['only' => ['create']]);
        $this->middleware('permission:edit-quiz', ['only' => ['edit']]);
        $this->middleware('permission:show-quiz-statistics', ['only' => ['chart']]);
        $this->middleware('permission:delete-quiz', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $solved_quizzes = UsersQuiz::with('quiz')->where('user_id', '=', Auth::id())->get();
        return view('quiz.index', compact('solved_quizzes'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (count(Auth::user()->courses) > 0)
            return view('quiz.create');
        else
            return redirect()->back()->with('courses_0', '');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        Quiz::create($request->all());
        return redirect()->route('quizzes.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $quiz = Quiz::findorFail($id)->load('questions');
        if (!Quiz::hasFinished($quiz->end_date) && Quiz::isAvailable($quiz->start_date, $quiz->end_date)) {
            $all_type_questions[] = $quiz->questions;
            $quiz_questions = Question::separateQuestionTypes($all_type_questions, 'MCQ');
            $quiz_problems = Question::separateQuestionTypes($all_type_questions, 'JUDGE');
            $solve_many = $quiz->solve_many;
            $grade = UsersQuiz::where([['user_id', '=', Auth::id()],
                ['quiz_id', '=', $id]])->pluck('grade')->toArray();
            if ($grade == null || $solve_many) {
                if (count($quiz->questions) > 0) {
                    $result = 0;
                    $solved_quiz = UsersQuiz::updateOrCreate([
                        'user_id' => Auth::id(),
                        'quiz_id' => $id,
                    ], [
                        'user_id' => Auth::id(),
                        'quiz_id' => $id,
                        'grade' => $result,
                        'processing_status' => 'PD',
                        'updated_at' => Carbon::now()
                    ]);
                    $return_duration = null;
                    if ($quiz->duration != null) {
                        $duration = Quiz::calculateDuration($quiz->duration, $solved_quiz->updated_at);
                        $duration_modified = explode(' ', $duration);
                        $return_duration = $duration_modified[0] . 'T' . $duration_modified[1];
                    }
                    return view('quiz.show', compact('id', 'solve_many', 'quiz_questions', 'quiz_problems', 'quiz', 'return_duration'));
                } else
                    return redirect()->back()->with('0_questions', '');
            }
            return redirect()->back()->with('done_already', '');
        }
        return redirect()->back()->with('not_available', '');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        $quiz = Quiz::findorFail($id);
        return view('quiz.edit', compact('quiz', 'id'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'start_date' => 'date_format:Y-m-d H:i:s',
            'end_date' => 'date_format:Y-m-d H:i:s',
        ]);

        if ($quiz = Quiz::findOrFail($id)) {
            $updates = $request->all();
            if ($quiz->fill($updates)->save()) {
                $request->session()->flash('success', 'Quiz has been edited successfully');
                return redirect()->back();
            } else {
                $request->session()->flash('failure', 'Error occurred while updating quiz information');
                return redirect()->back();
            }
        } else {
            $request->session()->flash('failure', 'Error occurred while updating quiz information');
            return redirect()->back();
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $quiz = Quiz::findOrFail($id);
        $quiz->delete();
        return redirect()->back();
    }

    public function chart($id)
    {
        $solved_quiz = UsersQuiz::with('user', 'quiz')->where('quiz_id', '=', $id)->get();
        if ($solved_quiz->first() != null) {
            $chart_data[] = ['Name', 'Grade'];
            $full_mark_count = 0;
            $failed_count = 0;
            foreach ($solved_quiz as $quiz) {
                $chart_data[] = [$quiz->user->name, $quiz->grade];
                //for count of full mark
                if ($quiz->grade == $quiz->quiz->full_mark)
                    $full_mark_count++;

                //for count of failed
                if ($quiz->grade < (($quiz->quiz->full_mark) / 2))
                    $failed_count++;
            }
            $problems = Question::where([['quiz_id', '=', $id],
                ['input_format', '!=', null]])->get();
            $problem_return = new Question();
            $Percentage = 100;
            foreach ($problems as $problem) {
                $sum_of_students_grades = UsersProblemAnswer::where('problem_id', '=', $problem->id)->sum('grade');
                $count_of_students = UsersProblemAnswer::where('problem_id', '=', $problem->id)->count();
                $Grade = (($sum_of_students_grades) / ($problem->grade * $count_of_students)) * 100;
                if ($Grade <= $Percentage) {
                    $Percentage = $Grade;
                    $problem_return = $problem;
                }
            }
            return view('quiz.chart')
                ->with('chart_data', json_encode($chart_data))
                ->with('minimum_problem', $problem_return)
                ->with('minimum_problem_percentage', $Percentage)
                ->with('full_mark_count', $full_mark_count)
                ->with('failed_count', $failed_count);
        } else
            return redirect()->back()->with('none-solved', '');
    }

    public function results($id)
    {
        $submissions = UsersQuiz::with('user', 'quiz')->where('quiz_id', '=', $id)->get();
        return view('quiz.submissions.index', compact('submissions'));

    }
}
