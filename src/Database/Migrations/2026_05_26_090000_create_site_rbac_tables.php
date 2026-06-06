<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSiteRbacTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('oc_roles')) {
        Schema::create('oc_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
        }

        if (!Schema::hasTable('oc_permissions')) {
        Schema::create('oc_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('group', 100);
        });
        }

        if (!Schema::hasTable('oc_role_permissions')) {
        Schema::create('oc_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->unique(['role_id', 'permission_id']);
            $table->foreign('role_id')->references('id')->on('oc_roles')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('oc_permissions')->cascadeOnDelete();
        });
        }

        if (!Schema::hasTable('oc_site_roles')) {
        Schema::create('oc_site_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('role_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unique(['site_id', 'role_id']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('oc_roles')->cascadeOnDelete();
        });
        }

        if (!Schema::hasTable('oc_site_user_roles')) {
        Schema::create('oc_site_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unique(['site_id', 'user_id', 'role_id']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('oc_roles')->cascadeOnDelete();
        });
        }

        if (!Schema::hasTable('oc_site_user_permissions')) {
        Schema::create('oc_site_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('granted')->default(true);
            $table->unique(['site_id', 'user_id', 'permission_id']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('oc_permissions')->cascadeOnDelete();
        });
        }

        if (!Schema::hasTable('oc_rbac_audit_logs')) {
        Schema::create('oc_rbac_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['site_id', 'created_at']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_rbac_audit_logs');
        Schema::dropIfExists('oc_site_user_permissions');
        Schema::dropIfExists('oc_site_user_roles');
        Schema::dropIfExists('oc_site_roles');
        Schema::dropIfExists('oc_role_permissions');
        Schema::dropIfExists('oc_permissions');
        Schema::dropIfExists('oc_roles');
    }
}
