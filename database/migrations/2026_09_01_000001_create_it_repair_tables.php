<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Service type -> assigned technician. Selecting a type on the
        // repair form auto-fills that person's name as the provider.
        Schema::create('it_repair_service_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('provider_name')->nullable();
            $table->timestamps();
        });

        Schema::create('it_repair_requests', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email');
            $table->string('full_name');
            // Free text — an external phone or an internal extension like 666710.
            $table->string('contact_number');
            $table->dateTime('requested_at');
            $table->string('service_type');
            // Snapshot of the assigned technician at the time — auto-filled
            // from it_repair_service_types but editable per request.
            $table->string('provider_name')->nullable();
            $table->text('details');
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('requested_at');
            $table->index('status');
        });

        // Admin-managed evaluation aspects, e.g. "Service Speed".
        Schema::create('it_repair_eval_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One evaluation per completed repair request.
        Schema::create('it_repair_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_repair_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('evaluated_at');
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('evaluated_at');
        });

        // A 1-5 star score per criterion within one evaluation.
        Schema::create('it_repair_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_repair_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('it_repair_eval_criterion_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['it_repair_evaluation_id', 'it_repair_eval_criterion_id'], 'it_repair_eval_score_unique');
        });

        $now = now();

        DB::table('it_repair_service_types')->insert(
            collect(['Server', 'Network', 'IP Phone', 'Computer'])
                ->map(fn (string $name) => [
                    'name' => $name,
                    'provider_name' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );

        DB::table('it_repair_eval_criteria')->insert(
            collect([
                'Service Speed',
                'Communication and Tracking',
                'Problem Solving Ability',
                'Post-Resolution Results',
            ])
                ->map(fn (string $name, int $i) => [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('it_repair_evaluation_scores');
        Schema::dropIfExists('it_repair_evaluations');
        Schema::dropIfExists('it_repair_eval_criteria');
        Schema::dropIfExists('it_repair_requests');
        Schema::dropIfExists('it_repair_service_types');
    }
};
