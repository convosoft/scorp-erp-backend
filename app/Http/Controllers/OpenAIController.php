<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIController extends Controller
{
    public function generateSop(Request $request)
    {
        // ✅ Step 1: Validation using Validator::make()
        $validator = \Validator::make(
            $request->all(),
            [
                'student_name' => 'required|string',
                'age' => 'nullable|string',
                'nationality' => 'required|string',
                'university_name' => 'required|string',
                'program_name' => 'required|string',
                'previous_education' => 'required|string',
                'academic_achievements' => 'required|string',
                'career_goals' => 'nullable|string',
                'reason_for_university' => 'nullable|string',
                'reason_for_course' => 'nullable|string',
                'financial_support' => 'nullable|string',
                'extracurricular_activities' => 'nullable|string',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // ✅ Step 2: Build details dynamically for filled fields
        $details = [];
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $label = ucwords(str_replace('_', ' ', $key));
                $details[] = "- {$label}: {$value}";
            }
        }

        $detailsText = implode("\n", $details);

        // ✅ Step 3: Build AI prompt
        $prompt = "
        Write a professional and personalized Statement of Purpose (SOP) for a student applying to a university for higher studies.

        Details:
        {$detailsText}

        Guidelines:
        - Write in a natural, academic, and formal tone suitable for university admission.
        - Structure the SOP into paragraphs like 'Introduction', 'Academic Background', 'Why This Course', 'Why This University', and 'Career Goals'.
        - Format the response in Markdown (headings, bold, paragraphs).
        ";

        // ✅ Step 4: Call OpenAI API
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert academic writer who drafts SOPs for university admissions.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        // ✅ Step 5: Convert text to HTML for Summernote
        $sopText = $response->choices[0]->message->content ?? 'No SOP generated.';
        $sopHtml = Str::markdown($sopText);

        // ✅ Step 6: Return HTML
        return response($sopHtml, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
