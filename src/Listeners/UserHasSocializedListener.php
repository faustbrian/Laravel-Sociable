<?php

declare(strict_types=1);

/*
 * This file is part of Laravel Sociable.
 *
 * (c) Brian Faust <hello@basecode.sh>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Artisanry\Sociable\Listeners;

use Artisanry\Sociable\Events\UserHasSocialized;
use Artisanry\Sociable\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class UserHasSocializedListener
{
    public function handle(UserHasSocialized $event)
    {
        $profile = $event->profile;

        // build data for the "$event->model" model
        $modelFields = $event->fields->map(function ($item) use ($profile) {
            return $profile[$item];
        })->merge($event->additionalFields)->toArray();

        // create or update an eloquent model
        $model = $event->model;

        if ($model instanceof Model) {
            $model->update($modelFields);
        } else {
            $model = $model::firstOrCreate($modelFields);
        }

        // check if the given model is already authenticated with the provider,
        // if not we will save the received profile data
        try {
            $provider = $model->sociables()
                              ->where('provider', '=', $event->provider)
                              ->where('uid', '=', $profile->get('id'))
                              ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $provider = new Provider(
                $profile->merge([
                    'uid'      => $profile->get('id'),
                    'provider' => $event->provider,
                ])->toArray()
            );

            $model->sociables()->save($provider);
        }

        return new Collection(compact('model', 'provider'));
    }
}
