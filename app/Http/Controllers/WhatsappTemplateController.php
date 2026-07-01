<?php

namespace App\Http\Controllers;

use App\Models\WhatsappTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WhatsappTemplateController extends Controller
{
    public function getWhatsappTemplatePluck(Request $request)
    {
        $is_campaign = $request->is_campaign ?? 0;

        $whatsappTemplates = WhatsappTemplate::where('is_campaign', $is_campaign)
            ->pluck('name', 'id')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $whatsappTemplates
        ], 200);
    }

    public function getWhatsappTemplates()
    {
        if (!Auth::user()->can('manage email template') && !Auth::user()->can('manage whatsapp template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $whatsappTemplates = WhatsappTemplate::with(['creator:id,name'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $whatsappTemplates
        ], 200);
    }

    public function getWhatsappTemplateDetail(Request $request)
    {
        if (!Auth::user()->can('manage email template') && !Auth::user()->can('manage whatsapp template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => ['required', function ($attr, $value, $fail) {
                if ($value == -1) return;
                if (!\DB::table('whatsapp_templates')->where('id', $value)->exists()) {
                    $fail('The selected id is invalid.');
                }
            }],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $whatsappTemplate = WhatsappTemplate::find($request->id);

        return response()->json([
            'status' => 'success',
            'data' => $whatsappTemplate
        ], 200);
    }

    public function addWhatsappTemplate(Request $request)
    {
        if (!Auth::user()->can('create email template') && !Auth::user()->can('create whatsapp template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:whatsapp_templates,name',
            'body' => 'required|string',
            'type' => 'required|string',
            'is_campaign' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $whatsappTemplate = new WhatsappTemplate();
        $whatsappTemplate->status = 1;
        $whatsappTemplate->name = $request->name;
        $whatsappTemplate->body = $request->body;
        $whatsappTemplate->type = $request->type;
        $whatsappTemplate->is_campaign = $request->is_campaign ?? 0;
        $whatsappTemplate->created_by = Auth::user()->creatorId();
        $whatsappTemplate->save();

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $whatsappTemplate->name . " WhatsApp template created",
                'message' => $whatsappTemplate->name . " WhatsApp template created",
            ]),
            'module_id' => $whatsappTemplate->id,
            'module_type' => 'whatsapp_template',
            'notification_type' => 'WhatsApp Template Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('WhatsApp template successfully created.'),
            'data' => $whatsappTemplate
        ], 200);
    }

    public function updateWhatsappTemplate(Request $request)
    {
        if (!Auth::user()->can('edit email template') && !Auth::user()->can('edit whatsapp template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:whatsapp_templates,id',
            'name' => 'required|string|unique:whatsapp_templates,name,' . $request->id . ',id',
            'body' => 'required|string',
            'type' => 'required|string',
            'is_campaign' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $whatsappTemplate = WhatsappTemplate::find($request->id);
        $originalData = $whatsappTemplate->toArray();

        $whatsappTemplate->name = $request->name;
        $whatsappTemplate->body = $request->body;
        $whatsappTemplate->type = $request->type;
        $whatsappTemplate->is_campaign = $request->is_campaign ?? 0;
        $whatsappTemplate->save();

        $changes = [];
        foreach ($originalData as $key => $value) {
            if ($whatsappTemplate->$key != $value && !in_array($key, ['created_at', 'updated_at'])) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => $whatsappTemplate->$key
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $whatsappTemplate->name . " WhatsApp template updated",
                    'message' => 'Fields updated: ' . implode(', ', array_keys($changes)),
                    'changes' => $changes
                ]),
                'module_id' => $whatsappTemplate->id,
                'module_type' => 'whatsapp_template',
                'notification_type' => 'WhatsApp Template Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('WhatsApp template successfully updated.'),
            'data' => $whatsappTemplate
        ], 200);
    }

    public function deleteWhatsappTemplate(Request $request)
    {
        if (!Auth::user()->can('delete email template') && !Auth::user()->can('delete whatsapp template')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:whatsapp_templates,id,created_by,' . Auth::user()->creatorId()
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $whatsappTemplate = WhatsappTemplate::find($request->id);
        $whatsappTemplateName = $whatsappTemplate->name;
        $whatsappTemplateId = $whatsappTemplate->id;

        $whatsappTemplate->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $whatsappTemplateName . " WhatsApp template deleted",
                'message' => $whatsappTemplateName . " WhatsApp template deleted"
            ]),
            'module_id' => $whatsappTemplateId,
            'module_type' => 'whatsapp_template',
            'notification_type' => 'WhatsApp Template Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('WhatsApp template successfully deleted.')
        ], 200);
    }
}
