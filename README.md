# OpenAI
This package is intended for communication with OpenAI.
Installation:
1. composer require davor/open-ai
2. php artisan vendor:publish --tag=config --provider="Hoks\OpenAI\OpenAIServiceProvider"
3. Set up config/openai.php
4. Use package :D

# Code example

```php
/**
 * This example shows how to ask OpenAI to create prompt for creating OpenAI image
 */

//using facade we create client and specify uri for OpenAI API
$askClient = \OpenAI::client('chat/completions');
//we ask for prompt (note that we use ['content'] to retrieve prompt)
$imagePrompt = $askClient->ask('Write best prompt for creating poster of Novak Djokovic being the best tennis player ever')['content'];
//using facade we create ampther client for image
$imageClient = \OpenAI::client('images/generations',60,'dall-e-3');
//we retrieve image url (by default)
$imageUrl = $imageClient->generateImage($imagePrompt)[0];

```
Check command GenerateAINews.php for more use cases, as well as OpenAI class for more options on methods
