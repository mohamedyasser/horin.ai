# Task 01: Database Schema & Migrations

**Priority:** P0 (Critical Path)
**Effort:** 2 days
**Dependencies:** None

---

## Objective

Create all database tables, indexes, and relationships needed for the alert system.

---

## Checklist

- [ ] Create `alerts` table migration
- [ ] Create `alert_templates` table migration
- [ ] Create `alert_history` table migration
- [ ] Create `alert_chains` table migration
- [ ] Create `user_alert_preferences` table migration
- [ ] Create `notifications` table migration
- [ ] Create `failed_notifications` table migration
- [ ] Create `alert_backtest_results` table migration
- [ ] Add all database indexes
- [ ] Run migrations and verify

---

## Migration 1: Alerts Table

```bash
php artisan make:migration create_alerts_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('alert_templates')->nullOnDelete();
            $table->foreignUuid('parent_alert_id')->nullable()->constrained('alerts')->nullOnDelete();
            $table->foreignUuid('chain_from_id')->nullable()->constrained('alerts')->nullOnDelete();

            // Alert type configuration
            $table->enum('type', ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation']);
            $table->string('trigger_type'); // target_price, breakout, zone, etc.
            $table->enum('scope', ['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])->default('single_asset');
            $table->enum('direction', ['above', 'below', 'both', 'cross_up', 'cross_down'])->nullable();
            $table->enum('condition_logic', ['single', 'and', 'or'])->default('single');
            $table->json('parameters'); // Type-specific configuration

            // Status & lifecycle
            $table->enum('status', ['active', 'triggered', 'paused', 'expired', 'chained', 'deleted'])->default('active');
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->boolean('is_recurring')->default(false);
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->unsignedInteger('max_triggers')->nullable();
            $table->unsignedInteger('triggered_count')->default(0);

            // Delivery configuration
            $table->json('delivery_config')->nullable();
            $table->json('escalation_config')->nullable();

            // Timing
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('market_hours_only')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
```

---

## Migration 2: Alert Templates Table

```bash
php artisan make:migration create_alert_templates_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();

            $table->enum('type', ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation']);
            $table->string('trigger_type');
            $table->json('default_parameters');
            $table->json('default_delivery_config')->nullable();

            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('usage_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_templates');
    }
};
```

---

## Migration 3: Alert History Table

```bash
php artisan make:migration create_alert_history_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // Denormalized
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('triggered_at');
            $table->decimal('trigger_value', 20, 6)->nullable();
            $table->json('trigger_context'); // Snapshot of conditions

            $table->boolean('notification_sent')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_history');
    }
};
```

---

## Migration 4: Alert Chains Table

```bash
php artisan make:migration create_alert_chains_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_chains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->foreignUuid('trigger_alert_id')->constrained('alerts')->cascadeOnDelete();
            $table->foreignUuid('activate_alert_id')->constrained('alerts')->cascadeOnDelete();

            $table->unsignedInteger('delay_minutes')->default(0);
            $table->unsignedInteger('expires_after_minutes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_chains');
    }
};
```

---

## Migration 5: User Alert Preferences Table

```bash
php artisan make:migration create_user_alert_preferences_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_alert_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();

            $table->json('default_channels')->default('["telegram", "in_app"]');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone')->default('Africa/Cairo');

            $table->unsignedInteger('max_alerts_per_hour')->default(10);
            $table->unsignedInteger('max_alerts_per_day')->default(50);

            $table->boolean('digest_enabled')->default(false);
            $table->time('digest_time')->default('20:00');
            $table->boolean('smart_defaults_enabled')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_alert_preferences');
    }
};
```

---

## Migration 6: Notifications Table

```bash
php artisan make:migration create_alert_notifications_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('alert_history_id')->nullable()->constrained('alert_history')->nullOnDelete();

            $table->string('idempotency_key')->unique();
            $table->string('type'); // Notification class
            $table->enum('channel', ['telegram', 'push', 'email', 'sms', 'in_app']);
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');

            $table->string('title');
            $table->string('title_ar');
            $table->text('body');
            $table->text('body_ar');
            $table->json('data')->nullable(); // Deep linking payload

            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'read'])->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->string('failed_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notifications');
    }
};
```

