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
        Schema::create('instant_pattern_detections', function (Blueprint $table) {
            $table->string('pid')->index();
            $table->bigInteger('timestamp')->index();
            $table->json('patterns');

            $table->boolean('has_head_shoulder')->default(false);
            $table->boolean('has_multiple_tops_bottoms')->default(false);
            $table->boolean('has_triangle')->default(false);
            $table->boolean('has_wedge')->default(false);
            $table->boolean('has_channel')->default(false);
            $table->boolean('has_double_top_bottom')->default(false);
            $table->boolean('has_trendline')->default(false);
            $table->boolean('has_support_resistance')->default(false);
            $table->boolean('has_pivots')->default(false);
            $table->integer('pattern_count')->default(0)->index();
            $table->timestamps();

            $table->primary(['pid', 'timestamp']);
        });

        DB::statement('CREATE INDEX instant_pattern_detections_created_at_index ON instant_pattern_detections (created_at)');

        DB::statement('CREATE INDEX instant_pattern_detections_has_head_shoulder_index ON instant_pattern_detections (has_head_shoulder) WHERE has_head_shoulder = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_multiple_tops_bottoms_index ON instant_pattern_detections (has_multiple_tops_bottoms) WHERE has_multiple_tops_bottoms = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_triangle_index ON instant_pattern_detections (has_triangle) WHERE has_triangle = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_wedge_index ON instant_pattern_detections (has_wedge) WHERE has_wedge = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_channel_index ON instant_pattern_detections (has_channel) WHERE has_channel = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_double_top_bottom_index ON instant_pattern_detections (has_double_top_bottom) WHERE has_double_top_bottom = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_trendline_index ON instant_pattern_detections (has_trendline) WHERE has_trendline = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_support_resistance_index ON instant_pattern_detections (has_support_resistance) WHERE has_support_resistance = true');
        DB::statement('CREATE INDEX instant_pattern_detections_has_pivots_index ON instant_pattern_detections (has_pivots) WHERE has_pivots = true');

        DB::statement("SELECT create_hypertable('instant_pattern_detections', 'timestamp', migrate_data => true)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instant_pattern_detections');
    }
};
