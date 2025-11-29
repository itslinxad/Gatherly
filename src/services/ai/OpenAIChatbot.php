<?php

/**
 * GitHub Copilot Chatbot for Event Planning
 * Uses GitHub Copilot API to provide intelligent venue recommendations
 */

class CopilotChatbot
{
    private $db;
    private $provider;
    private $apiToken;
    private $apiEndpoint;
    private $model;
    private $maxTokens;
    private $temperature;

    public function __construct($dbConnection)
    {
        $this->db = $dbConnection;

        // Load configuration
        $config = require __DIR__ . '/../../../config/openai.php';
        $this->provider = $config['provider'] ?? 'openai';
        $this->maxTokens = $config['max_tokens'];
        $this->temperature = $config['temperature'];

        // Configure based on provider
        if ($this->provider === 'gemini') {
            $this->apiToken = $config['gemini_api_key'];
            $this->model = $config['gemini_model'];
            // Use v1 API (v1beta has model availability issues)
            $this->apiEndpoint = 'https://generativelanguage.googleapis.com/v1/models/' . $this->model . ':generateContent?key=' . $this->apiToken;

            if ($this->apiToken === 'your-gemini-api-key-here') {
                throw new Exception('Please get your FREE Gemini API key from https://aistudio.google.com/app/apikey and add it to config/openai.php');
            }
        } elseif ($this->provider === 'copilot') {
            $this->apiToken = $config['github_token'];
            $this->apiEndpoint = $config['copilot_endpoint'];
            $this->model = $config['copilot_model'];

            if ($this->apiToken === 'your-github-token-here') {
                throw new Exception('Please configure your GitHub token in config/openai.php');
            }
        } else {
            // Default to OpenAI
            $this->apiToken = $config['openai_api_key'];
            $this->apiEndpoint = $config['openai_endpoint'];
            $this->model = $config['openai_model'];

            if ($this->apiToken === 'your-openai-api-key-here') {
                throw new Exception('Please configure your OpenAI API key in config/openai.php');
            }
        }
    }

    /**
     * Get all venues from database with details
     */
    private function getVenuesFromDatabase()
    {
        $query = "
            SELECT 
                v.venue_id,
                v.venue_name,
                v.capacity,
                v.description,
                v.availability_status,
                v.suitable_themes,
                v.venue_type,
                v.ambiance,
                l.city,
                l.province,
                l.baranggay,
                p.base_price,
                p.weekday_price,
                p.weekend_price,
                p.peak_price,
                GROUP_CONCAT(DISTINCT a.amenity_name SEPARATOR ', ') as amenities,
                GROUP_CONCAT(DISTINCT vt.theme_name SEPARATOR ', ') as mapped_themes,
                pk.two_wheels,
                pk.four_wheels
            FROM venues v
            LEFT JOIN locations l ON v.location_id = l.location_id
            LEFT JOIN prices p ON v.venue_id = p.venue_id
            LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
            LEFT JOIN amenities a ON va.amenity_id = a.amenity_id
            LEFT JOIN parking pk ON v.venue_id = pk.venue_id
            LEFT JOIN venue_theme_mapping vtm ON v.venue_id = vtm.venue_id
            LEFT JOIN venue_themes vt ON vtm.theme_id = vt.theme_id
            WHERE v.status = 'active' AND v.availability_status = 'available'
            GROUP BY v.venue_id
            ORDER BY v.venue_name
        ";

        $result = $this->db->query($query);

        if (!$result) {
            throw new Exception('Database query failed: ' . $this->db->error);
        }

        $venues = [];
        while ($row = $result->fetch_assoc()) {
            $venues[] = $row;
        }

        return $venues;
    }

    /**
     * Format venues data for OpenAI context
     */
    private function formatVenuesForAI($venues)
    {
        $venuesList = [];

        foreach ($venues as $venue) {
            $location = trim(($venue['city'] ?? '') . ', ' . ($venue['province'] ?? ''));

            // Get themes from either suitable_themes or mapped_themes
            $themes = [];
            if (!empty($venue['mapped_themes'])) {
                $themes = explode(', ', $venue['mapped_themes']);
            } elseif (!empty($venue['suitable_themes'])) {
                $themes = explode(',', $venue['suitable_themes']);
                $themes = array_map('trim', $themes);
            }

            $venueInfo = [
                'id' => $venue['venue_id'],
                'name' => $venue['venue_name'],
                'capacity' => $venue['capacity'] . ' guests',
                'location' => $location ?: 'Location not specified',
                'venue_type' => $venue['venue_type'] ?: 'General',
                'ambiance' => $venue['ambiance'] ?: 'Versatile',
                'suitable_themes' => !empty($themes) ? $themes : ['General Events'],
                'prices' => [
                    'base' => '₱' . number_format($venue['base_price'], 2),
                    'weekday' => '₱' . number_format($venue['weekday_price'], 2),
                    'weekend' => '₱' . number_format($venue['weekend_price'], 2),
                    'peak' => '₱' . number_format($venue['peak_price'], 2),
                ],
                'amenities' => $venue['amenities'] ?: 'None listed',
                'parking' => [
                    'motorcycles' => $venue['two_wheels'] ?: 0,
                    'cars' => $venue['four_wheels'] ?: 0,
                ],
                'description' => $venue['description'] ?: 'No description available'
            ];

            $venuesList[] = $venueInfo;
        }

        return json_encode($venuesList, JSON_PRETTY_PRINT);
    }

