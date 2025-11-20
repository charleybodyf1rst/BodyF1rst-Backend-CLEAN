<?php

namespace App\Http\Controllers;

use App\Models\FAQ;
use App\Models\FAQView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * FAQ Controller
 * Manages frequently asked questions with analytics
 */
class FAQController extends Controller
{
    /**
     * Get FAQs
     * GET /api/faqs
     */
    public function getFAQs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);

            $query = FAQ::where('is_active', true)
                ->orderBy('order', 'asc')
                ->orderBy('created_at', 'desc');

            // Category filter
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('question', 'LIKE', "%{$search}%")
                      ->orWhere('answer', 'LIKE', "%{$search}%")
                      ->orWhere('keywords', 'LIKE', "%{$search}%");
                });
            }

            $faqs = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedFAQs = $faqs->map(function($faq) {
                return [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'keywords' => $faq->keywords,
                    'order' => $faq->order,
                    'views' => $faq->views_count ?? 0,
                    'helpfulCount' => $faq->helpful_count ?? 0,
                    'notHelpfulCount' => $faq->not_helpful_count ?? 0,
                    'createdAt' => $faq->created_at,
                    'updatedAt' => $faq->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'faqs' => $formattedFAQs,
                'pagination' => [
                    'page' => $faqs->currentPage(),
                    'per_page' => $faqs->perPage(),
                    'total' => $faqs->total(),
                    'total_pages' => $faqs->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load FAQs',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get FAQ by ID
     * GET /api/faqs/{id}
     */
    public function getFAQById($id)
    {
        try {
            $faq = FAQ::findOrFail($id);

            // Track view
            $user = Auth::user();
            if ($user) {
                FAQView::create([
                    'faq_id' => $faq->id,
                    'user_id' => $user->id,
                    'viewed_at' => now(),
                ]);
            }

            // Increment view count
            $faq->increment('views_count');

            return response()->json([
                'success' => true,
                'faq' => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'category' => $faq->category,
                    'keywords' => $faq->keywords,
                    'order' => $faq->order,
                    'views' => $faq->views_count,
                    'helpfulCount' => $faq->helpful_count ?? 0,
                    'notHelpfulCount' => $faq->not_helpful_count ?? 0,
                    'createdAt' => $faq->created_at,
                    'updatedAt' => $faq->updated_at,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ not found',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 404);
        }
    }

    /**
     * Mark FAQ as helpful/not helpful
     * POST /api/faqs/{id}/feedback
     */
    public function submitFeedback(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'helpful' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $faq = FAQ::findOrFail($id);

            if ($request->helpful) {
                $faq->increment('helpful_count');
            } else {
                $faq->increment('not_helpful_count');
            }

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your feedback',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit feedback',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get FAQ Analytics
     * GET /api/admin/faq-analytics
     */
    public function getFAQAnalytics(Request $request)
    {
        $this->middleware(['auth:admin', 'role']);

        try {
            // Top viewed FAQs
            $topViewed = FAQ::orderBy('views_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'category' => $faq->category,
                        'views' => $faq->views_count ?? 0,
                    ];
                });

            // Most helpful FAQs
            $mostHelpful = FAQ::orderByRaw('helpful_count - not_helpful_count DESC')
                ->limit(10)
                ->get()
                ->map(function($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'category' => $faq->category,
                        'helpfulCount' => $faq->helpful_count ?? 0,
                        'notHelpfulCount' => $faq->not_helpful_count ?? 0,
                        'helpfulnessRatio' => ($faq->helpful_count + $faq->not_helpful_count) > 0
                            ? round(($faq->helpful_count / ($faq->helpful_count + $faq->not_helpful_count)) * 100, 2)
                            : 0,
                    ];
                });

            // Least helpful FAQs (may need improvement)
            $leastHelpful = FAQ::where(function($q) {
                $q->where('not_helpful_count', '>', DB::raw('helpful_count'))
                  ->orWhere(function($q2) {
                      $q2->where('helpful_count', 0)
                         ->where('not_helpful_count', '>', 0);
                  });
            })
                ->orderByRaw('not_helpful_count - helpful_count DESC')
                ->limit(10)
                ->get()
                ->map(function($faq) {
                    return [
                        'id' => $faq->id,
                        'question' => $faq->question,
                        'category' => $faq->category,
                        'helpfulCount' => $faq->helpful_count ?? 0,
                        'notHelpfulCount' => $faq->not_helpful_count ?? 0,
                    ];
                });

            // Category breakdown
            $categoryStats = FAQ::select('category', DB::raw('count(*) as count'), DB::raw('SUM(views_count) as total_views'))
                ->groupBy('category')
                ->get()
                ->map(function($stat) {
                    return [
                        'category' => $stat->category,
                        'count' => $stat->count,
                        'totalViews' => $stat->total_views ?? 0,
                    ];
                });

            // Overall stats
            $totalFAQs = FAQ::count();
            $activeFAQs = FAQ::where('is_active', true)->count();
            $totalViews = FAQ::sum('views_count') ?? 0;
            $totalFeedback = FAQ::sum('helpful_count') + FAQ::sum('not_helpful_count');
            $helpfulPercentage = $totalFeedback > 0
                ? round((FAQ::sum('helpful_count') / $totalFeedback) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'analytics' => [
                    'overview' => [
                        'totalFAQs' => $totalFAQs,
                        'activeFAQs' => $activeFAQs,
                        'totalViews' => $totalViews,
                        'totalFeedback' => $totalFeedback,
                        'helpfulPercentage' => $helpfulPercentage,
                    ],
                    'topViewed' => $topViewed,
                    'mostHelpful' => $mostHelpful,
                    'leastHelpful' => $leastHelpful,
                    'categoryStats' => $categoryStats,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load FAQ analytics',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Create FAQ (Admin)
     * POST /api/admin/faqs
     */
    public function createFAQ(Request $request)
    {
        $this->middleware(['auth:admin', 'role']);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|string|max:100',
            'keywords' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $faq = FAQ::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'category' => $request->category,
                'keywords' => $request->keywords,
                'order' => $request->order ?? 0,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully',
                'faq' => $faq,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create FAQ',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update FAQ (Admin)
     * PUT /api/admin/faqs/{id}
     */
    public function updateFAQ(Request $request, $id)
    {
        $this->middleware(['auth:admin', 'role']);

        $validator = Validator::make($request->all(), [
            'question' => 'string|max:500',
            'answer' => 'string',
            'category' => 'string|max:100',
            'keywords' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $faq = FAQ::findOrFail($id);

            $faq->update($request->only([
                'question',
                'answer',
                'category',
                'keywords',
                'order',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'FAQ updated successfully',
                'faq' => $faq,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FAQ',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete FAQ (Admin)
     * DELETE /api/admin/faqs/{id}
     */
    public function deleteFAQ($id)
    {
        $this->middleware(['auth:admin', 'role']);

        try {
            $faq = FAQ::findOrFail($id);
            $faq->delete();

            return response()->json([
                'success' => true,
                'message' => 'FAQ deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FAQ',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
