<?php

namespace Inovector\Mixpost\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inovector\Mixpost\Actions\ImportPostsFromCsv;
use Inovector\Mixpost\Http\Requests\ImportPosts;
use RuntimeException;

class ImportPostsController extends Controller
{
    public function __invoke(ImportPosts $request, ImportPostsFromCsv $import): RedirectResponse
    {
        try {
            $result = $import($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Imported {$result['created']} post(s), skipped {$result['skipped']}.");
    }
}