    /**
     * Build system prompt with venue data
     */
    private function buildSystemPrompt()
    {
        $venues = $this->getVenuesFromDatabase();
        $venuesJson = $this->formatVenuesForAI($venues);

        return "You are an intelligent AI event planning assistant for Gatherly Event Management System. Your role is to help organizers find the perfect venue for their events.

AVAILABLE VENUES DATABASE:
{$venuesJson}

YOUR CAPABILITIES:
1. Recommend venues based on event type, guest count, budget, and preferences
2. Intelligently match venues to event themes (Wedding, Corporate, Birthday, etc.)
3. Compare venues and explain why they're suitable for specific themes
4. Provide detailed venue information including pricing, capacity, amenities, location, and ambiance
5. Answer questions about venue features, parking, and availability
6. Help users make informed decisions about their event venue

THEME-BASED RECOMMENDATION GUIDELINES:
- PRIORITY: Always match the event theme to venue's 'suitable_themes' field
- For WEDDINGS: Prioritize venues with 'Wedding' theme, romantic ambiance, garden setup, elegant features
- For CORPORATE: Prioritize venues with 'Corporate/Conference' themes, professional ambiance, presentation equipment
- For BIRTHDAYS: Prioritize venues with 'Birthday' theme, fun and flexible spaces
- For CONCERTS: Prioritize venues with 'Concert' theme, stage setup, good acoustics
- Consider venue_type (Garden, Ballroom, Conference Hall, etc.) when recommending
- Consider ambiance (Elegant, Rustic, Modern, Romantic, Professional, etc.) to match event mood

GENERAL RECOMMENDATION GUIDELINES:
- Always consider: event theme/type, number of guests, budget, and location preferences
- Prioritize venues where capacity is 100-150% of guest count (not too small, not too large)
- For budget recommendations, suggest venues where the base price is around 35-40% of total budget
- Match amenities to event needs (e.g., stage for concerts, garden for weddings, projector for conferences)
- IMPORTANT: When mentioning venues, use their NAMES (e.g., 'Shepherd\'s Events Garden')
- NEVER show or mention venue IDs in your response text
- Recommend top 3 venues with detailed explanations
- Explain WHY each venue matches the requested theme based on its suitable_themes, venue_type, and ambiance

RESPONSE FORMAT:
- Be conversational and helpful
- Ask clarifying questions if information is missing
- When recommending venues, you MUST include this special marker at the end of your response:
  
  [RECOMMENDED_VENUES: 1,2,3]
  
  Replace the numbers with the actual venue IDs you're recommending (comma-separated, no spaces)
  This marker should be on its own line at the very end of your response
  
- Structure venue recommendations clearly:
  * Venue name (bold)
  * Why it's a good match
  * Key features (capacity, pricing, amenities)
  * Any considerations or alternatives
- Use markdown formatting for better readability

CONVERSATION FLOW:
1. Greet warmly and ask about their event
2. Gather: event type, guest count, budget (optional), date (optional), specific requirements
3. Analyze venues from the database
4. Recommend top 3 best matches with detailed reasoning
5. Answer follow-up questions and help with final decision

Be friendly, professional, and focus on finding the best venue match from the available options.";
    }

    /**
     * Call AI API (OpenAI, Copilot, or Gemini)
     */
    private function callOpenAI($messages)
    {
        if ($this->provider === 'gemini') {
            return $this->callGemini($messages);
        }

        // OpenAI/Copilot format
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'stream' => false,
        ];

