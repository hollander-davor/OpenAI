<?php

namespace Hoks\OpenAI\Commands;

use Exception;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class GenerateAITagsForArticles extends Command
{
    protected $intextImages;
    protected $intextImagePace;
    protected $lastInsertedMediaId;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-article-tags';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For given articles AI will create certain number of tags';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //we ask user to either enter sprcific category or to use all categories eg. all articles
        $categoryOrAll = $this->choice('Do you want to create tags for all categories or for specific ones?',['all','select categories'],0);
        if($categoryOrAll == 'all'){
            $articles = \DB::table(config('openai.articles_table_name'))->whereNull('deleted_at')->get();
        }else{
            $categoriesArray = $this->createCategoriesArray();
            $categoryIds = [];
            foreach($categoriesArray as $category){
                $categoryId = \DB::table(config('openai.categories_table_name'))->select('id')->where('name',$category)->first()->id;
                $categoryIds[] = $categoryId;
            }
            if(!empty($categoryIds)){
                if(config('openai.use_publish')){
                    $articles = \DB::table(config('openai.articles_table_name'))
                        ->join(config('openai.publish_table_name'),config('openai.articles_table_name').'.id','=',config('openai.publish_table_name').'.article_id')
                        ->select(config('openai.articles_table_name').'.id',config('openai.articles_table_name').'.text')
                        ->whereNull('deleted_at')
                        ->whereIn(config('openai.publish_table_name').'.category_id',$categoryIds)
                        ->get();
                }else{
                    $articles = \DB::table(config('openai.articles_table_name'))->select('id','text')->whereNull('deleted_at')->whereIn('category_id',$categoryIds)->get();
                }   
            }else{
                $articles = false;
            }
        }

        if($articles){
            $bar = $this->output->createProgressBar(count($articles));
            foreach($articles as $article){
                $articleId = $article->id;
                $text = $article->text;
                $pattern = '/<p>(.*?)<\/p>/s';
                // match all <p> tags and their contents
                preg_match_all($pattern, $text, $matches);
                // concatenate all matched text
                $filteredText = implode('', $matches[0]);
                if(!empty($filteredText)){
                    $askClient = \OpenAI::client('chat/completions');
                    $tags = $askClient->ask('Za naredni novinski tekst predlozi mi 3 tag-a postujuci najbolje SEO prakse i analizu kljucnih reci u tekstu. Tagovi koje predlazes smeju biti u duzini od jedne reci ili od dve reci. Preskoci sve predloge koji imaju vise od dve reci. Neka odgovor bude string u kojem ce tagovi biti odvjeni sa |  "' . strip_tags($filteredText) . '"')['content'];
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
                $bar->advance();
            }

        }
        $bar->finish();
        $this->info("\nAll article tags have been created!");
        
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


}
