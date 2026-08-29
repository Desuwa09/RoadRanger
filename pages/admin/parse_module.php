<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/module_schema.php';

session_start();
header('Content-Type: application/json');
ob_start();
register_shutdown_function(function () {
    $output = ob_get_clean();
    if ($output === '') {
        return;
    }

    if (json_decode($output, true) !== null) {
        echo $output;
        return;
    }

    http_response_code(500);
    echo json_encode([
        'error' => 'The module generation service returned an invalid server response.',
        'raw_output' => substr(strip_tags($output), 0, 1024)
    ]);
});

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    echo json_encode(['error' => 'Access Denied: Unauthorized Administrator Session']);
    exit;
}

function load_local_env_file() {
    $candidate_paths = array(
        dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        __DIR__ . DIRECTORY_SEPARATOR . '../../.env',
        realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',
        getcwd() . DIRECTORY_SEPARATOR . '.env',
        isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . '.env' : null,
    );

    $env_path = null;
    foreach ($candidate_paths as $path) {
        if ($path && is_file($path)) {
            $env_path = $path;
            break;
        }
    }

    if (!$env_path) {
        return false;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) {
            continue;
        }

        if (strpos($trimmed, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);

        if ($value !== '' && preg_match('/^(".*"|\'.*\')$/', $value)) {
            $value = substr($value, 1, -1);
        }

        if ($name !== '') {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;
}

load_local_env_file();

$gemini_key = $GEMINI_API_KEY ?? getenv('GEMINI_API_KEY') ?? ($_ENV['GEMINI_API_KEY'] ?? '');

if (!$gemini_key || trim((string)$gemini_key) === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['error' => 'Gemini API key is not configured. Set the GEMINI_API_KEY environment variable or .env file before generating a module.']);
        exit;
    }
}

