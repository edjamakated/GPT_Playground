<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

require_once 'vendor/autoload.php';

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class GPTResponse
{
    private const DEFAULT_API_KEY = 'sk-yourOpenAIkeyHere';

    private $api_key;
    private $api_url = 'https://api.openai.com/v1/chat/completions';
    private $ch;
    private $system_instructions;
    private $assistant_instructions;
    private $user_instructions;
    private $model;

    public function __construct(
        $api_key = null,
        $system_instructions = null,
        $assistant_instructions = null,
        $user_instructions = null,
        $model = null
    ) {
        $this->api_key = $api_key ?? self::DEFAULT_API_KEY;
        $this->system_instructions = $system_instructions ?? "You are a specially designed GPT that writes amazing poems. Take the user input and write an amazing poem that evokes emotion. Include content from the input and write an amazing poem.";
        $this->assistant_instructions = $assistant_instructions ?? "Write a really awesome poem that invokes emotion about the following. Try to make it a really impressive poem that rhymes well. Write a poem based on the following user input: ";
        $this->model = $model ?? 'gpt-3.5-turbo';
        $this->initializeCurl();
    }

    private function initializeCurl()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $this->api_url);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key
        ]);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 171);
    }
    public function saveToDatabase($input_data, $gpt_response)
    {
        $db_file = 'gpt_responses.db';

        if (!file_exists($db_file)) {
            $db = new SQLite3($db_file);

            $create_table_query = "CREATE TABLE responses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                datetime TEXT NOT NULL,
                input_data TEXT NOT NULL,
                gpt_response TEXT NOT NULL
            )";
            $db->exec($create_table_query);
        } else {
            $db = new SQLite3($db_file);
        }

        $datetime = date('Y-m-d H:i:s');
        $insert_query = $db->prepare("INSERT INTO responses (datetime, input_data, gpt_response) VALUES (:datetime, :input_data, :gpt_response)");
        $insert_query->bindValue(':datetime', $datetime, SQLITE3_TEXT);
        $insert_query->bindValue(':input_data', $input_data, SQLITE3_TEXT);
        $insert_query->bindValue(':gpt_response', $gpt_response, SQLITE3_TEXT);
        $insert_query->execute();

        $db->close();
    }
    public function getResponse($user_instructions = '', $system_instructions = '', $assistant_instructions = '')
    {
        if (empty($user_instructions) || empty($assistant_instructions) || empty($system_instructions)) {
            throw new InvalidArgumentException('User input is empty.');
        }
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system_instructions],
                ['role' => 'assistant', 'content' => $assistant_instructions],
                ['role' => 'user', 'content' => $user_instructions]
            ],
            'temperature' => 0.06,
            'max_tokens' => 2999,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];

        $json_data = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error encoding request data: ' . json_last_error_msg());
        }

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $json_data);
        $response = curl_exec($this->ch);

        if ($response === false) {
            throw new Exception('Error executing curl request: ' . curl_error($this->ch), curl_errno($this->ch));
        }
        $http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            throw new Exception('Error retrieving response from GPT. HTTP code: ' . $http_code);
        }
        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Error decoding response data: ' . json_last_error_msg());
        }

        if (!isset($response_data['choices'][0]['message']['content'])) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Error: No response from GPT']);
            exit;
        }

        header('Content-Type: application/json');
        $theResponse = json_encode(['response' => $response_data['choices'][0]['message']['content']]);
        $this->saveToDatabase($user_instructions, $theResponse);
        return $theResponse;
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateJsonInput($input_data)
{
    $jsonSchema = '{
        "type": "object",
        "properties": {
            "user_instructions": {"type": "string"},
            "assistant_instructions": {"type": "string"},
            "system_instructions": {"type": "string"},
            "model": {"type": "string"},
            "temperature": {"type": "number"},
            "max_tokens": {"type": "integer"},
            "top_p": {"type": "number"},
            "frequency_penalty": {"type": "number"},
            "presence_penalty": {"type": "number"}
        },
        "required": [
            "user_instructions",
            "assistant_instructions",
            "system_instructions",
            "model",
            "temperature",
            "max_tokens",
            "top_p",
            "frequency_penalty",
            "presence_penalty"
        ]
    }';

    $validator = new Validator();
    $validator->validate($input_data, json_decode($jsonSchema), Constraint::CHECK_MODE_TYPE_CAST);

    if (!$validator->isValid()) {
        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
        }

        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request. Input data validation failed.', 'details' => $errors]);
        exit;
    }
}

$json_input = file_get_contents('php://input');
$input_data = json_decode($json_input, true);

validateJsonInput($input_data);

$user_instructions = array_map('sanitizeInput', $input_data['user_instructions']);
$assistant_instructions = array_map('sanitizeInput', $input_data['assistant_instructions']);
$system_instructions = array_map('sanitizeInput', $input_data['system_instructions']);
$model = sanitizeInput($input_data['model']);
$temperature = $input_data['temperature'];
$max_tokens = $input_data['max_tokens'];
$top_p = $input_data['top_p'];
$frequency_penalty = $input_data['frequency_penalty'];
$presence_penalty = $input_data['presence_penalty'];

$gpt4 = new GPTResponse(
    null,
    null,
    null,
    null,
    $model,
    $temperature,
    $max_tokens,
    $top_p,
    $frequency_penalty,
    $presence_penalty
);

$count = count($user_instructions);

for ($i = 0; $i < $count; $i++) {
    try {
        $gpt_response = $gpt4->getResponse($user_instructions[$i], $system_instructions[$i], $assistant_instructions[$i]);

        echo "event: gptResponse\n";
        echo "data: " . json_encode([
            'userInstruction' => $user_instructions[$i],
            'assistantResponse' => json_decode($gpt_response, true)['response']
        ]) . "\n\n";
        ob_flush();
        flush();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
    }
}
