<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

abstract class BaseMasterController extends Controller
{
    protected $model;
    protected $permissionPrefix;
    protected $moduleType;
    protected $entityName;
    protected $tableName;

    /**
     * Pluck
     */
    public function pluck()
    {
        $data = $this->model::pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Listing
     */
    public function index()
    {
        if (!Auth::user()->can('manage ' . $this->permissionPrefix)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $data = $this->model::with(['created_by:id,name'])->get();

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Create
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('create ' . $this->permissionPrefix)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $record = $this->model::create([
            'name' => $request->name,
            'created_by' => Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => "{$this->entityName} Created",
                'message' => "A new {$this->entityName} '{$record->name}' has been created successfully."
            ]),
            'module_id' => $record->id,
            'module_type' => $this->moduleType,
            'notification_type' => "{$this->entityName} Created",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __($this->entityName . ' successfully created.'),
            'data' => $record
        ], 201);
    }

    /**
     * Update
     */
    public function updateRecord(Request $request)
    {
        if (!Auth::user()->can('edit ' . $this->permissionPrefix)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:' . $this->tableName . ',id',
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $record = $this->model::find($request->id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => __($this->entityName . ' not found.')
            ], 404);
        }

        $oldName = $record->name;

        $record->update([
            'name' => $request->name
        ]);

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => "{$this->entityName} Updated",
                'message' => "{$this->entityName} '{$oldName}' updated successfully."
            ]),
            'module_id' => $record->id,
            'module_type' => $this->moduleType,
            'notification_type' => "{$this->entityName} Updated",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __($this->entityName . ' successfully updated.'),
            'data' => $record
        ]);
    }

    /**
     * Delete
     */
    public function destroyRecord(Request $request)
    {
        if (!Auth::user()->can('delete ' . $this->permissionPrefix)) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:' . $this->tableName . ',id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $record = $this->model::find($request->id);

        if (!$record) {
            return response()->json([
                'status' => 'error',
                'message' => __($this->entityName . ' not found.')
            ], 404);
        }

        $name = $record->name;
        $id = $record->id;

        $record->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => "{$this->entityName} Deleted",
                'message' => "{$this->entityName} '{$name}' deleted successfully."
            ]),
            'module_id' => $id,
            'module_type' => $this->moduleType,
            'notification_type' => "{$this->entityName} Deleted",
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __($this->entityName . ' successfully deleted.')
        ]);
    }
}
