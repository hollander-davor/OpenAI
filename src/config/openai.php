<?php

return [
    //api key for openai
    'openai-api-key' => '',
    //article image dimensions
    'image_dimensions' => [
        'image_orig',
        'image_t',
        'image_f',
        'image_ig',
        'image_s',
        'image_iff',
        'image_iffk',
        'image_kf',
        'image_m',
    ],
    //tags ids for images (if tags allready exist, if not, leave empty)
    'image_tags_ids' => [1,2,3],
    //tags names, if tags table are empty, enter three tag titles to be created
    'image_tag_titles' => ['Image','Photograph','Illustration'],
    //string that would be saved as source for image
    'image_source' => 'OpenAI',
    //category id for image
    'image_category_id' => 1,
    //table name for articles
    'articles_table_name' => 'articles',
    //table name for categories
    'categories_table_name' => 'categories',
    //table name for media
    'media_table_name' => 'media',
    //table name for media sources
    'media_sources_table_name' => 'media_sources',
    //table name for media tags
    'media_tags_table_name' => 'media_tags',
    //table name for publish
    'publish_table_name' => 'publish',
    //table name for tags
    'tags_table_name' => 'tags',
    //table name for article tags
    'article_tags_table_name' => 'article_tag',
     /** 
     * additional fields for article table
     * ['brid_tv_id' => null,
     *  'allow_something' => true
     * ]
     */
    'additional_article_fields' => [],
    //does database uses publish table for articles (if yes, specify site id 'article_site_id' only for GenerateAINewsPeriodicaly)
    'use_publish' => false,
    //does article table has column site_id, if uses, pass value (value used only for GenerateAINewsPeriodicaly)
    'article_site_id' => false,
    //user id for created_at and updated_at
    'user_id' => 0,
    //maximum number of tokens for dialog
    'dialog_max_tokens' => 1000,
    //number of articles GenerateAINewsPeriodicaly
    'articles_number' => 5,
    //insert intext images into GenerateAINewsPeriodicaly articles(enter number of images),to skip pass false
    'insert_intext_images' => false,
    //default number of tags for GenerateAINewsPeriodicaly
    'tags_number' => 3
];
