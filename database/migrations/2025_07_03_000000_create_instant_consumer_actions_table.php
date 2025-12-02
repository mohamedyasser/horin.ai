<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instant_consumer_actions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('consumer_id');
            $table->uuid('classification_id');
            $table->string('action_type', 50);
            $table->jsonb('action_data')->nullable();
            $table->string('status', 20);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('consumer_id')
                ->references('id')
                ->on('instant_signal_consumers')
                ->onDelete('cascade');

            $table->foreign('classification_id')
                ->references('id')
                ->on('instant_signal_classifications')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instant_consumer_actions');
    }
};
