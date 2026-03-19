<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 12)->unique(); // M000000001
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->date('date_of_birth');
            $table->enum('gender', ['M', 'F', 'Other']);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->unique();
            $table->string('employer_name', 100);
            $table->enum('employment_status',
                ['Active', 'On-Leave', 'Terminated'])->default('Active');
            $table->string('tax_file_number', 8);
            $table->timestamps();
            $table->softDeletes();

            // Composite index for soft duplicate detection
            $table->index(['first_name', 'last_name', 'date_of_birth'], 'idx_name_dob');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
