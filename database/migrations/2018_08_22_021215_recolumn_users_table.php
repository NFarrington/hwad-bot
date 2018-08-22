<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RecolumnUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name', 'email', 'password');
            $table->string('uid')->unique()->after('id');
            $table->string('username')->after('uid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uid', 'username');
            $table->string('name')->after('id');
            $table->string('email')->unique()->after('name');
            $table->string('password')->after('password');
        });
    }
}
