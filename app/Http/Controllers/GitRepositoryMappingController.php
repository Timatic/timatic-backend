<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\GitRemote;
use App\Exceptions\InvalidGitRemoteException;
use App\Http\Requests\GitRepositoryMappingCollectionRequest;
use App\Http\Resources\GitRepositoryMapping as GitRepositoryMappingResource;
use App\Integrations\GitRepositoryService;
use TiMacDonald\JsonApi\JsonApiResourceCollection;

class GitRepositoryMappingController extends Controller
{
    public function index(GitRepositoryMappingCollectionRequest $request, GitRepositoryService $gitRepositoryService): JsonApiResourceCollection
    {
        try {
            $mapping = $gitRepositoryService->findMappingByRemote(GitRemote::fromUrl($request->string('filter.remote')->toString()));
        } catch (InvalidGitRemoteException) {
            $mapping = null;
        }

        return GitRepositoryMappingResource::collection(collect([$mapping])->filter());
    }
}
