<?php

declare(strict_types=1);

namespace Timatic\Jira\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Timatic\Jira\Http\Resources\ProjectMapping as ProjectMappingResource;
use Timatic\Jira\Models\ProjectMapping;

class ProjectMappingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProjectMappingResource::collection(ProjectMapping::query()->active()->orderBy('project_key')->get());
    }
}
