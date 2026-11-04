<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\FindingPhoto;
use App\Models\NonConformity;
use App\Services\FindingPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Actions taken from the findings table over AJAX: bulk status / delete on the
 * ticked rows, adding photos from a photo cell and removing one in the photo viewer.
 */
class FindingActionsController extends Controller
{
    private const BULK_ACTIONS = ['open', 'closed', 'delete'];

    public function bulk(Request $request, FindingPhotos $photos): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(self::BULK_ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        // ids the user cannot see are silently left out
        $findings = NonConformity::visibleTo($request->user())->with('photos')->whereKey($validated['ids'])->get();

        DB::transaction(function () use ($findings, $validated, $photos) {
            foreach ($findings as $finding) {
                if ($validated['action'] === 'delete') {
                    $photos->deleteAll($finding);
                    $finding->delete();
                } else {
                    $finding->update(['status' => $validated['action'] === 'closed']);
                }
            }
        });

        $message = match ($validated['action']) {
            'delete' => __('nc.deleted', ['count' => $findings->count()]),
            default => __('nc.bulk.statusChanged', ['count' => $findings->count(), 'status' => __('nc.'.$validated['action'])]),
        };

        return response()->json(['message' => $message, 'count' => $findings->count()]);
    }

    /**
     * Add photos to a finding from the table. Auditees may only add repair photos to
     * findings they can see; admins and auditors may add both.
     */
    public function storePhotos(Request $request, NonConformity $finding, string $field, FindingPhotos $photos): JsonResponse
    {
        abort_unless(in_array($field, FindingPhotos::FIELDS, true), 404);
        $this->authorizePhotoChange($request, $finding, $field);

        $validated = $request->validate(
            array_merge_recursive(['photos' => ['required']], FindingPhotos::rules('photos', $finding, $field)),
            ['photos.max' => __('nc.create.photoLimit', ['max' => FindingPhotos::MAX_PER_FIELD])],
        );

        $photos->add($finding, $field, $validated['photos']);

        return response()->json([
            'message' => __('nc.photosSaved', ['count' => count($validated['photos']), 'id' => $finding->id]),
            'count' => count($validated['photos']),
        ]);
    }

    /** Remove one photo, with the same rights as adding it. */
    public function destroyPhoto(Request $request, NonConformity $finding, FindingPhoto $photo, FindingPhotos $photos): JsonResponse
    {
        abort_unless($photo->non_conformity_id === $finding->getKey(), 404);
        $this->authorizePhotoChange($request, $finding, $photo->field);

        $photos->delete($photo);

        return response()->json(['message' => __('nc.photoDeleted', ['id' => $finding->id])]);
    }

    private function authorizePhotoChange(Request $request, NonConformity $finding, string $field): void
    {
        abort_unless(NonConformity::visibleTo($request->user())->whereKey($finding->getKey())->exists(), 404);
        abort_if($request->user()->otorisasi === Role::Auditee && $field !== 'foto_perbaikan', 403);
    }
}
