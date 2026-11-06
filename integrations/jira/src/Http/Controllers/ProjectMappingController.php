<?php

declare(strict_types=1);

namespace Timatic\Jira\Http\Controllers;

use App\Http\Controllers\Controller;
use TiMacDonald\JsonApi\JsonApiResourceCollection;
use Timatic\Jira\Http\Resources\ProjectMapping as ProjectMappingResource;
use Timatic\Jira\Models\ProjectMapping;

class ProjectMappingController extends Controller
{
    public function index(): JsonApiResourceCollection
    {
        return ProjectMappingResource::collection(ProjectMapping::query()->active()->orderBy('project_key')->get());
    }
}
