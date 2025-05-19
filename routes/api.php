<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\SavedPostController;

/*
|--------------------------------------------------------------------------
| Private Groups
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get('my-private-groups', function (Request $request) {
    $groups = $request->user()
        ->groups()
        ->where('is_public', false)
        ->withCount('members')
        ->get()
        ->transform(function ($g) use ($request) {
            if ($g->created_by !== $request->user()->id) {
                unset($g->access_key);
            }
            return $g;
        });
    return response()->json($groups, 200);
});

/*
|--------------------------------------------------------------------------
| My Groupstest
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get('my-groups', function (Request $request) {
    return response()->json(
        $request->user()->groups()->withCount('members')->get(),
        200
    );
});

/*
|--------------------------------------------------------------------------
| User Profile, Likes & Saved Posts
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Perfil
    Route::get('user', [UserController::class, 'profile']);
    Route::post('user', [UserController::class, 'updateProfile']);
    Route::put('user',  [UserController::class, 'updateProfile']);

    // Likes
    Route::get   ('user/likes', [LikeController::class, 'myLikes']);
    Route::post  ('posts/{post}/like',   [LikeController::class, 'like']);
    Route::delete('posts/{post}/like',   [LikeController::class, 'unlike']);

    // Saved posts
    Route::get   ('user/saved',           [SavedPostController::class, 'index']);
    Route::post  ('posts/{post}/save',    [SavedPostController::class, 'save']);
    Route::delete('posts/{post}/save',    [SavedPostController::class, 'unsave']);
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::controller(RegisteredUserController::class)->group(function () {
    Route::post('register', 'store')
        ->middleware('guest')
        ->name('register');
});
Route::controller(AuthenticatedSessionController::class)->group(function () {
    Route::post('login', 'store')
        ->middleware('guest')
        ->name('login');
    Route::delete('logout', 'destroy')
        ->middleware('auth:sanctum')
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Public Group Routes
|--------------------------------------------------------------------------
*/
Route::controller(GroupController::class)->group(function () {
    Route::get('groups',      'index');
    Route::get('groups/{id}', 'show');
});

/*
|--------------------------------------------------------------------------
| Protected Group Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::controller(GroupController::class)->group(function () {
        Route::post('groups',             'store');
        Route::put('groups/{id}',         'update');
        Route::delete('groups/{id}',      'destroy');
        Route::post('groups/{id}/join',   'join');
        Route::post('groups/{id}/leave',  'leave');
        Route::post('groups/join-by-code','joinByCode');
    });
});

/*
|--------------------------------------------------------------------------
| Public Post Routes
|--------------------------------------------------------------------------
*/
Route::controller(PostController::class)->group(function () {
    Route::get('posts',      'index');
    Route::get('posts/{id}', 'show');
});

/*
|--------------------------------------------------------------------------
| Public Comment Routes
|--------------------------------------------------------------------------
*/
Route::get('comments',       [CommentController::class, 'index']);
Route::get('comments/{id}',  [CommentController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Protected Post & Comment Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Posts
    Route::controller(PostController::class)->group(function () {
        Route::post('posts',      'store');
        Route::put('posts/{id}',  'update');
        Route::delete('posts/{id}','destroy');
    });

    // Comments
    Route::controller(CommentController::class)->group(function () {
        Route::post('comments',       'store');
        Route::delete('comments/{id}','destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Admin User Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::controller(UserController::class)->group(function () {
        Route::get('users',        'index');
        Route::post('users',       'store');
        Route::get('users/{id}',   'show');
        Route::put('users/{id}',   'update');
        Route::delete('users/{id}','destroy');
    });
});
