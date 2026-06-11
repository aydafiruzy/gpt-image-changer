<?php
/**
 * ChatGPT API handler class
 *
 * @package GPT_Image_Changer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ChatGPT API handler class
 */
class GPT_Image_Changer_API {
    /**
     * Debug mode flag
     *
     * @var bool
     */
    private $debug_mode = false;
    
    /**
     * Last debug information
     *
     * @var array
     */
    private $last_debug_info = array();
    
    /**
     * API key
     * 
     * @var string
     */
    private $api_key = '';
    
    /**
     * Initialize the API handler
     */
    public function init() {
        // No actions needed for initialization
        $settings = get_option('gic_settings', array());
        $this->debug_mode = isset($settings['debug_mode']) ? (bool) $settings['debug_mode'] : false;
    }
    
    /**
     * Set debug mode
     *
     * @param bool $debug_mode Debug mode setting
     */
    public function set_debug_mode($debug_mode) {
        $this->debug_mode = (bool) $debug_mode;
    }
    
    /**
     * Get last debug info
     *
     * @return array Debug information
     */
    public function get_last_debug_info() {
        return $this->last_debug_info;
    }
    
    /**
     * Set API key for testing
     *
     * @param string $api_key API key to set
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Process an image using the ChatGPT API
     *
     * @param int $image_id Image ID to process
     * @param string $product_context Product context information
     * @return array|WP_Error Result data or error
     */
    public function process_image($image_id, $product_context = '') {
        // Reset debug info
        $this->last_debug_info = array();
        
        // Get API key
        $settings = get_option('gic_settings', array());
        $api_key = !empty($this->api_key) ? $this->api_key : (isset($settings['api_key']) ? $settings['api_key'] : '');
        $gpt_model = isset($settings['gpt_model']) ? $settings['gpt_model'] : 'gpt-4-vision-preview';
        
        // Check if API key is set
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', __('ChatGPT API key is missing.', 'gpt-image-changer'));
        }
        
        // Get image URL
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            return new WP_Error('image_not_found', __('Image not found.', 'gpt-image-changer'));
        }
        
        // Debug info
        if ($this->debug_mode) {
            $this->last_debug_info['timing'] = array(
                'start' => time(),
            );
        }
        
        // Get current image data for context
        $attachment = get_post($image_id);
        $image_title = $attachment ? $attachment->post_title : '';
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        $image_caption = $attachment ? $attachment->post_excerpt : '';
        $image_description = $attachment ? $attachment->post_content : '';
        
        // Get current filename
        $current_filename = basename(get_attached_file($image_id));
        
        // Debug image info
        if ($this->debug_mode) {
            $this->last_debug_info['image_info'] = array(
                'id' => $image_id,
                'url' => $image_url,
                'title' => $image_title,
                'alt_text' => $image_alt,
                'caption' => $image_caption,
                'description' => $image_description,
                'filename' => $current_filename
            );
        }
        
        // Build the message
        $system_prompt = "You are an SEO specialist with expertise in image optimization. Analyze the image and create SEO-optimized metadata to improve search visibility.";
        
        $user_prompt = "Analyze this image and create SEO-optimized metadata. Return your response as JSON format with these fields:
- title: A concise SEO-optimized title for the image (max 60 chars)
- alt_text: Descriptive alt text that incorporates relevant keywords (max 125 chars)
- caption: A brief caption for the image (max 150 chars, can be empty if not relevant)
- description: A longer SEO-friendly description (max 250 chars, can be empty if not relevant)
- filename: A new SEO-friendly filename without spaces (use hyphens instead) and without extension (max 50 chars)

