<?php

namespace Hoks\OpenAI;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;


class OpenAI{

    //request headers
    protected $headers = [];
    //uri
    protected $uri;
    //openai response
    protected $response;
    //model type
    protected $model;
    //messages key, for chat/completions it is messages, for responses endpoint it is input
    protected $messagesKey;
    //max tokens key, for chat/completions it is max_tokens, for responses endpoint it is max_output_tokens
    protected $maxTokensKey;
    //dialog array,keeps conversation in array form
    protected $dialog = [];
    /**
     * @var Client
     */
    protected $client;

    /**
     * Set headers array
     */
    protected function setHeaders($headers){
        $this->headers = $headers;
    }

    protected function getHeaders(){
        return $this->headers;
    }

    /**
     * set response
     */
    protected function setResponse($response){
        $this->response = $response;
    }

    protected function getResponse(){
        return $this->response;
    }

    /**
     * set client
     */
    protected function setClient($client){
        $this->client = $client;
    }

    protected function getClient(){
        return $this->client;
    }

    /**
     * set model
     */
    protected function setModel($model){
        $this->model = $model;
    }

    protected function getModel(){
        return $this->model;
    }

     /**
     * set uri
     */
    protected function setUri($uri){
        $this->uri = $uri;
        if(strpos($uri, 'responses') !== false){
            $this->setMessagesKey('input');
            $this->setMaxTokensKey('max_output_tokens');
        }else{
            $this->setMessagesKey('messages');
            $this->setMaxTokensKey('max_tokens');
        }
    }
    /**
     * set messages key (for responses endpoint it is input, for chat/completions it is messages)
     */
    protected function setMessagesKey($messagesKey){
        $this->messagesKey = $messagesKey;
    }
    /**
     * set max tokens key (for responses endpoint it is max_output_tokens, for chat/completions it is max_tokens)
     */
    protected function setMaxTokensKey($maxTokensKey){
        $this->maxTokensKey = $maxTokensKey;
    }

    protected function getMessagesKey(){
        return $this->messagesKey;
    }

    protected function getMaxTokensKey(){
        return $this->maxTokensKey;
    }

    protected function getUri(){
        return $this->uri;
    }

    /**
     * sets/resets dialog array
     */
    protected function setDialog($reset,$message){
        if($reset){
            $this->dialog = [];
        }
        $this->dialog[] = $message;
    }
    /**
     * returns array that is sent to openai as messages parameter
     * example
     * [
     *  [1st question],
     *  [1st answer],
     *  [2nd question],
     *  [2nd answer]
     * ]
     */
    protected function getDialog(){
        return $this->dialog;
    }


    /**
     * Create client
     */
    public function client(string $uri,int $timeout = 30,string $model = 'gpt-4-turbo',$apiKey = ''){
        if(!config('openai.provide-api-key')){
            $apiKey = config('openai.openai-api-key');
        }
        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
        $headers = [
            "Authorization" => "Bearer ".$apiKey,
            "Content-Type" => "application/json",
            "timeout" => $timeout,
        ];
        $this->setUri($uri);
        $this->setModel($model);
        $this->setHeaders($headers);
        $this->client = $client;

        return $this;
    }

