<?php

namespace App\Http\Controllers\NonConfirmities;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\NonConformity;
use App\Services\FindingPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Actions taken from the findings table over AJAX: bulk status / delete on the
 * ticked rows, and uploading a photo straight into a photo cell.
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
        $findings = NonConformity::visibleTo($request->user())->whereKey($validated['ids'])->get();

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
     * Upload (or replace) one photo of a finding from the table. Auditees may only add the
     * repair photo of findings they can see; admins and auditors may set both.
     */
    public function photo(Request $request, NonConformity $finding, string $field, FindingPhotos $photos): JsonResponse
    {
        abort_unless(in_array($field, FindingPhotos::FIELDS, true), 404);
        abort_unless(NonConformity::visibleTo($request->user())->whereKey($finding->getKey())->exists(), 404);
        abort_if($request->user()->otorisasi === Role::Auditee && $field !== 'foto_perbaikan', 403);

        $request->validate([
            'photo' => ['required', 'image', 'max:10240', 'dimensions:max_width=10000,max_height=10000'],
        ]);

        $photos->replace($finding, $field, $request->file('photo'));

        return response()->json([
            'message' => __('nc.photoSaved', ['id' => $finding->id]),
            'url' => asset($finding->{$field}),
        ]);
    }
}
