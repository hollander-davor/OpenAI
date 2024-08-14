<?php

namespace Hoks\OpenAI\Commands;

use Exception;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GenerateAINews extends Command
{
    protected $intextImages;
    protected $intextImagePace;
    protected $lastInsertedMediaId;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-news';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For given categories AI will create certain number of news';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //we ask for site id if eaither publish or article table needs siteId
        if(config('openai.use_publish') || config('openai.article_site_id')){
            $siteId = $this->ask('Enter site id number');
        }else{
            $siteId = false;
        }
        $categoriesArray = $this->createCategoriesArray();
        $articlesPerCategory = $this->ask('How many articles to create per category?');
        $categoriesNumber = count($categoriesArray);
        $intextImages = $this->choice('Do You want to add intext images to articles?',['yes','no'],1);
        $this->intextImages = $intextImages;
        if($intextImages == 'yes'){
            $intextImagePace = $this->ask('How many articles should have intext image INTEGER ("1" for every,"2" for every other,"3" for every third,etc.)?');
            $this->intextImagePace = $intextImagePace;
        }


        $this->info("\nCreating articles!");
        $bar = $this->output->createProgressBar($categoriesNumber * $articlesPerCategory);

        foreach($categoriesArray as $key => $category){
            //create category and subcategory
            $categoryId = $this->createCategory($category,$key);
            $subcategoryId = $this->createCategory('Vesti',$key,$categoryId);
            $articleTitlesForCategory = [];
            for($i = 0; $i < $articlesPerCategory; $i++){
                $newArticleTitle = $this->createArticle($categoryId,$category,$subcategoryId,$articleTitlesForCategory,$i,$siteId);
                $articleTitlesForCategory[] = $newArticleTitle;
                $bar->advance();
            }
        }
        $bar->finish();
        $this->info("\nAll articles have been created!");
    }

    /**
     * function that creates article
     */
    protected function createArticle($categoryId,$categoryName, $subcategoryId,$articleTitlesForCategory,$increment,$siteId = false){
        $client = \OpenAI::client('chat/completions');
        if(config('openai.country_news')){

            $questionsArray = [
                'Ti si novinar u novinskoj agenciji u drzavi:'.config('openai.country').'. Napisi naslov za tekst na temu '.$categoryName.' u skladu sa aktuelnim desavanjima u drzavi i svetu. Naslov napisi uzimajuci u obzir najbolje prakse sa stanovista SEO. Neka jezik bude '.config('openai.language').' jezik.',
                'Za ovaj naslov, napisi kraci uvodni tekst uzimajuci u obzir najbolje prakse sa stanovista SEO, a da tekst ne bude veci od 300 karaktera i treba da je na jeziku:'.config('openai.language').' jezik',
                'Za prethodni naslov i uvodni tekst, napisi novinarski tekst vodeci racuna o pravopisu i SEO. Neka tekst bude na jeziku: '.config('openai.language').' jezik.Tekst vrati u p html tagovima.',
            ];
        }else{
            $questionsArray = [
                'Ti si novinar u novinskoj agenciji. Napisi naslov za tekst na temu '.$categoryName.' u skladu sa stvarnim aktuelnim desavanjima. Naslov napisi uzimajuci u obzir najbolje prakse sa stanovista SEO. Neka jezik bude '.config('openai.language').' jezik.',
                'Za ovaj naslov, napisi kraci uvodni tekst uzimajuci u obzir najbolje prakse sa stanovista SEO, a da tekst ne bude veci od 300 karaktera i treba da je na jeziku:'.config('openai.language').' jezik',
                'Za prethodni naslov i uvodni tekst, napisi novinarski tekst vodeci racuna o pravopisu i SEO. Neka tekst bude na jeziku: '.config('openai.language').' jezik.Tekst vrati u p html tagovima.',
            ];
        }


        if(!empty($articleTitlesForCategory) && count($articleTitlesForCategory) > 0){
            $articleTitlesForCategoryString = implode(', ',$articleTitlesForCategory);
            $questionsArray[0] = $questionsArray[0].'Neka naslov ima temu razlicitu od sledecih naslova; '.$articleTitlesForCategoryString;
        }

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
        if($this->intextImages == 'yes'){
            if(($increment+1) % $this->intextImagePace == 0){
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

        if(config('openai.article_site_id') && $siteId){
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

        return $heading;


    }

    /**
     * function that creates category/subcategory and returns id
     */
    protected function createCategory($categoryName, $priority, $parentId=0){
        $existCategory = \DB::table(config('openai.categories_table_name'))->where('name',$categoryName)->where('parent_id',$parentId)->first();
        if(isset($existCategory) && !empty($existCategory)){
            $categoryId = $existCategory->id;
        }else{
            $categoryId = \DB::table(config('openai.categories_table_name'))->insertGetId([
                'name' => $categoryName,
                'color' => '#305aa2',
                'seo_title' => $categoryName,
                'seo_description' => $categoryName,
                'seo_keywords' => $categoryName,
                'site_id' => 0,
                'parent_id' => $parentId,
                'priority' => $priority,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => config('openai.user_id'),
                'updated_by' => config('openai.user_id')
            ]);
        }

        return $categoryId;
    }

    /**
     * Function used to take user input of categories to be created
     */
    protected function createCategoriesArray($categoriesArray = []){
        $newCategory = $this->ask('Enter category name (type `end` to end)');
        if($newCategory != 'end'){
            $categoriesArray[] = $newCategory;

            $categoriesArray = $this->createCategoriesArray($categoriesArray);
        }
        return $categoriesArray;

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


}
