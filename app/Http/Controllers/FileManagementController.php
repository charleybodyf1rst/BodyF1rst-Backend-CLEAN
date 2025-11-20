<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * File Management Controller
 * Document upload, retrieval, and management
 */
class FileManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:admin', 'role']);
    }

    /**
     * Upload Document
     * POST /api/admin/upload-document
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:reports,certificates,contracts,medical,forms,other',
            'description' => 'nullable|string|max:1000',
            'organization_id' => 'nullable|integer|exists:organizations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');

            // Validate file type
            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/png',
                'text/plain',
            ];

            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not allowed',
                ], 422);
            }

            // Generate unique filename
            $filename = time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file->getClientOriginalName());

            // Store file
            $path = $file->storeAs('documents', $filename, 'public');

            // Create document record
            $document = Document::create([
                'title' => $request->title,
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'category' => $request->category,
                'description' => $request->description,
                'uploaded_by' => Auth::id(),
                'user_id' => Auth::id(),
                'organization_id' => $request->organization_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'filename' => $document->filename,
                    'fileSize' => $document->file_size,
                    'fileType' => $document->file_type,
                    'category' => $document->category,
                    'description' => $document->description,
                    'url' => Storage::url($document->file_path),
                    'downloadUrl' => route('document.download', $document->id),
                    'uploadedBy' => $document->uploaded_by,
                    'uploadedAt' => $document->created_at,
                    'userId' => $document->user_id,
                    'organizationId' => $document->organization_id,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get Documents
     * GET /api/admin/get-documents
     */
    public function getDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'category' => 'string',
            'user_id' => 'integer',
            'organization_id' => 'integer',
            'search' => 'string|max:255',
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

            $query = Document::with(['uploader', 'user', 'organization'])
                ->orderBy('created_at', 'desc');

            // Filters
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('organization_id')) {
                $query->where('organization_id', $request->organization_id);
            }

            if ($request->has('search')) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'LIKE', "%{$request->search}%")
                      ->orWhere('description', 'LIKE', "%{$request->search}%")
                      ->orWhere('filename', 'LIKE', "%{$request->search}%");
                });
            }

            $documents = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedDocuments = $documents->map(function($doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'filename' => $doc->filename,
                    'fileSize' => $doc->file_size,
                    'fileType' => $doc->file_type,
                    'category' => $doc->category,
                    'description' => $doc->description,
                    'url' => Storage::url($doc->file_path),
                    'downloadUrl' => route('document.download', $doc->id),
                    'uploadedBy' => $doc->uploader->name ?? 'Unknown',
                    'uploadedAt' => $doc->created_at,
                    'userId' => $doc->user_id,
                    'userName' => $doc->user->name ?? null,
                    'organizationId' => $doc->organization_id,
                    'organizationName' => $doc->organization->name ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'documents' => $formattedDocuments,
                'pagination' => [
                    'page' => $documents->currentPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'total_pages' => $documents->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load documents',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Download Document
     * GET /api/admin/download-document/{id}
     */
    public function downloadDocument($id)
    {
        try {
            $document = Document::findOrFail($id);

            if (!Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return Storage::disk('public')->download($document->file_path, $document->filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete Document
     * DELETE /api/admin/delete-document/{id}
     */
    public function deleteDocument($id)
    {
        try {
            $document = Document::findOrFail($id);

            // Delete file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            // Delete database record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
