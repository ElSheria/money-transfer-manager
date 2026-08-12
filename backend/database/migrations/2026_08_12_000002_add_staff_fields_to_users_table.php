<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 24)->default('manager')->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('agency_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->after('agency_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agency_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
