<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data-preserving: nullable columns only, no existing data touched.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            // owner | manager | cashier. Null = not yet assigned to a tenant.
            $table->string('role')->nullable()->after('outlet_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropConstrainedForeignId('outlet_id');
            $table->dropColumn('role');
        });
    }
};
