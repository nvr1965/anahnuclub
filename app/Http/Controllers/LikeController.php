<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Post;
use App\Models\Video;
use App\Notifications\ClubActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function toggle(Request $request, Post $post): RedirectResponse
    {
        return $this->like($request, $post, 'post_liked', route('posts.show', $post), $post->title ?: $post->body);
    }

    public function photo(Request $request, Photo $photo): RedirectResponse
    {
        return $this->like($request, $photo, 'photo_liked', route('albums.show', ['album' => $photo->album_id ?? 0, 'photo' => $photo->id]), $photo->caption);
    }

    public function video(Request $request, Video $video): RedirectResponse
    {
        return $this->like($request, $video, 'video_liked', route('videos.show', $video), $video->title);
    }

    private function like(Request $request, Model $model, string $kind, string $url, ?string $excerpt): RedirectResponse
    {
        $user = $request->user();
        $like = $model->likes()->where('user_id', $user->id)->first();
        $subject = $model->getMorphClass().':'.$model->getKey();

        if ($like) {
            $like->delete();
            ClubActivity::retract($model->user, $kind, $user, $subject);
        } else {
            $model->likes()->create(['user_id' => $user->id]);
            ClubActivity::send($model->user, $kind, $user, $url, $subject, $excerpt);
        }

        return back();
    }
}
