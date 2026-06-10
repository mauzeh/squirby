<?php
 
 use Illuminate\Database\Migrations\Migration;
 use Illuminate\Database\Schema\Blueprint;
 use Illuminate\Support\Facades\Schema;
 
 return new class extends Migration
 {
     /**
      * Run the migrations.
      */
     public function up(): void
     {
         Schema::table('users', function (Blueprint $table) {
             $table->string('weight_unit', 10)->default('lbs')->after('prefill_suggested_values');
         });
 
         Schema::table('lift_sets', function (Blueprint $table) {
             $table->string('unit', 10)->default('lbs')->after('weight');
         });
 
         Schema::table('personal_records', function (Blueprint $table) {
             $table->string('unit', 10)->default('lbs')->after('weight');
         });
 
         Schema::table('body_logs', function (Blueprint $table) {
             $table->string('unit', 10)->nullable()->after('value');
         });
     }
 
     /**
      * Reverse the migrations.
      */
     public function down(): void
     {
         Schema::table('users', function (Blueprint $table) {
             $table->dropColumn('weight_unit');
         });
 
         Schema::table('lift_sets', function (Blueprint $table) {
             $table->dropColumn('unit');
         });
 
         Schema::table('personal_records', function (Blueprint $table) {
             $table->dropColumn('unit');
         });
 
         Schema::table('body_logs', function (Blueprint $table) {
             $table->dropColumn('unit');
         });
     }
 };
