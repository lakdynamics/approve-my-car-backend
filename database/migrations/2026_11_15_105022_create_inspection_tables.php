<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An inspection is the inspector's filled-in checklist for one booking.
 * When it starts, the checklist is copied into it (sections, labels,
 * options, limits), so later admin edits never change a past report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('inspector_id')->constrained('users');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order');
            $table->string('comment', 2000)->nullable();
            $table->timestamps();
        });

        Schema::create('inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inspection_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();

            // Copied from the checklist item when the inspection started.
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->string('answer_type', 20);
            $table->json('options')->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->decimal('problem_below', 12, 2)->nullable();
            $table->decimal('problem_above', 12, 2)->nullable();
            $table->unsignedInteger('sort_order');

            // The inspector's answer. outcome is worked out by the server.
            $table->unsignedBigInteger('option_id')->nullable();
            $table->string('option_label', 50)->nullable();
            $table->decimal('number_value', 12, 2)->nullable();
            $table->string('text_value', 1000)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('outcome', 20)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['inspection_section_id', 'sort_order'], 'inspection_answers_section_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_answers');
        Schema::dropIfExists('inspection_sections');
        Schema::dropIfExists('inspections');
    }
};