        // Build headers based on provider
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiToken,
        ];

        if ($this->provider === 'copilot') {
            // Add GitHub Copilot specific headers
            $headers[] = 'Editor-Version: vscode/1.95.0';
            $headers[] = 'Editor-Plugin-Version: copilot-chat/0.12.0';
            $headers[] = 'Openai-Organization: github-copilot';
            $headers[] = 'Openai-Intent: conversation-panel';
        }

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL error: ' . $error);
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? $errorData['message'] ?? 'Unknown error';

            // Log full error for debugging
            error_log(ucfirst($this->provider) . ' API Error: ' . $response);

            throw new Exception(ucfirst($this->provider) . ' API error (HTTP ' . $httpCode . '): ' . $errorMessage . '. Check error log for details.');
        }

        $result = json_decode($response, true);

        if (!isset($result['choices'][0]['message']['content'])) {
            error_log('Invalid ' . $this->provider . ' response: ' . $response);
            throw new Exception('Invalid response from ' . ucfirst($this->provider) . ' API');
        }

        return $result['choices'][0]['message']['content'];
    }

    /**
     * Call Google Gemini API
     */
    private function callGemini($messages)
    {
        // Convert messages to Gemini format
        $contents = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                // Gemini doesn't have system role, prepend to first user message
                continue;
            }
            $contents[] = [
                'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $msg['content']]]
            ];
        }

        // Prepend system message to first user message if exists
        $systemMsg = '';
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMsg = $msg['content'] . "\n\n";
                break;
            }
        }
        if ($systemMsg && !empty($contents)) {
            $contents[0]['parts'][0]['text'] = $systemMsg . $contents[0]['parts'][0]['text'];
        }

        $data = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
            ]
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL error: ' . $error);
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
            error_log('Gemini API Error: ' . $response);
            throw new Exception('Gemini API error (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $result = json_decode($response, true);

        if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('Invalid Gemini response: ' . $response);
            throw new Exception('Invalid response from Gemini API');
        }

        return $result['candidates'][0]['content']['parts'][0]['text'];
    }

    /**
     * Process conversation with AI
     */
    public function chat($userMessage, $conversationHistory = [])
    {
        try {
            // Build messages array
            $messages = [];

            // Add system prompt
            $messages[] = [
                'role' => 'system',
                'content' => $this->buildSystemPrompt()
            ];

            // Add conversation history
            if (!empty($conversationHistory)) {
                foreach ($conversationHistory as $msg) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
            }

            // Add current user message
            $messages[] = [
                'role' => 'user',
                'content' => $userMessage
            ];

            // Call OpenAI API
            $aiResponse = $this->callOpenAI($messages);

            // Extract venue IDs from response if present
            $venueIds = $this->extractVenueIds($aiResponse);

            // Remove the marker from the response before displaying
            $cleanResponse = preg_replace('/\[RECOMMENDED_VENUES:\s*[\d,]+\]/i', '', $aiResponse);
            $cleanResponse = trim($cleanResponse);

            // Get venue details if IDs were mentioned
            $venues = [];
            if (!empty($venueIds)) {
                $venues = $this->getVenuesByIds($venueIds);
            }

            return [
                'success' => true,
                'response' => $cleanResponse,
                'venues' => $venues,
                'has_recommendations' => !empty($venues),
                'recommended_venue_ids' => $venueIds
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'response' => 'I apologize, but I encountered an error processing your request. Please try again or contact support if the issue persists.'
            ];
        }
    }

    /**
     * Extract venue IDs from AI response
     */
    private function extractVenueIds($response)
    {
        $venueIds = [];

        // Look for the special marker: [RECOMMENDED_VENUES: 1,2,3]
        if (preg_match('/\[RECOMMENDED_VENUES:\s*([\d,]+)\]/i', $response, $matches)) {
            $ids = explode(',', $matches[1]);
            $venueIds = array_unique(array_map('intval', array_map('trim', $ids)));
        }

        return $venueIds;
    }

    /**
     * Get venue details by IDs
     */
    private function getVenuesByIds($venueIds)
    {
        if (empty($venueIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($venueIds), '?'));

        $query = "
            SELECT 
                v.venue_id,
                v.venue_name,
                v.capacity,
                v.description,
                l.city,
                l.province,
                p.base_price,
                p.weekday_price,
                p.weekend_price,
                GROUP_CONCAT(DISTINCT a.amenity_name SEPARATOR ', ') as amenities
            FROM venues v
            LEFT JOIN locations l ON v.location_id = l.location_id
            LEFT JOIN prices p ON v.venue_id = p.venue_id
            LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
            LEFT JOIN amenities a ON va.amenity_id = a.amenity_id
            WHERE v.venue_id IN ({$placeholders})
            GROUP BY v.venue_id
        ";

        $stmt = $this->db->prepare($query);

        if (!$stmt) {
            throw new Exception('Failed to prepare statement: ' . $this->db->error);
        }

        // Bind parameters dynamically
        $types = str_repeat('i', count($venueIds));
        $stmt->bind_param($types, ...$venueIds);

        $stmt->execute();
        $result = $stmt->get_result();

        $venues = [];
        while ($row = $result->fetch_assoc()) {
            $venues[] = $row;
        }

        $stmt->close();

        return $venues;
    }

    /**
     * Get conversation starter
     */
    public function getGreeting()
    {
        $providerMap = [
            'gemini' => ['name' => 'Google Gemini', 'model' => 'Gemini Pro'],
            'copilot' => ['name' => 'GitHub Copilot', 'model' => 'GPT-4'],
            'openai' => ['name' => 'OpenAI', 'model' => $this->model]
        ];
        $provider = $providerMap[$this->provider] ?? ['name' => 'AI', 'model' => 'Advanced'];
        $providerName = $provider['name'];
        $modelInfo = $provider['model'];

        return [
            'success' => true,
            'response' => "👋 Hello! I'm your AI event planning assistant powered by {$providerName} ({$modelInfo}). I'm here to help you find the perfect venue for your event!\n\nI can recommend venues based on:\n• Event type (Wedding, Corporate, Birthday, Concert, etc.)\n• Number of guests\n• Budget\n• Location preferences\n• Specific amenities you need\n\nTell me about your event, and I'll suggest the best venues from our database! What kind of event are you planning?",
            'venues' => [],
            'has_recommendations' => false
        ];
    }
}
