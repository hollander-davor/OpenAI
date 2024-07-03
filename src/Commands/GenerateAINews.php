<?php

namespace Hoks\OpenAI\Commands;

use Exception;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GenerateAINews extends Command
{
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
        $categoriesArray = $this->createCategoriesArray();
        $articlesPerCategory = $this->ask('How many articles to create per category?');
        $categoriesNumber = count($categoriesArray);

        $this->info("\nCreating articles!");
        $bar = $this->output->createProgressBar($categoriesNumber * $articlesPerCategory);

        foreach($categoriesArray as $key => $category){
            //create category and subcategory
            $categoryId = $this->createCategory($category,$key);
            $subcategoryId = $this->createCategory('Vesti',$key,$categoryId);
            $articleTitlesForCategory = [];
            for($i = 0; $i < $articlesPerCategory; $i++){
                $newArticleTitle = $this->createArticle($categoryId,$category,$subcategoryId,$articleTitlesForCategory);
                $articleTitlesForCategory[] = $newArticleTitle;
                $bar->advance();
            }
        }
        $bar->finish();
        $this->info("\nAll articles have been created!");

        // $client = \OpenAI::client('chat/completions')->dialog('What is fastest land animal?')->dialog('Is Usain Bolt faster?')->getDialogAnswers();


    }

    /**
     * function that creates article
     */
    protected function createArticle($categoryId,$categoryName, $subcategoryId,$articleTitlesForCategory){
        $client = \OpenAI::client('chat/completions');
        $questionsArray = [
            'Ti si novinar u novinskoj agenciji u Srbiji. Napisi naslov za tekst na temu '.$categoryName.' u skladu sa aktuelnim desavanjima u svetu. Naslov napisi uzimajuci u obzir najbolje prakse sa stanovista SEO.',
            'Za ovaj naslov, napisi kraci uvodni tekst uzimajuci u obzir najbolje prakse sa stanovista SEO',
            'Za prethodni naslov i uvodni tekst, napisi novinarski tekst vodeci racuna o pravopisu.Tekst vrati u p html tagovima.',
        ];

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
            $client = $client->dialog($question,1000,$reset);
        }
        $AIAnswers = $client->getDialogAnswers();

        $heading = str_replace('"','',$AIAnswers[0]);
        $lead = $AIAnswers[1];
        $text = $AIAnswers[2];
        $text = str_replace('```','',$text);
        $text = str_replace('html','',$text);


        $askClient = \OpenAI::client('chat/completions');
        $imagePrompt = $askClient->ask('Write best prompt for creating realistic image that will be used as newspaper article photo. Photo should not containt faces or letters or numbers or anyting that would tell the viewer that image is created with AI. Photo should be created for the following text in Serbian "' . strip_tags($text) . '"')['content'];
        $imageClient = \OpenAI::client('images/generations',60,'dall-e-3');
        $imageUrl = $imageClient->generateImage($imagePrompt)[0];
        $savedImage = $this->saveImage($imageUrl);


        $articleData = [
            'preheading' => null,
            'heading' => $heading,
            'tv_heading' => $heading,
            'lead' => $lead,
            'text' => $text,
            'author_id' => 0,
            'source' => 'Cubes',
            'category_id'  => $categoryId,
            'subcategory_id' => $subcategoryId,
            'time_created' => now(),
            'time_created_date' => now()->format('YYYY-mm-dd'),
            'time_changed' => now(),
            'created_by' => 0,
            'time_created_real' => now(),
            'updated_by' => 0,
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
            'brid_tv_id' => null,
            'latest' => 0,
            'image_orig' => str_replace('.png','_orig.png',$savedImage),
            'image_t' => str_replace('.png','_t.png',$savedImage),
            'image_f' => str_replace('.png','_f.png',$savedImage),
            'image_ig' => str_replace('.png','_ig.png',$savedImage),
            'image_s' => str_replace('.png','_s.png',$savedImage),
            'image_iff' => str_replace('.png','_iff.png',$savedImage),
            'image_iffk' => str_replace('.png','_iffk.png',$savedImage),
            'image_kf' => str_replace('.png','_kf.png',$savedImage),
            'image_m' => str_replace('.png','_m.png',$savedImage),
        ];
        $imageDimensionsArray = config('openai.image_dimensions');
        foreach($imageDimensionsArray as $imageDimension){
            $imageDimensionShort = str_replace('image','',$imageDimension);
            $articleData[$imageDimension] = str_replace('.png',$imageDimensionShort.'png',$savedImage);
        }
        $article = \DB::table(config('openai.articles_table_name'))->insert($articleData);


    }

    /**
     * function that creates category/subcategory and returns id
     */
    protected function createCategory($categoryName, $priority, $parentId=0){
        $existCategory = \DB::table(config('openai.categories_table_name'))->where('name',$categoryName)->where('parent_id',$parentId)->first();
        if(isset($existCategory) && !empty($existCategory)){
            $categoryId = $existCategory->id;
        }else{
            $categoryId = \DB::table(config('openai.categories_table_name'))::insertGetId([
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
                'created_by' => 0,
                'updated_by' => 0
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
            'tags' => config('openai.image_tags_ids')
        ];
        $data['creation_user'] = 0;
        $data['creation_date'] = now()->toDateString();

        $mediaEntity = \DB::table(config('openai.media_table_name'))::create($data);

        \DB::table(config('openai.media_sources_table_name'))::firstOrCreate([
            'name' => $data['source'],
        ]);

        if(!empty($data['tags'])) {
            $mediaEntity->tags()->attach($data['tags']);
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
        $mediaEntity->update(['filename' => $filename]);

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