---

## Migration 7: Failed Notifications Table

```bash
php artisan make:migration create_failed_notifications_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->nullable();
            $table->foreignUuid('alert_id')->nullable();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->text('error');
            $table->json('payload');
            $table->timestamp('failed_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('should_retry')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_notifications');
    }
};
```

---

## Migration 8: Alert Backtest Results Table

```bash
php artisan make:migration create_alert_backtest_results_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_backtest_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('alert_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('lookback_days');
            $table->unsignedInteger('trigger_count');
            $table->json('triggers'); // Array of trigger events

            $table->decimal('avg_return_1d', 8, 4)->nullable();
            $table->decimal('avg_return_1w', 8, 4)->nullable();
            $table->decimal('avg_return_1m', 8, 4)->nullable();
            $table->decimal('win_rate', 5, 4)->nullable();

            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_backtest_results');
    }
};
```

---

## Migration 9: Add Indexes

```bash
php artisan make:migration add_indexes_to_alert_tables
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alerts table indexes
        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'idx_alerts_user_status');
            $table->index(['type', 'status'], 'idx_alerts_type_status');
        });

        // Partial indexes (PostgreSQL specific)
        DB::statement('CREATE INDEX idx_alerts_asset_type_active ON alerts(asset_id, type, status) WHERE status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_price_active ON alerts(asset_id, trigger_type) WHERE type = \'price\' AND status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_expires ON alerts(expires_at) WHERE expires_at IS NOT NULL AND status = \'active\'');
        DB::statement('CREATE INDEX idx_alerts_snoozed ON alerts(snoozed_until) WHERE snoozed_until IS NOT NULL');

        // JSON path index for target_price lookups
        DB::statement('CREATE INDEX idx_alerts_target_price ON alerts USING gin ((parameters->\'target_price\'))');

        // Alert history indexes
        Schema::table('alert_history', function (Blueprint $table) {
            $table->index(['user_id', 'triggered_at'], 'idx_history_user_triggered');
            $table->index(['alert_id', 'triggered_at'], 'idx_history_alert');
            $table->index(['asset_id', 'triggered_at'], 'idx_history_asset');
        });

        // Notifications indexes
        Schema::table('alert_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'idx_notifications_user_status');
        });

        DB::statement('CREATE INDEX idx_notifications_scheduled ON alert_notifications(scheduled_at) WHERE status = \'pending\'');
        DB::statement('CREATE INDEX idx_notifications_escalation ON alert_notifications(escalation_level, escalated_at) WHERE escalation_level > 0');
    }

    public function down(): void
    {
        // Drop partial indexes
        DB::statement('DROP INDEX IF EXISTS idx_alerts_asset_type_active');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_price_active');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_expires');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_snoozed');
        DB::statement('DROP INDEX IF EXISTS idx_alerts_target_price');
        DB::statement('DROP INDEX IF EXISTS idx_notifications_scheduled');
        DB::statement('DROP INDEX IF EXISTS idx_notifications_escalation');

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('idx_alerts_user_status');
            $table->dropIndex('idx_alerts_type_status');
        });

        Schema::table('alert_history', function (Blueprint $table) {
            $table->dropIndex('idx_history_user_triggered');
            $table->dropIndex('idx_history_alert');
            $table->dropIndex('idx_history_asset');
        });

        Schema::table('alert_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_status');
        });
    }
};
```

---

## Verification

After running migrations, verify:

```bash
# Run migrations
php artisan migrate

# Check tables exist
php artisan tinker
>>> Schema::hasTable('alerts')
>>> Schema::hasTable('alert_history')
>>> Schema::hasTable('alert_templates')
>>> Schema::hasTable('alert_chains')
>>> Schema::hasTable('user_alert_preferences')
>>> Schema::hasTable('alert_notifications')
>>> Schema::hasTable('failed_notifications')
>>> Schema::hasTable('alert_backtest_results')
```

---

## Next Task

Proceed to [Task 02: Core Models & Services](./02-core-models.md)
