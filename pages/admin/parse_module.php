<?php





session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || (int)$_SESSION['is_admin'] !== 1) {
    echo json_encode(['error' => 'Access Denied: Unauthorized Administrator Session']);
    exit;
}

$gemini_key = "AIzaSyAZFvjyF_YEGKJcKO7CsjxOsGX61Xgzg7U"; 

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
        curl_setopt($process, CURLOPT_TIMEOUT, 60);
        curl_setopt($process, CURLOPT_CONNECTTIMEOUT, 10);

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
    $instructions .= "Convert the following raw traffic/driving rules into an interactive scenario-based branching chatbot dialogue tree. ";
    $instructions .= "If the supplied source text or image contains a road sign, hazard, instruction, or scenario visual, include the relevant visual as an optional `image` field on the matching node using a valid data URL or public URL string. ";
    $instructions .= "Strict Requirement: Your response must be purely raw JSON conforming EXACTLY to this schema outline, without markdown formatting blocks:\n";
    $instructions .= "{\n";
    $instructions .= "  \"nodes\": {\n";
    $instructions .= "    \"start\": {\n";
    $instructions .= "      \"bot_message\": \"Scenario setup or lesson question text...\",\n";
    $instructions .= "      \"image\": \"data:image/png;base64,...\" or \"https://example.com/sign.png\" (optional),\n";
    $instructions .= "      \"choices\": [\n";
    $instructions .= "        { \"text\": \"Option A text\", \"next_node\": \"node_a\", \"score_impact\": 10 },\n";
    $instructions .= "        { \"text\": \"Option B text\", \"next_node\": \"node_b\", \"score_impact\": 0 }\n";
    $instructions .= "      ]\n";
    $instructions .= "    },\n";
    $instructions .= "    \"node_a\": { \"bot_message\": \"Feedback text for picking A.\", \"image\": \"optional visual URL\", \"choices\": [] },\n";
    $instructions .= "    \"node_b\": { \"bot_message\": \"Feedback text for picking B.\", \"choices\": [] }\n";
    $instructions .= "  }\n";
    $instructions .= "}\n";
    $instructions .= "Ensure the nodes flow logically. Final nodes must contain an empty choices array. Do not add any fields beyond this schema except an optional image field on a node. ";
    $instructions .= "When an uploaded image is provided, use it to describe and reinforce the relevant lesson or decision point. Keep the image field as a valid string value only.";

    $system_part = array("parts" => array(array("text" => $instructions)));
    $content_parts = array(array("text" => "Analyze this LTO source reference text:\n\n" . $raw_lto_text));

    if ($source_image !== null) {
        $content_parts[] = array(
            'inline_data' => array(
                'mime_type' => $source_image['mime_type'],
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
    curl_setopt($process, CURLOPT_TIMEOUT, 60);
    curl_setopt($process, CURLOPT_CONNECTTIMEOUT, 10);
    
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
            echo $trimmed_output;
            exit;
        }
    }

    if ($ai_json_payload !== null) {
        $trimmed_payload = trim($ai_json_payload);
        if (json_decode($trimmed_payload, true) === null) {
            echo json_encode([
                'error' => 'Gemini returned text that is not valid JSON. Please try again or copy the output manually.',
                'debug_log' => substr($trimmed_payload, 0, 512)
            ]);
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