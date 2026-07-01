<?php

namespace Kalimulhaq\Qubuilder\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kalimulhaq\Qubuilder\Http\Requests\GetCollectionRequest;
use Kalimulhaq\Qubuilder\Http\Requests\GetResourceRequest;
use Kalimulhaq\Qubuilder\QubuilderServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QubuilderServiceProvider::class,
        ];
    }

    /**
     * Use an in-memory SQLite database for the whole suite.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => 'qubuilder_',
        ]);
    }

    /**
     * Routes used by the Form Request feature tests.
     */
    protected function defineRoutes($router): void
    {
        $router->match(['get', 'post'], '/test/collection', function (GetCollectionRequest $request) {
            return response()->json($request->filters());
        });

        $router->match(['get', 'post'], '/test/resource', function (GetResourceRequest $request) {
            return response()->json($request->filters());
        });
    }

    /**
     * Build the fixture schema once the app (and DB) is booted.
     */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->nullable();
            $table->integer('age')->nullable();
            $table->integer('score')->nullable();
            $table->string('role')->nullable();
            $table->string('type')->nullable();
            $table->string('country')->nullable();
            $table->string('bio')->nullable();
            $table->text('settings')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('status')->nullable();
            $table->float('total')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable();
            $table->string('product_id')->nullable();
            $table->integer('qty')->nullable();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('bio')->nullable();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->integer('rating')->nullable();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('status')->nullable();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->foreignId('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->foreignId('channel_id')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('body')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->morphs('commentable');
            $table->timestamps();
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('reactable');
        });
    }
}
