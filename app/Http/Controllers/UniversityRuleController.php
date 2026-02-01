<?php

namespace App\Http\Controllers;

use App\Models\UniversityRule;
use App\Models\University;
use App\Models\Homeuniversity;
use Illuminate\Http\Request;

class UniversityRuleController extends Controller
{
    public function getUniversityRules(Request $request)
    {
        if (!\Auth::user()->can('manage university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'university_id' => 'required|integer|exists:universities,id',
                'type' => 'required|integer|in:1,2',
                'rule_type' => 'required|string|in:restriction,requirement,pipeline'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }


        $rules = UniversityRule::with(['created_by:id,name'])
        ->where('university_id', $request->university_id)
        ->where('rule_type', $request->rule_type)
        ->where('type', $request->type)
        ->orderBy('position', 'ASC')
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $rules
        ], 200);
    }

    public function addUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('create university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'university_id' => 'required|integer|exists:universities,id',
                'name' => 'required|string',
                'position' => 'required|integer',
                'type' => 'required|integer|in:1,2',
                'rule_type' => 'required|string|in:restriction,requirement,pipeline'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $rule = UniversityRule::create([
            'university_id' => $request->university_id,
            'name' => $request->name,
            'position' => $request->position,
            'type' => $request->type,
            'rule_type' => $request->rule_type,
            'created_by' => \Auth::id()
        ]);

        if($request->type == 1){
            $university = University::find($request->university_id);
        }else{
            $university = Homeuniversity::find($request->university_id);
        }

          $typetext = $request->type == 1 ? 'international' : 'home';

        // Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' =>  $typetext . ' ' . $university->name . ' ' . $request->rule_type .' created',
                'message' => "A new rule '{$rule->name}' has been created successfully.",
            ]),
            'module_id' => $rule->university_id,
            'module_type' => 'university',
            'notification_type' => 'Rule Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully created.'),
            'data' => $rule
        ], 201);
    }

    public function updateUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:university_rules,id',
                'name' => 'required|string',
                'position' => 'required|integer',
                'type' => 'required|integer|in:1,2',
                'rule_type' => 'required|string|in:restriction,requirement,pipeline'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $rule = UniversityRule::where('id', $request->id)->first();

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => __('University Rule not found or unauthorized.')
            ], 404);
        }

        // Store original data before update
        $originalData = $rule->toArray();

        // Update fields
        $rule->name = $request->name;
        $rule->position = $request->position;
        $rule->rule_type = $request->rule_type;
        $rule->created_by = \Auth::id();

        // Manually update only the updated_at timestamp
        $rule->timestamps = false;  // Disable automatic timestamp updating
        $rule->save();

        // Log changed fields only
          $university = University::find($request->university_id);
        if($request->type == 2){
            $university = Homeuniversity::find($request->university_id);
        }

        // Log activity
      // Log activity
        $typetext = $request->type == 1 ? 'international' : 'home';
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => $typetext . ' ' .$university->name . ' ' . $request->rule_type . ' updated',
                'message' => "The rule has been updated from '{$originalData['name']}' to '{$rule->name}'.",
            ]),
            'module_id' => $rule->university_id,
            'module_type' => 'university',
            'notification_type' => 'Rule Updated',
        ]);


        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully updated.'),
            'data' => $rule
        ], 200);
    }
    public function updateUniversityRulePosition(Request $request)
    {
        if (!\Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:university_rules,id',
                'position' => 'required|integer'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $rule = UniversityRule::where('id', $request->id)->first();

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => __('University Rule not found or unauthorized.')
            ], 404);
        }

        // Store original data before update
        $originalData = $rule->toArray();

        // Update fields
        $rule->position = $request->position;
        $rule->created_by = \Auth::id();

        // Manually update only the updated_at timestamp
        $rule->timestamps = false;  // Disable automatic timestamp updating
        $rule->save();

        // Log changed fields only
        $changes = [];
        foreach ($originalData as $field => $oldValue) {
            if ($rule->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $rule->$field
                ];
            }
        }

        // If there are changes, log the activity
        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'University Rule Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $rule->university_id,
                'module_type' => 'university',
                'notification_type' => 'Rule Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully updated.'),
            'data' => $rule
        ], 200);
    }


    public function deleteUniversityRule(Request $request)
    {
        if (!\Auth::user()->can('delete university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:university_rules,id',
                ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $rule = UniversityRule::where('id', $request->id)->first();

        if (!$rule) {
            return response()->json([
                'status' => 'error',
                'message' => __('University Rule not found or unauthorized.')
            ], 404);
        }

        $ruleName = $rule->name;
        $rule_type = $rule->rule_type;
        $ruleId = $rule->id;

        $university = University::find($rule->university_id);
        if($request->type == 2){
            $university = Homeuniversity::find($rule->university_id);
        }

        $rule->delete();

        // Log activity



        // Log activity
          $typetext = $request->type == 1 ? 'international' : 'home';
        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $typetext . ' ' .$university->name . ' ' . $rule_type .' deleted',
                'message' => "A new rule '{$ruleName}' has been deleted successfully.",
            ]),
            'module_id' => $rule->university_id,
            'module_type' => 'university',
            'notification_type' => 'Rule Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('University Rule successfully deleted.')
        ], 200);
    }
}
