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
    //tags ids for images
    'image_tags_ids' => [1,2,3],
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
     /**
     * additional fields for article table
     * ['brid_tv_id' => null,
     *  'allow_something' => true
     * ]
     */
    'additional_article_fields' => [],
    //does database uses publish table for articles (if yes, specifi site id)
    'use_publish' => false,
    //does article table has column site_id
    'article_site_id' => false,
    //user id for created_at and updated_at
    'user_id' => 0,
    //maximum number of tokens for dialog
    'dialog_max_tokens' => 1000
];
