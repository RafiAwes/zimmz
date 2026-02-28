<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Page;
use App\Traits\ApiResponseTraits;
use App\Http\Requests\Api\Page\StorePageRequest;
use App\Http\Requests\Api\Page\UpdatePageRequest;
use App\Http\Requests\Api\Faq\UpsertFaqRequest;

class GeneralController extends Controller
{
    use ApiResponseTraits;

    /**
     * Get a specific page by slug.
     */
    public function getPage($title)
    {
        $page = Page::where('title', $title)->first();

        if (! $page) {
            return $this->errorResponse('Page not found.', 404);
        }

        return $this->successResponse($page, 'Page retrieved successfully.');
    }

    /**
     * Create a new page.
     */
    public function createPage(StorePageRequest $request)
    {
        $page = Page::create($request->validated());

        return $this->successResponse($page, 'Page created successfully.', 201);
    }

    /**
     * Update an existing page.
     */
    public function updatePage(UpdatePageRequest $request, $id)
    {
        $page = Page::findOrFail($id);
        $page->update($request->validated());

        return $this->successResponse($page, 'Page updated successfully.', 200);
    }

    /**
     * Delete a page.
     */
    public function deletePage($id)
    {
        $page = Page::findOrFail($id);
        $page->delete();

        return $this->successResponse(null, 'Page deleted successfully.', 200);
    }

    /**
     * Get all active FAQs.
     */
    public function getFaqs()
    {
        $faqs = Faq::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return $this->successResponse($faqs, 'FAQs retrieved successfully.', 200);
    }

    /**
     * Create or update an FAQ.
     */
    public function upsertFaq(UpsertFaqRequest $request)
    {
        $faq = Faq::updateOrCreate(
            ['question' => $request->question],
            $request->validated()
        );

        $message = $faq->wasRecentlyCreated ? 'FAQ created successfully.' : 'FAQ updated successfully.';
        $code = $faq->wasRecentlyCreated ? 201 : 200;

        return $this->successResponse($faq, $message, $code);
    }

    /**
     * Delete an FAQ.
     */
    public function deleteFaq($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();

        return $this->successResponse(null, 'FAQ deleted successfully.');
    }
}
