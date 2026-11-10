<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inspection checklist that admins maintain: sections ("Engine")
 * containing items ("Oil level and condition"). Nothing is deleted;
 * hiding (is_active = false) keeps old inspection reports intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->restrictOnDelete();
            $table->string('label', 150);
            $table->string('description', 500)->nullable();
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['checklist_section_id', 'label']);
            $table->index(['checklist_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_sections');
    }
};
