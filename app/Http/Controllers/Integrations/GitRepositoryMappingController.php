<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\DataTransferObjects\GitRemote;
use App\Exceptions\InvalidGitRemoteException;
use App\Http\Requests\GitRepositoryMappingCollectionRequest;
use App\Http\Resources\GitRepositoryMapping as GitRepositoryMappingResource;
use App\Integrations\Git\GitRepositoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GitRepositoryMappingController extends Controller
{
    public function index(GitRepositoryMappingCollectionRequest $request, GitRepositoryService $gitRepositoryService): AnonymousResourceCollection
    {
        try {
            $mapping = $gitRepositoryService->findMappingByRemote(GitRemote::fromUrl($request->string('filter.remote')->toString()));
        } catch (InvalidGitRemoteException) {
            $mapping = null;
        }

        return GitRepositoryMappingResource::collection(collect([$mapping])->filter());
    }
}