function normalize_inline_image_payload($image_data, $mime_type = 'image/png') {
    if (!is_string($image_data)) {
        return null;
    }

    $trimmed = trim($image_data);
    if ($trimmed === '') {
        return null;
    }

    $mime = is_string($mime_type) && $mime_type !== '' ? $mime_type : 'image/png';

    if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/s', $trimmed, $matches)) {
        $mime = $matches[1];
        $trimmed = $matches[2];
    }

    $trimmed = preg_replace('/\s+/', '', $trimmed);
    if ($trimmed === '') {
        return null;
    }

    return array(
        'mime_type' => $mime,
        'data' => $trimmed
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_lto_text = isset($_POST['content']) ? trim($_POST['content']) : '';
    $action = isset($_POST['action']) ? trim($_POST['action']) : 'parse';
    $source_image = null;

    if (isset($_POST['image_data'])) {
        $source_image = normalize_inline_image_payload($_POST['image_data'], isset($_POST['image_mime_type']) ? $_POST['image_mime_type'] : 'image/png');
    }

    if ($raw_lto_text === '') {
        echo json_encode(['error' => 'System Error: Input text context cannot be blank.']);
        exit;
    }

    if ($action === 'translate_to_tagalog') {
        $decoded_module = json_decode($raw_lto_text, true);
        if (!is_array($decoded_module)) {
            echo json_encode(['error' => 'Translation request requires valid JSON module data.']);
            exit;
        }

        $source_tree = null;
        if (isset($decoded_module['en']) && is_array($decoded_module['en'])) {
            $source_tree = $decoded_module['en'];
        } elseif (isset($decoded_module['nodes']) && is_array($decoded_module['nodes'])) {
            $source_tree = $decoded_module;
        } elseif (isset($decoded_module['tl']) && isset($decoded_module['en']) && is_array($decoded_module['en'])) {
            $source_tree = $decoded_module['en'];
        }

        if (!is_array($source_tree) || !isset($source_tree['nodes']) || !is_array($source_tree['nodes'])) {
            echo json_encode(['error' => 'Translation request requires a JSON tree with a top-level nodes structure.']);
            exit;
        }

        $translate_instructions = "You are an educational assistant for the RoadRangers platform. ";
        $translate_instructions .= "Translate the following JSON dialogue tree into Tagalog. ";
        $translate_instructions .= "Do not change node IDs, next_node values, score_impact values, or JSON structure. ";
        $translate_instructions .= "Only translate the values of bot_message and choices.text strings. ";
        $translate_instructions .= "Return valid JSON only, with the same structure provided. " .
            "Do not include markdown, comments, or extra text outside the JSON object.\n";

        $system_part = array('parts' => array(array('text' => $translate_instructions)));
        $content_part = array('parts' => array(array('text' => json_encode($source_tree, JSON_UNESCAPED_UNICODE))));

        $payload_array = array(
            'contents' => array($content_part),
            'systemInstruction' => $system_part,
            'generationConfig' => array(
                'responseMimeType' => 'application/json'
            )
        );

        $api_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $gemini_key;

        $process = curl_init($api_endpoint);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($process, CURLOPT_POST, true);
        curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($payload_array));
        curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($process, CURLOPT_TIMEOUT, 180);
        curl_setopt($process, CURLOPT_CONNECTTIMEOUT, 20);

        $server_output = curl_exec($process);

        if ($server_output === false) {
            $error_msg = curl_error($process);
            curl_close($process);
            echo json_encode(['error' => 'cURL Network Connection Failure: ' . $error_msg]);
            exit;
        }

        $http_status = curl_getinfo($process, CURLINFO_HTTP_CODE);
        curl_close($process);

        $result_data = json_decode($server_output, true);
        $ai_json_payload = null;

        if (is_array($result_data)) {
            if (isset($result_data['candidates'][0]['content']['parts'][0]['text'])) {
                $ai_json_payload = $result_data['candidates'][0]['content']['parts'][0]['text'];
            } elseif (isset($result_data['candidates'][0]['content'][0]['text'])) {
                $ai_json_payload = $result_data['candidates'][0]['content'][0]['text'];
            } elseif (isset($result_data['candidates'][0]['text'])) {
                $ai_json_payload = $result_data['candidates'][0]['text'];
            } elseif (isset($result_data['response']['output'][0]['content'][0]['text'])) {
                $ai_json_payload = $result_data['response']['output'][0]['content'][0]['text'];
            } elseif (isset($result_data['output_text'])) {
                $ai_json_payload = $result_data['output_text'];
            }
        } else {
            $trimmed_output = trim($server_output);
            if ($trimmed_output !== '' && json_decode($trimmed_output, true) !== null) {
                $ai_json_payload = $trimmed_output;
            }
        }

        if ($ai_json_payload !== null) {
            $trimmed_payload = trim($ai_json_payload);
            $translated_tree = json_decode($trimmed_payload, true);
            if ($translated_tree === null) {
                echo json_encode([
                    'error' => 'Gemini returned Tagalog output that is not valid JSON. Please try again.',
                    'debug_log' => substr($trimmed_payload, 0, 512)
                ]);
                exit;
            }

            if (!isset($translated_tree['nodes']) || !is_array($translated_tree['nodes'])) {
                echo json_encode([
                    'error' => 'Translated JSON does not contain a valid nodes structure.',
                    'debug_log' => substr($trimmed_payload, 0, 512)
                ]);
                exit;
            }

            $combined = array(
                'en' => $source_tree,
                'tl' => $translated_tree
            );
            echo json_encode(array('translated_module' => $combined));
            exit;
        }

        $debug_info = array('error' => 'Gemini Engine API Processing Timeout or Bad Data Format Structure.');
        if (is_array($result_data)) {
            $debug_info['debug_log'] = $result_data;
            $debug_info['http_status'] = $http_status;
        } else {
            $debug_info['raw_output'] = substr($server_output, 0, 1024);
            $debug_info['http_status'] = $http_status;
        }

        echo json_encode($debug_info);
        exit;
    }

    $instructions = "You are an educational assistant for the RoadRangers platform. ";
    $instructions .= "Convert the supplied driving rules, road sign material, or scenario details into a readable learning module for citizens. ";
    $instructions .= "Read the entire source text and use all important rules, not just the first section. Include the main points from the full document, including later rules, conditions, exceptions, and examples. ";
    $instructions .= "The output must be valid JSON only, with short plain-language sentences that are easy for everyday users to understand. ";
    $instructions .= "Do not return a branching chatbot tree or raw node structure. Do not include markdown fences or code blocks. ";
    $instructions .= "Return this schema exactly:\n";
    $instructions .= "{\n";
    $instructions .= "  \"title\": \"Short module title\",\n";
    $instructions .= "  \"summary\": \"One or two easy sentences explaining the lesson.\",\n";
    $instructions .= "  \"cover_image\": \"optional valid image URL or data URL\",\n";
    $instructions .= "  \"content\": [\n";
    $instructions .= "    { \"heading\": \"Key Rule\", \"text\": \"Simple sentence for the learner.\", \"image\": \"optional image URL\" },\n";
    $instructions .= "    { \"heading\": \"What to do\", \"text\": \"Simple sentence for the learner.\", \"image\": \"optional image URL\" }\n";
    $instructions .= "  ],\n";
    $instructions .= "  \"quiz\": [\n";
    $instructions .= "    {\n";
    $instructions .= "      \"question\": \"Question for the lesson\",\n";
    $instructions .= "      \"options\": [\n";
    $instructions .= "        { \"text\": \"Correct answer\", \"correct\": true },\n";
    $instructions .= "        { \"text\": \"Wrong answer\", \"correct\": false }\n";
    $instructions .= "      ],\n";
    $instructions .= "      \"explanation\": \"Brief reason for the correct answer.\"\n";
    $instructions .= "    }\n";
    $instructions .= "  ],\n";
    $instructions .= "  \"pass_score\": 60\n";
    $instructions .= "}\n";
    $instructions .= "Rules: keep each lesson paragraph short, use everyday language, include 2 to 4 content sections, include 3 quiz questions unless the lesson is too short, include only one correct answer for each question, and add an image URL only when a road sign or visual is clearly relevant. If no image is relevant, set the image field to an empty string or omit it. IMPORTANT: do not skip key rules from the middle or end of the document; summarize the full source into the lesson without losing major points.";

    $system_part = array("parts" => array(array("text" => $instructions)));
    $content_parts = array(array("text" => "Analyze this LTO source reference text:\n\n" . $raw_lto_text));

    if ($source_image !== null) {
        $content_parts[] = array(
            'inlineData' => array(
                'mimeType' => $source_image['mime_type'],
                'data' => $source_image['data']
            )
        );
    }

    $content_part = array("parts" => $content_parts);
    
    $payload_array = array(
        "contents" => array($content_part),
        "systemInstruction" => $system_part,
        "generationConfig" => array(
            "responseMimeType" => "application/json"
        )
    );

    $api_endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $gemini_key;

    $process = curl_init($api_endpoint);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($process, CURLOPT_POST, true);
    curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($payload_array));
    curl_setopt($process, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($process, CURLOPT_TIMEOUT, 180);
    curl_setopt($process, CURLOPT_CONNECTTIMEOUT, 20);
    
    $server_output = curl_exec($process);
    
    if ($server_output === false) {
        $error_msg = curl_error($process);
        curl_close($process);
        echo json_encode(['error' => 'cURL Network Connection Failure: ' . $error_msg]);
        exit;
    }

    $http_status = curl_getinfo($process, CURLINFO_HTTP_CODE);
    curl_close($process);

    $result_data = json_decode($server_output, true);
    $ai_json_payload = null;

    if (isset($result_data['error'])) {
        $error_message = $result_data['error']['message'] ?? 'Unknown Gemini API error.';
        $error_status = $result_data['error']['status'] ?? $http_status;
        echo json_encode([
            'error' => 'Gemini API Request Failed: ' . $error_message,
            'status' => $error_status,
            'debug_log' => $result_data
        ]);
        exit;
    }

    if (is_array($result_data)) {
        if (isset($result_data['candidates'][0]['content']['parts'][0]['text'])) {
            $ai_json_payload = $result_data['candidates'][0]['content']['parts'][0]['text'];
        } elseif (isset($result_data['candidates'][0]['content'][0]['text'])) {
            $ai_json_payload = $result_data['candidates'][0]['content'][0]['text'];
        } elseif (isset($result_data['candidates'][0]['text'])) {
            $ai_json_payload = $result_data['candidates'][0]['text'];
        } elseif (isset($result_data['response']['output'][0]['content'][0]['text'])) {
            $ai_json_payload = $result_data['response']['output'][0]['content'][0]['text'];
        } elseif (isset($result_data['output_text'])) {
            $ai_json_payload = $result_data['output_text'];
        }
    } else {
        $trimmed_output = trim($server_output);
        if ($trimmed_output !== '' && json_decode($trimmed_output, true) !== null) {
            echo $trimmed_output;
            exit;
        }
    }

    if ($ai_json_payload !== null) {
        $trimmed_payload = trim($ai_json_payload);
        $decoded_payload = json_decode($trimmed_payload, true);
        if ($decoded_payload === null) {
            echo json_encode([
                'error' => 'Gemini returned text that is not valid JSON. Please try again or copy the output manually.',
                'debug_log' => substr($trimmed_payload, 0, 512)
            ]);
            exit;
        }

        if (class_exists('RoadRanger\\ModuleSchema')) {
            $normalized_payload = \RoadRanger\ModuleSchema::normalizeGeneratedModule(is_array($decoded_payload) ? $decoded_payload : [], 'Road Safety Lesson');
            echo json_encode($normalized_payload);
            exit;
        }

        echo $trimmed_payload;
        exit;
    }

    $debug_info = ['error' => 'Gemini Engine API Processing Timeout or Bad Data Format Structure.'];
    if (is_array($result_data)) {
        $debug_info['debug_log'] = $result_data;
        $debug_info['http_status'] = $http_status;
    } else {
        $debug_info['raw_output'] = substr($server_output, 0, 1024);
        $debug_info['http_status'] = $http_status;
    }

    echo json_encode($debug_info);
    exit;
}