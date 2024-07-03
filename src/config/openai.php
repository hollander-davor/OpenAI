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
];
