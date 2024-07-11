<?php

namespace Hoks\OpenAI\Commands;

use Exception;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GenerateAINewsPeriodicaly extends Command
{

    protected $intextImages;
    protected $lastInsertedMediaId;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-news-periodaicaly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates certain number of articles when called. Articles are put in random category that already exists.';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //we ask for site id if either publish or article table needs siteId
        if(config('openai.use_publish') || config('openai.article_site_id')){
            $siteId = config('openai.article_site_id');
        }else{
            $siteId = false;
        }
        $intextImages = config('openai.insert_intext_images');
        $this->intextImages = $intextImages;

        $this->info("\nCreating articles!");
        $articlesNumber = config('openai.articles_number');
        $bar = $this->output->createProgressBar($articlesNumber);

        //pick category
        $categoriesIds = \DB::table(config('openai.categories_table_name'))->select('id')->where('parent_id',0)->where('ban',0)->whereNull('deleted_at')->pluck('id')->toArray();
       
        for($i = 0; $i < $articlesNumber; $i++){
            $categoryId = $this->pickRandom($categoriesIds);
            $categoryName = \DB::table(config('openai.categories_table_name'))->select('name')->where('id',$categoryId)->first()->name;
            $subcategoryId = \DB::table(config('openai.categories_table_name'))->select('id')->where('parent_id',$categoryId)->where('ban',0)->whereNull('deleted_at')->first()->id;

            $this->createArticle($categoryId,$categoryName,$subcategoryId,$i,$siteId);
            $bar->advance();
        }
        $bar->finish();
        $this->info("\nAll articles have been created!");
    }

    /**
     * function that creates article
     */
    protected function createArticle($categoryId,$categoryName, $subcategoryId,$increment,$siteId = false){
        $client = \OpenAI::client('chat/completions');
        $questionsArray = [
            'Ti si novinar u novinskoj agenciji u Srbiji. Napisi naslov za tekst na temu '.$categoryName.' u skladu sa aktuelnim desavanjima u svetu. Naslov napisi uzimajuci u obzir najbolje prakse sa stanovista SEO.',
            'Za ovaj naslov, napisi kraci uvodni tekst uzimajuci u obzir najbolje prakse sa stanovista SEO, a da tekst ne bude veci od 300 karaktera',
            'Za prethodni naslov i uvodni tekst, napisi novinarski tekst vodeci racuna o pravopisu.Tekst vrati u p html tagovima.',
        ];

        foreach($questionsArray as $key => $question){
            if($key == 0){
                $reset = true;
            }else{
                $reset = false;
            }
            $client = $client->dialog($question,config('openai.dialog_max_tokens'),$reset);
        }
        $AIAnswers = $client->getDialogAnswers();

        $heading = str_replace('"','',$AIAnswers[0]);
        $lead = $AIAnswers[1];
        $text = $AIAnswers[2];
        $text = str_replace('```','',$text);
        $text = str_replace('html','',$text);

        $askClient = \OpenAI::client('chat/completions');
        $imagePrompt = $askClient->ask('Write best prompt for creating image that will be used as newspaper article photo. Photo must be award-winning, photorealistic, 8K, natural lighting, HDR, high resolution, shot on IMAX Laser, intricate details. Photo must resemble photos found on stock photos. Use natural colors and ambient, and studio lighting and uneven skin tone. Photo should be created for the following text in Serbian "' . strip_tags($text) . '"  photo, photograph, raw photo, analog photo, 4k, fujifilm photograph ')['content'];
        $imageClient = \OpenAI::client('images/generations',60,'dall-e-3');
        $imageUrl = $imageClient->generateImage($imagePrompt)[0];
        $savedImage = $this->saveImage($imageUrl);

        //intext image
        if($this->intextImages){
            $intextAskClient =  \OpenAI::client('chat/completions');
            $intextImagePrompt = $askClient->ask('Write best prompt for creating image that will be used as newspaper article photo. Photo must be award-winning, photorealistic, 8K, natural lighting, HDR, high resolution, shot on IMAX Laser, intricate details. Photo must resemble photos found on stock photos. Use natural colors and ambient, and studio lighting and uneven skin tone. Photo should be created for the following text in Serbian "' . strip_tags($text) . '"  photo, photograph, raw photo, analog photo, 4k, fujifilm photograph ')['content'];
            $intextImageClient = \OpenAI::client('images/generations',60,'dall-e-3');
            $intextImageUrl = $intextImageClient->generateImage($intextImagePrompt)[0];
            $savedIntextImage = $this->saveImage($intextImageUrl);

            $intextImageSrc = str_replace('.png','_f.png',$savedIntextImage);
            $intextImageHtml = '<figure class="single-news-img"><img m_ext="f" m_id="'.$this->lastInsertedMediaId.'" src="'.$intextImageSrc.'">
                                        <p class="image-source">OpenAI
                                        </p></figure>';
                                    
            $search = '/'.preg_quote('</p>', '/').'/';
            $text = preg_replace($search,'</p>'.$intextImageHtml,$text,1);

        }


        $articleData = [
            'preheading' => null,
            'heading' => $heading,
            'lead' => $lead,
            'text' => $text,
            'author_id' => 0,
            'source' => 'Cubes',
            'category_id'  => $categoryId,
            'subcategory_id' => $subcategoryId,
            'time_created' => now(),
            'time_created_date' => date('Y-m-d'),
            'time_changed' => now(),
            'created_by' => config('openai.user_id'),
            'time_created_real' => now(),
            'updated_by' => config('openai.user_id'),
            'time_updated_real' => now(),
            'published' => 1,
            'publish_at' => now(),
            'more_soon' => now(),
            'og_title' => $heading,
            'push_title' => $heading,
            'has_video' => 0,
            'has_gallery' => 0,
            'views' => 100,
            'shares' => 100,
            'related' => null,
            'show_banners' => 1,
            'comments' => 1,
            'deleted_at' => null,
            'deleted_by' => null,
        ];
        $imageDimensionsArray = config('openai.image_dimensions');
        foreach($imageDimensionsArray as $imageDimension){
            $imageDimensionShort = str_replace('image','',$imageDimension);
            $articleData[$imageDimension] = str_replace('.png',$imageDimensionShort.'.png',$savedImage);
        }
        $additionalArticleFields = config('openai.additional_article_fields');
        foreach($additionalArticleFields as $key => $articleField){
            $articleData[$key] = $articleField;
        }

        if($siteId){
            $articleData['site_id'] = $siteId;
        }
        $articleId = \DB::table(config('openai.articles_table_name'))->insertGetId($articleData);

        if(config('openai.use_publish') && $siteId){
            \DB::table(config('openai.publish_table_name'))->insert([
                'site_id' => $siteId,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
                'article_id' => $articleId,
                'created_at' => now(),
                'created_by' => config('openai.user_id')
            ]);
        }

        //create tags for article
        $this->createTags($articleId,$text);

        return $heading;


    }

    /**
     * function for storing image
     */
    protected function saveImage($imageUrl){
        $data = [
            'category' => config('openai.image_category_id'),
            'source' => config('openai.image_source'),
            'created_at' => now(),
            'updated_at' => now()
        ];
        $data['creation_user'] = 0;
        $data['creation_date'] = now()->toDateString();

        $mediaEntityId = \DB::table(config('openai.media_table_name'))->insertGetId($data);
        $this->lastInsertedMediaId = $mediaEntityId;
        $mediaEntity = \DB::table(config('openai.media_table_name'))->where('id',$mediaEntityId)->first();

        $existingMediaSource =
        \DB::table(config('openai.media_sources_table_name'))->where('name',$data['source'])->first();

        if(!isset($existingMediaSource) && empty($existingMediaSource) && !$existingMediaSource){
            \DB::table(config('openai.media_sources_table_name'))->insert([
                'name' => $data['source'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $mediaTags = config('openai.image_tags_ids');
        //if tags already exist in db, insert into media tags
        if(!empty($mediaTags)) {
            $mediaEntityId = $mediaEntity->id;
            foreach($mediaTags as $mediaTag){
                \DB::table(config('openai.media_tags_table_name'))->insert([
                    'tag_id' => $mediaTag,
                    'media_id' => $mediaEntityId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }else{
            //if tags table is empty, create tags from config and insert into media tags
            $mediaTagsTitles = config('openai.image_tag_titles');
            $mediaEntityId = $mediaEntity->id;

            if(!empty($mediaTagsTitles)){
                foreach($mediaTagsTitles as $mediaTagTitle){
                //check if tag already exists, if not create new one
                $tagExists = \DB::table(config('openai.tags_table_name'))->where('title',$mediaTagTitle)->first();
                if($tagExists){
                    $newTagId = $tagExists->id;
                }else{
                    $newTagId = \DB::table(config('openai.tags_table_name'))->insertGetId([
                        'title' => $mediaTagTitle,
                        'active' => 1,
                        'created_by' => config('openai.user_id'),
                        'updated_by' => config('openai.user_id'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                \DB::table(config('openai.media_tags_table_name'))->insert([
                    'tag_id' => $newTagId,
                    'media_id' => $mediaEntityId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                }
            }
        }

        $storeImagesAppPath = config('newscms.images_folder_absolute_path');
        $targetFolderName = now()->toDateString();
        $targetFolderAbsolutePath = $this->joinToPath($storeImagesAppPath, $targetFolderName);

        // kreiraj folder sa danasnjim datumom ukoliko vec ne postoji
        if( ! file_exists($targetFolderAbsolutePath) ) {
            try {
                mkdir($targetFolderAbsolutePath, 0755, TRUE);
            }
            catch (\ErrorException $e) {
                \Log::error('Greska: Prilikom kreiranja direktorijuma za sliku. '
                            . 'mkdir je vratio gresku. Putanja: ' . $targetFolderAbsolutePath
                            . ' Originalna greska: ' . $e->getMessage());
                return FALSE;
            }
        }
        $file = \Intervention\Image\Facades\Image::make($imageUrl);
        header('Content-Type: image/png');
        $fullFileName = $mediaEntity->id.'_open_ai_image.png';
        $file->save($this->joinToPath($targetFolderAbsolutePath, $fullFileName));
        $filename =  $this->joinToPath($targetFolderName, $fullFileName);
        \DB::table(config('openai.media_table_name'))->where('id',$mediaEntityId)->update(['filename' => $filename]);

        $filePathForArticle = '/data/images/'.$targetFolderName.'/'.$fullFileName;

        return $filePathForArticle;

    }

    /**
     * Vrati 'string' putanje od delova putanje.
     *
     * param variadic(string) $parts
     *
     * @return string
     */
    private function joinToPath(...$parts)
    {
        $trimmedParts = array_map(function($el) {
            return rtrim($el, '/');
        }, $parts);
        return join('/', $trimmedParts);
    }

    /**
     * picks random array element
     */
    protected function pickRandom(array $array){
        $randomKey = array_rand($array);
        return $array[$randomKey];
    }

    protected function createTags($articleId,$text){
        $tagsPerArticle = config('openai.tags_number');
        $pattern = '/<p>(.*?)<\/p>/s';
        // match all <p> tags and their contents
        preg_match_all($pattern, $text, $matches);
        // concatenate all matched text
        $filteredText = implode('', $matches[0]);
        if(!empty($filteredText)){
            $askClient = \OpenAI::client('chat/completions');
            $tags = $askClient->ask('Za naredni novinski tekst predlozi mi sledeci broj tagova:'.$tagsPerArticle.' pritom postujuci najbolje SEO prakse i analizu kljucnih reci u tekstu. Tagovi koje predlazes smeju biti u duzini od jedne reci ili od dve reci. Preskoci sve predloge koji imaju vise od dve reci. Neka odgovor bude string u kojem ce tagovi biti odvjeni sa |  "' . strip_tags($filteredText) . '"')['content'];
            $tagsArray = explode('|',$tags);
            foreach($tagsArray as $tag){
                $tagTitle = trim($tag);
                //check if tag exists and get id
                $tagExists = $tagId = \DB::table(config('openai.tags_table_name'))->select('id')->where('title',$tagTitle)->first();
                if($tagExists){
                    $tagId = $tagExists->id;
                }else{
                    $tagId = \DB::table(config('openai.tags_table_name'))->insertGetId([
                        'title' => $tagTitle,
                        'active' => 1,
                        'created_by' => config('openai.user_id'),
                        'updated_by' => config('openai.user_id'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                \DB::table(config('openai.article_tags_table_name'))->insert([
                    'tag_id' => $tagId,
                    'article_id' => $articleId
                ]);
            }
        }

    }

}