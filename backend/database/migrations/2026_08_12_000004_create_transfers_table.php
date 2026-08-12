<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('sender_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('receiver_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('source_agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('destination_agency_id')->constrained('agencies')->restrictOnDelete();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->decimal('fee', 14, 2)->default(0);
            $table->string('status', 32)->default('validated');
            $table->string('reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
