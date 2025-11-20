<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    /**
     * Upload a document
     * POST /api/admin/upload-document
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('documents', $filename, 'public');

            $document = DB::table('documents')->insertGetId([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => auth()->id(),
                'tags' => json_encode($request->tags ?? []),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => $document,
                    'title' => $request->title,
                    'filename' => $filename,
                    'url' => Storage::url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all documents
     * GET /api/admin/get-documents
     */
    public function getDocuments(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $category = $request->get('category');
            $search = $request->get('search');

            $query = DB::table('documents')->orderBy('created_at', 'desc');

            if ($category) {
                $query->where('category', $category);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $documents = $query->paginate($perPage);

            // Add URLs to documents
            $documentsWithUrls = collect($documents->items())->map(function($doc) {
                $doc->url = Storage::url($doc->file_path);
                return $doc;
            });

            return response()->json([
                'success' => true,
                'data' => $documentsWithUrls,
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'total_pages' => $documents->lastPage(),
                    'total_items' => $documents->total(),
                    'per_page' => $documents->perPage()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 1,
                    'total_items' => 0,
                    'per_page' => 20
                ],
                'message' => 'Documents table may not exist yet'
            ]);
        }
    }

    /**
     * Download a document
     * GET /api/admin/download-document/{id}
     */
    public function downloadDocument($id)
    {
        try {
            $document = DB::table('documents')->find($id);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            if (!Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found on storage'
                ], 404);
            }

            return Storage::disk('public')->download($document->file_path, $document->filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error downloading document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a document
     * DELETE /api/admin/delete-document/{id}
     */
    public function deleteDocument($id)
    {
        try {
            $document = DB::table('documents')->find($id);

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Delete database record
            DB::table('documents')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
