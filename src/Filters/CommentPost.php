<?php

namespace Blomstra\Spam\Filters;

use Blomstra\Spam\Concerns;
use Blomstra\Spam\Filter;
use Flarum\Post\Event\Saving;
use Illuminate\Contracts\Events\Dispatcher;

class CommentPost
{
    use Concerns\Approval,
        Concerns\Users,
        Concerns\Content;

    public function subscribe(Dispatcher $events): void
    {
        // This class is disabled, skip.
        if (in_array(static::class, Filter::$disabled)) return;

        $events->listen(Saving::class, [$this, 'filter']);
    }

    public function filter(Saving $event): void
    {
        $discussion = $event->post->discussion;
        $post = $event->post;

        // Anonymous user
        $anonymous = $post->user === null;

        // When the user edits their first post, we need to rerun the check and hold for moderation.
        $editsFirstPost = ! $anonymous
            && $post->user->posts()->count() === 1
            && $post->user->posts()->first()->is($event->post);

        if (
            // Let's check integrity of posts when the change is made by the post author only.
            $event->actor->is($post->user)
            // Ignore any elevated user.
            && ! $this->isElevatedUser($event->actor)
            // Only run the check with authors that are anonymous, new or are posting for the first time.
            && ($anonymous || ($this->isFreshUser($post->user) || $editsFirstPost))
            // Discussions that are hidden don't need to be checked.
            && $discussion->hidden_at === null
            // Only test against comment posts for now (no event posts for instance)
            && $post->type === 'comment'
            // Now actually check whether the content contains content we MAY consider spam.
            && $this->containsProblematicContent($post->content, $event->actor)) {
            $this->requireApproval($post, 'contains URL or email address');
        }
    }
}
