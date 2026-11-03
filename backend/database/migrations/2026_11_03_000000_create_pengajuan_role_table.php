<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PENGAJUAN_ROLE', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('role_kode', 40);
            $table->text('informasi_pendukung');
            $table->string('status', 20)->default('MENUNGGU');
            $table->text('alasan_penolakan')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->dateTime('ditinjau_pada')->nullable();
            $table->timestamps();
            $table->foreign('user_id', 'pengajuan_role_user_fk')->references('id')->on('USERS')->restrictOnDelete();
            $table->foreign('unit_id', 'pengajuan_role_unit_fk')->references('id')->on('UNIT_ORGANISASI')->restrictOnDelete();
            $table->foreign('reviewer_id', 'pengajuan_role_reviewer_fk')->references('id')->on('USERS')->nullOnDelete();
            $table->index(['unit_id', 'status'], 'pengajuan_role_unit_status_idx');
            $table->index(['user_id', 'created_at'], 'pengajuan_role_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PENGAJUAN_ROLE');
    }
};
