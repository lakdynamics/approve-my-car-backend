<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each checklist item is answered. "Pick one" items use a reusable
 * answer list (e.g. Condition: Good / Bad / None) whose options say what
 * the answer means (ok, problem, not applicable, info). Number and
 * percentage items can have a red-flag limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_answer_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('checklist_answer_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_answer_set_id')->constrained()->restrictOnDelete();
            $table->string('label', 50);
            $table->string('outcome', 20);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Named by hand: the default names pass MySQL's 64-character limit.
            $table->unique(['checklist_answer_set_id', 'label'], 'answer_options_set_label_unique');
            $table->index(['checklist_answer_set_id', 'sort_order'], 'answer_options_set_order_index');
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            // Items made before this become free-text items.
            $table->string('answer_type', 20)->default('text')->after('description');
            $table->foreignId('checklist_answer_set_id')->nullable()->after('answer_type')
                ->constrained()->restrictOnDelete();
            $table->string('unit', 20)->nullable()->after('checklist_answer_set_id');
            $table->decimal('min_value', 12, 2)->nullable()->after('unit');
            $table->decimal('max_value', 12, 2)->nullable()->after('min_value');
            $table->decimal('problem_below', 12, 2)->nullable()->after('max_value');
            $table->decimal('problem_above', 12, 2)->nullable()->after('problem_below');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_answer_set_id');
            $table->dropColumn(['answer_type', 'unit', 'min_value', 'max_value', 'problem_below', 'problem_above']);
        });

        Schema::dropIfExists('checklist_answer_options');
        Schema::dropIfExists('checklist_answer_sets');
    }
};
