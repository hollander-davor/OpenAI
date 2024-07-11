<?php

namespace Hoks\OpenAI;

use Illuminate\Support\ServiceProvider;
use Hoks\OpenAI\OpenAI;
use Hoks\OpenAI\Commands\GenerateAINews;
use Hoks\OpenAI\Commands\GenerateAITagsForArticles;
use Hoks\OpenAI\Commands\GenerateAINewsPeriodicaly;


class OpenAIServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/openai.php' => config_path('openai.php')
        ],'config');
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAINews::class,
                GenerateAITagsForArticles::class,
                GenerateAINewsPeriodicaly::class
            ]);
        }

    }

    public function register(){
        $this->app->bind('OpenAI',function(){
            return new OpenAI();
        });
        $this->mergeConfigFrom(__DIR__.'/config/openai.php', 'openai');
    }
}
