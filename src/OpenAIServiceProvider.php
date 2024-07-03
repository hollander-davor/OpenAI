<?php

namespace Hoks\OpenAI;

use Illuminate\Support\ServiceProvider;
use Hoks\OpenAI\OpenAI;
use Hoks\OpenAI\Commands\GenerateAINews;

class OpenAIServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/openai.php' => config_path('openai.php')
        ]);
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAINews::class,
            ]);
        }

    }

    public function register(){
        $this->app->bind('OpenAI',function(){
            return new OpenAI();
        });
    }
}