Current data:
- Current title: {$image_title}
- Current alt text: {$image_alt}
- Current filename: {$current_filename}";

        // Add product context if available
        if (!empty($product_context)) {
            $user_prompt .= "\n\nProduct context: {$product_context}";
        }
        
        $user_prompt .= "\n\nYour JSON response must be proper valid JSON format. Include only these fields without any markdown formatting or additional text.";
        
        $max_tokens = isset($settings['gic_max_tokens']) ? $settings['gic_max_tokens'] : 300;
        
        $payload = array(
            'model' => $gpt_model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => array(
                        array(
                            'type' => 'text',
                            'text' => $user_prompt
                        ),
                        array(
                            'type' => 'image_url',
                            'image_url' => array(
                                'url' => $image_url
                            )
                        )
                    )
                )
            ),
            'max_tokens' => (int) $max_tokens,
            'response_format' => array('type' => 'json_object')
        );
        
        // Debug request info
        if ($this->debug_mode) {
            $this->last_debug_info['request'] = $payload;
        }
        
        // Call the API
        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'timeout' => 60,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($payload),
            )
        );
        
        // Check for errors
        if (is_wp_error($response)) {
            if ($this->debug_mode) {
                $this->last_debug_info['error'] = array(
                    'type' => 'wp_error',
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                );
                
                $this->last_debug_info['timing']['end'] = time();
                $this->last_debug_info['timing']['duration'] = $this->last_debug_info['timing']['end'] - $this->last_debug_info['timing']['start'];
            }
            
            $this->log_api_usage($image_id, 0, $gpt_model, 'error');
            return $response;
        }
        
        // Parse response
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Debug info for response
        if ($this->debug_mode) {
            $this->last_debug_info['response'] = array(
                'code' => $response_code,
                'body' => $response_data
            );
            
            $this->last_debug_info['timing']['end'] = time();
            $this->last_debug_info['timing']['duration'] = $this->last_debug_info['timing']['end'] - $this->last_debug_info['timing']['start'];
        }
        
        // Check for API errors
        if ($response_code !== 200 || !isset($response_data['choices'][0]['message']['content'])) {
            $error_message = isset($response_data['error']['message']) 
                ? $response_data['error']['message'] 
                : __('Unknown API error.', 'gpt-image-changer');
            
            if ($this->debug_mode) {
                $this->last_debug_info['error'] = array(
                    'type' => 'api_error',
                    'message' => $error_message,
                    'code' => $response_code
                );
            }
                
            $this->log_api_usage($image_id, 0, $gpt_model, 'error');
            return new WP_Error('api_error', $error_message, array('status' => $response_code));
        }
        
        // Extract and parse JSON from the content
        $content = $response_data['choices'][0]['message']['content'];
        $usage = isset($response_data['usage']) ? $response_data['usage'] : array();
        $total_tokens = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0;
        
        // Try to parse the JSON
        $data = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            // Log API usage
            $this->log_api_usage($image_id, $total_tokens, $gpt_model, 'success');
            
            // Ensure all required fields are present
            $result = array(
                'title' => isset($data['title']) ? $data['title'] : $image_title,
                'alt_text' => isset($data['alt_text']) ? $data['alt_text'] : $image_alt,
                'caption' => isset($data['caption']) ? $data['caption'] : $image_caption,
                'description' => isset($data['description']) ? $data['description'] : $image_description,
                'filename' => isset($data['filename']) ? $data['filename'] : pathinfo($current_filename, PATHINFO_FILENAME)
            );
            
            return $result;
        }
        
        // If JSON parsing failed, log and return error
        $this->log_api_usage($image_id, $total_tokens, $gpt_model, 'parse_error');
        
        if ($this->debug_mode) {
            $this->last_debug_info['error'] = array(
                'type' => 'parse_error',
                'message' => json_last_error_msg(),
                'content' => $content
            );
        }
        
        return new WP_Error(
            'invalid_response', 
            __('Could not parse the response from ChatGPT.', 'gpt-image-changer'),
            array('content' => $content)
        );
    }
    
    /**
     * Validate API key
     *
     * @param string $api_key API key to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_api_key($api_key = null) {
        // Reset debug info
        $this->last_debug_info = array();
        
        if ($api_key === null) {
            $api_key = $this->api_key;
        }
        
        if (empty($api_key)) {
            return new WP_Error('api_key_empty', __('API key is empty.', 'gpt-image-changer'));
        }
        
        // Make a simple models list request to validate the key
        $response = wp_remote_get(
            'https://api.openai.com/v1/models',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
            )
        );
        
        // Debug info for timing
        if ($this->debug_mode) {
            $this->last_debug_info = array(
                'request' => array(
                    'url' => 'https://api.openai.com/v1/models',
                    'method' => 'GET'
                ),
                'timing' => array(
                    'start' => time(),
                    'end' => time(),
                    'duration' => 0
                )
            );
        }
        
        // Check for errors
        if (is_wp_error($response)) {
            if ($this->debug_mode) {
                $this->last_debug_info['error'] = array(
                    'type' => 'wp_error',
                    'message' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                );
            }
            
            return $response;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($this->debug_mode) {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            $this->last_debug_info['response'] = array(
                'code' => $response_code,
                'body' => $response_data
            );
        }
        
        if ($response_code === 200) {
            return true;
        }
        
        // Parse response for error message
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        $error_message = isset($response_data['error']['message']) 
            ? $response_data['error']['message'] 
            : __('Invalid API key or API error.', 'gpt-image-changer');
            
        if ($this->debug_mode) {
            $this->last_debug_info['error'] = array(
                'type' => 'api_error',
                'message' => $error_message,
                'code' => $response_code
            );
        }
            
        return new WP_Error('api_key_invalid', $error_message, array('status' => $response_code));
    }

    /**
     * Log API usage
     *
     * @param int $image_id Image ID
     * @param int $tokens Tokens used
     * @param string $model Model used
     * @param string $status Status of the request
     * @return void
     */
    private function log_api_usage($image_id, $tokens, $model, $status) {
        $usage_log = get_option('gic_api_usage_log', array());
        
        $usage_log[] = array(
            'timestamp' => time(),
            'image_id' => $image_id,
            'tokens' => $tokens,
            'model' => $model,
            'status' => $status,
        );
        
        // Keep only the latest 200 entries
        if (count($usage_log) > 200) {
            $usage_log = array_slice($usage_log, -200);
        }
        
        // Add debug info if enabled
        if ($this->debug_mode) {
            $usage_log[count($usage_log) - 1]['debug'] = $this->last_debug_info;
        }
        
        update_option('gic_api_usage_log', $usage_log);
        
        // Also log to the debug log file if debug mode is enabled
        if ($this->debug_mode && defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[GPT Image Changer] API request - Image ID: %d, Model: %s, Tokens: %d, Status: %s',
                $image_id,
                $model,
                $tokens,
                $status
            ));
        }
    }
} 