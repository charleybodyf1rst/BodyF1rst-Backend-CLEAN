<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE EXTENSION IF NOT EXISTS vector");
        DB::statement("
            CREATE TABLE documents(
                id bigserial PRIMARY KEY,
                content text NOT NULL,
                embedding vector(1536),
                created_at timestamp, 
                updated_at timestamp
            )");
        DB::statement("CREATE INDEX ON documents USING hnsw (embedding vector_cosine_ops)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS documents");
    }
};
