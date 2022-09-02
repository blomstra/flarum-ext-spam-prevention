<?php

namespace Blomstra\Spam\Filters;

use Blomstra\Spam\Concerns;
use FoF\UserBio\Event\BioChanged;
use Illuminate\Contracts\Events\Dispatcher;

class UserBio
{
    use Concerns\Users,
        Concerns\Content;

    public function subscribe(Dispatcher $events)
    {
        $events->listen(BioChanged::class, [$this, 'filter']);
    }

    public function filter(BioChanged $event)
    {
        if(
            $event->actor->is($event->user)
            && $this->isFreshUser($event->user)
            && $this->containsProblematicContent($event->user->bio)
        ) {
            $user = $event->user;

            $originalBio = $user->getOriginal('bio');

            if (! $this->containsProblematicContent($originalBio)) {
                $user->bio = $originalBio;
            } else {
                $user->bio = '[Bio has been auto moderated]';
            }

            $user->isDirty('bio') && $user->save();
        }
    }
}