    /**
     * method that returns openAI response for given question
     * it is possible to define  maximum number of tokens
     */
    public function ask(string $question,int $maxTokens = 400){
        $uri = $this->getUri();
         // If the URI is for the responses endpoint, map the dialog data to the new format
        if (strpos($uri, 'responses') !== false) {
            $question = $this->mapChatToResponses($question);
        }else{
            $question = [
                [
                'role' => 'user',
                'content' => $question
                ]
            ];
        }


        $body = [
            'model' => $this->getModel(),
            $this->getMessagesKey() => $question,
            $this->getMaxTokensKey() => $maxTokens
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getAnswer();
    }

    /**
     * method that keeps dialog with openai
     */
    public function dialog(string $question, int $maxTokens = 400,bool $reset = false){
        $message = [
            'role' => 'user',
            'content' => $question
        ];
        $this->setDialog($reset,$message);

        $body = [
            'model' => $this->getModel(),
            'messages' => $this->getDialog(),
            'max_tokens' => $maxTokens
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);

        $this->setResponse($response);
        $this->setDialog($reset,$this->getAnswer());

        return $this;
    }

    /**
     * return an answer from openAI
     */
    protected function getAnswer(){
        $uri = $this->getUri();
        if (strpos($uri, 'responses') !== false) {
            
            $raw = json_decode($this->getResponse()->getBody()->getContents(), true);
            $response = end($raw['output']);

            $message = [
                'content' => '',
            ];
            if($response['type'] == 'message'){
                $message['content'] = $response['content'][0]['text'];
            }elseif($response['type'] == 'function_call'){
                $response['arguments'] = json_decode($response['arguments'] ?? '{}', true);
                $message['function_call'] = $response;
            }

            return (array) $message;
        }else{
            return (array) json_decode($this->getResponse()->getBody()->getContents())->choices[0]->message;
        }
    }
    /**
     * return an image from openAI in form of url (url is active for 60min)
     */
    protected function getImage(){
        return (array) json_decode($this->getResponse()->getBody()->getContents())->data[0]->url;
    }

    /**
     * get all AI answers array
     */
    public function getDialogAnswers(){
        $answers = [];
        foreach($this->getDialog() as $dialogItem){
            if($dialogItem['role'] == 'assistant'){
                $answers[] = $dialogItem['content'];
            }
        }
        return $answers;
    }

    /**
     * generates image
     */
    public function generateImage($prompt,$imagesNumber = 1,$size = '1024x1024',$responseFormat = 'url'){
        $body = [
            'model' => $this->getModel(),
            'prompt' => $prompt,
            'n' => $imagesNumber,
            'size' => $size,
            'response_format' => $responseFormat
        ];

        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getImage();
    }

    /**
     * method that comunicates with batch api
     * it expects array of prompts 
     * to target batch api, when instancing client, uri is batches/{endpoint}
     */
    public function batch(array $batchArray,$maxTokens = 400){
        $body = [];
        foreach($batchArray as $key => $prompt){
            $body[$key]['model'] = $this->getModel();
            $body[$key]['prompt'] = $prompt;
            $body[$key]['max_tokens'] = $maxTokens;
        }
       
        $options = ['headers' => $this->getHeaders(),'json' => $body];
        $response = $this->getClient()->request('POST',$this->getUri(),$options);
        $this->setResponse($response);

        return $this->getResponse();
    }

    //method creates thread and returns thread id
    public function createThread(){
        //get client
        $client = $this->getClient();
        //add 'OpenAI-Beta'  => 'assistants=v2', to headers
        $this->headers['OpenAI-Beta'] = 'assistants=v2';
        $this->setHeaders($this->headers);

        //form request
        $options = ['headers' => $this->getHeaders(),'json' => []];
        $response = $client->request('POST',$this->getUri(),$options);
        if (!$response->getStatusCode() === 200) {
            Log::error('Error creating thread: '.$response->getBody());
            return false;
        }
        $threadId = json_decode($response->getBody(),true)['id'];
        return $threadId;
    }

    //send data to thread
    public function updateThread($threadId,$text, array $fileIds = []){
        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
        $this->setUri('threads/'.$threadId.'/messages');

        $payload = [
            'role' => 'user',
            'content' => $text
        ];

        // if there are files to attach, map each file ID into the structure the API expects
        if (! empty($fileIds)) {
            $payload['attachments'] = array_map(function (string $fileId) {
                return [
                    'file_id' => $fileId,
                    'tools'   => [
                        ['type' => 'file_search']
                    ],
                ];
            }, $fileIds);
        }

        $options = [
            'headers' => $this->getHeaders(),
            'json' => $payload
        ];
        $response = $client->request('POST',$this->getUri(),$options);
       
         // Provera uspešnosti odgovora
        if (!$response->getStatusCode() === 200) {
            Log::error('Error updating thread(sending thread message): '.$response->getBody());
            return false;
        }
        return true;
    }

    public function runThread($threadId,$assistantId){
        $client = new Client(['base_uri' => 'https://api.openai.com/v1/']);
        $this->setUri('threads/'.$threadId.'/runs');
        $options = ['headers' => $this->getHeaders(),'json' => [
            'assistant_id' => $assistantId,
        ]];
        $response = $client->request('POST',$this->getUri(),$options);
         // Provera uspešnosti odgovora
        if (!$response->getStatusCode() === 200) {
            Log::error('Error running thread: '.$response->getBody());
            return false;
        }
        $runId = json_decode($response->getBody(),true)['id'];

        return $runId;
    }   

    public function getRunStatus($threadId, $runId)
    {
        $client = $this->getClient();
        $this->setUri('threads/'.$threadId.'/runs/'.$runId);
        $this->headers['OpenAI-Beta'] = 'assistants=v2';
        $this->setHeaders($this->headers);
        $options = ['headers' => $this->getHeaders()];

        $response = $client->request('GET', $this->getUri(), $options);
        $status =  json_decode($response->getBody(),true)['status'];
        if ($status !== 'completed') {
            Log::error('Error getting run status: '.$response->getBody());
            return false;
        }
        $this->setUri('threads/'.$threadId.'/messages');
        $options = ['headers' => $this->getHeaders()];
        $response = $client->request('GET', $this->getUri(), $options);
        if(!$response->getStatusCode() === 200) {
            Log::error('Error getting thread messages: '.$response->getBody());
            return false;
        }
        $messages =  json_decode($response->getBody(),true)['data'];
        $latest = collect($messages)
            ->where('role', 'assistant')
            ->first();
        $content = $latest['content'][0]['text']['value'] ?? 'Nema odgovora.';
        return $content;
    } 

     /**
     * This method is used to send message with all previous messages 
     * which are provided to method ($dialogData)
     * Also you can provide additional data in form of array
     * that will be added to the request, such as
     * temperature, top_p, frequency_penalty, presence_penalty...
     *  
     */
    public function sendDialog($dialogData,$additionalData = [],$maxTokens = 4000){
        $uri = $this->getUri();
         // If the URI is for the responses endpoint, map the dialog data to the new format
        if (strpos($uri, 'responses') !== false) {
            $dialogData = $this->mapChatToResponses($dialogData);
        }
        $body = [
            'model' => $this->getModel(),
            $this->getMessagesKey() => $dialogData,
            $this->getMaxTokensKey() => $maxTokens,
        ];
        if(!empty($additionalData)){
            foreach($additionalData as $key => $value){
                $body[$key] = $value;
            }
        }

        $options = ['headers' => $this->getHeaders(), 'json' => $body];
        $response = $this->getClient()->request('POST', $this->getUri(), $options);

        $this->setResponse($response);

        return $this->getAnswer();
    }

    /**
     * Similar to send dialog, only it is used for async communication
     * it return id of the queued task, and you can use that id to check task status and get result when it is ready
     */
    public function queueTask($dialogData,$additionalData = [],$maxTokens = 4000){
        $uri = $this->getUri();
         // If the URI is for the responses endpoint, map the dialog data to the new format
        if (strpos($uri, 'responses') !== false) {
            $dialogData = $this->mapChatToResponses($dialogData);
        }
        $body = [
            'model' => $this->getModel(),
            'background' => true,
            $this->getMessagesKey() => $dialogData,
            $this->getMaxTokensKey() => $maxTokens,
        ];
        if(!empty($additionalData)){
            foreach($additionalData as $key => $value){
                $body[$key] = $value;
            }
        }

        $options = ['headers' => $this->getHeaders(), 'json' => $body];
        $response = $this->getClient()->request('POST', $this->getUri(), $options);

        $this->setResponse($response);
        $responseId = $this->getResponseId($response);

        return $responseId;
    }

    /**
     * method that gets response id from openAI response
     */
    public function getResponseId($response): ?string
    {
        $data = is_string($response)
            ? json_decode($response, true)
            : json_decode($response->getBody()->getContents(), true);

        return $data['id'] ?? null;
    }

    /**
     * method that checks status of the queued task by response id. 
     * It returns status as string, or null if there is an error.
     */
    public function getQueuedTaskStatus(string $responseId): ?string
    {
        $this->setUri("responses/{$responseId}");

        $response = $this->getClient()->request('GET', $this->getUri(), [
            'headers' => $this->getHeaders(),
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        return $data['status'] ?? null;
    }

    /**
     * method that gets answer of the queued task by response id.
     */
    public function getQueuedTaskAnswer(string $responseId): array
    {
        $this->setUri("responses/{$responseId}");

        $response = $this->getClient()->request('GET', $this->getUri(), [
            'headers' => $this->getHeaders(),
        ]);

        $this->setResponse($response);

        $data = json_decode($response->getBody()->getContents(), true);

        if (($data['status'] ?? null) !== 'completed') {
            return [
                'status' => $data['status'] ?? 'unknown',
                'content' => null
            ];
        }

        $output = end($data['output']);

        $message = [
            'content' => '',
        ];

        if (($output['type'] ?? null) === 'message') {
            $message['content'] = $output['content'][0]['text'] ?? '';
        }

        return $message;
    }

    /**
     * Uploads a file to OpenAI for a specific purpose.
     *
     * $filePath The path to the file to be uploaded.
     * $purpose The purpose of the file :
     *  - assistants: Used in the Assistants API
     *  - batch: Used in the Batch API
     *  - fine-tune: Used for fine-tuning
     *  - vision: Images used for vision fine-tuning 
     *  - user_data: Flexible file type for any purpose
     *  - evals: Used for eval data sets
    
     */
    public function uploadFile($filePath, $purpose){
       
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at: {$filePath}");
        }
        $client = $this->getClient(); // from your existing class
        $headers = $this->getHeaders();
        unset($headers['Content-Type']); // Remove Content-Type header for multipart requests

        $options = [
            'headers' => $headers,
            'multipart' => [
                [
                    'name'     => 'purpose',
                    'contents' => $purpose,
                ],
                [
                    'name'     => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => basename($filePath),
                ],
            ],
        ];
        $response = $client->request('POST', $this->getUri(), $options);
        $arrayResponse =  json_decode($response->getBody(), true);

        $fileId = $arrayResponse['id'];

        return $fileId;
    }

    // Deletes a file from OpenAI by its file ID.
    public function deleteFile($fileId){
        $client  = $this->getClient();
        // client uri for delete should be set to 'files'
        $uri     = $this->getUri().'/'.$fileId;

        $headers = $this->getHeaders();  
        // Remove Content-Type header for DELETE requests
        unset($headers['Content-Type']);
        try {
            $response = $client->request('DELETE', $uri, [
                'headers'     => $headers,
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('Error deleting file, status '.$response->getStatusCode().': '.$response->getBody());
                return false;
            }

            $data = json_decode((string)$response->getBody(), true);
            return $data['deleted'] ?? false;

        } catch (\Exception $e) {
            Log::error('Exception deleting file: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Method that maps old chat format to new Responses API format.
     * Input is array of messages in old format (or message for ask()), output is array of messages in new format.
     * Example:
     *   [['role' => 'user', 'content' => 'There was not that in there.'],
     *    ['role' => 'assistant', 'content' => 'Yes, there was not.']]
     * Output:
     *   [['role'=>'user','content'=>[['type'=>'text','text'=>'There was not that in there.]]],
     *    ['role'=>'assistant','content'=>[['type'=>'text','text'=>'Yes, there was not.']]]]
     */
    protected function mapChatToResponses($dialogData): array
    {
        //first check if input is array
        if (!is_array($dialogData)) {
            // if it's not an array, assume it's a string and wrap it in the expected format
            return [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $dialogData],
                    ],
                ],
            ];
        }
        
        return array_map(function ($m) {
            // if the format is already in new format, return as is
            if (isset($m['content']) && is_array($m['content']) && isset($m['content'][0]['type'])) {
                return $m;
            }
            // map role to type: assistant -> output_text, user -> input_text
            $role = $m['role'];
            if($role == 'assistant'){
                $type = 'output_text';
            }else{
                $type = 'input_text';
            }
            $text = is_string($m['content'] ?? null) ? $m['content'] : '';
            return [
                'role' => $role,
                'content' => [
                    ['type' => $type, 'text' => $text],
                ],
            ];
        }, $dialogData);
    }

    /**
     * Method that creates vector store and returns its id. 
     * You can provide name for vector store, if not provided, it will be named temp-store-{timestamp}
     */
    public function createVectorStore($name = null)
    {
        $this->setUri('vector_stores');

        $response = $this->getClient()->request('POST', $this->getUri(), [
            'headers' => $this->getHeaders(),
            'json' => [
                'name' => $name ?? 'temp-store-' . time()
            ]
        ]);

        return json_decode($response->getBody(), true)['id'];
    }

    /**
     * Method that attaches file to vector store.
     * It expects vector store id and file id as parameters. 
     * File should be already uploaded and processed by OpenAI.
     */
    public function attachFileToVectorStore($vectorStoreId, $fileId)
    {
        $this->setUri("vector_stores/{$vectorStoreId}/files");

        $response = $this->getClient()->request('POST', $this->getUri(), [
            'headers' => $this->getHeaders(),
            'json' => [
                'file_id' => $fileId
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        if (empty($data['id'])) {
            throw new \Exception('Vector store file attach failed');
        }

        return $this->waitUntilVectorStoreFileReady($vectorStoreId, $data['id']);
    }

    /**
     * Method that waits until vector store file is ready.
     *  It checks file status every 1s and returns file data when status is completed.
     */
    public function waitUntilVectorStoreFileReady($vectorStoreId, $vectorStoreFileId, $timeout = 120)
    {
        $start = time();

        do {
            $this->setUri("vector_stores/{$vectorStoreId}/files/{$vectorStoreFileId}");

            $response = $this->getClient()->request('GET', $this->getUri(), [
                'headers' => $this->getHeaders(),
            ]);

            $data = json_decode($response->getBody(), true);
            Log::info('evo testinga statusa vector  store '.$vectorStoreId.' ;file sa id '.$vectorStoreFileId,[
                'status' => $data['status'] ?? null,
                'response' => $data
            ]);
            $status = $data['status'] ?? null;

            if ($status === 'completed') {
                return $data;
            }

            if ($status === 'failed' || $status === 'cancelled') {
                throw new \Exception('Vector store file failed: ' . json_encode($data));
            }

            usleep(1000000);

        } while ((time() - $start) < $timeout);

        throw new \Exception('Timeout waiting for vector store file');
    }

    /**
     * Method that attaches multiple files to vector store.
     * It expects vector store id and array of file ids as parameters.
     */
    public function attachFilesToVectorStore($vectorStoreId, array $fileIds)
    {
        foreach ($fileIds as $fileId) {
            $this->attachFileToVectorStore($vectorStoreId, $fileId);
        }
    }

    /**
     * Method that deletes vector store. It expects vector store id as parameter.
     */
    public function deleteVectorStore($vectorStoreId)
    {
        $this->setUri("vector_stores/{$vectorStoreId}");

        $headers = $this->getHeaders();
        unset($headers['Content-Type']);

        $response = $this->getClient()->request('DELETE', $this->getUri(), [
            'headers' => $headers
        ]);

        return json_decode($response->getBody(), true);
    }


}
