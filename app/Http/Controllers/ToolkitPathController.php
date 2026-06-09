<?php

namespace App\Http\Controllers;

use App\Models\ToolkitPath;
use Illuminate\Http\Request;

class ToolkitPathController extends BaseMasterController
{
    protected $model = ToolkitPath::class;

    protected $permissionPrefix = 'Toolkit Path';

    protected $moduleType = 'Toolkit_Path';

    protected $entityName = 'Toolkit Path';

    protected $tableName = 'toolkit_paths';

    public function getToolkitPathPluck()
    {
        return $this->pluck();
    }

    public function getToolkitPaths()
    {
        return $this->index();
    }

    public function addToolkitPath(Request $request)
    {
        return $this->store($request);
    }

    public function updateToolkitPath(Request $request)
    {
        return $this->updateRecord($request);
    }

    public function deleteToolkitPath(Request $request)
    {
        return $this->destroyRecord($request);
    }
}
