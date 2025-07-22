<?php
class Cloud_Uploader_Handler {
    private $storage_provider;
    
    public function __construct() {
        $this->storage_provider = $this->get_storage_provider();
        
        add_action('wp_ajax_handle_file_upload', array($this, 'handle_upload'));
        add_action('wp_ajax_nopriv_handle_file_upload', array($this, 'handle_upload'));
    }
    
    private function get_storage_provider() {
        $settings = get_option('cloud_uploader_settings');
        return $settings['storage_provider'] ?? 'aws';
    }
    
    public function handle_upload() {
    // Debug: Check if the request is reaching the handler
    error_log('Upload handler reached');
    
    check_ajax_referer('cloud_uploader_nonce', 'nonce');
    
    // Debug: Check $_FILES and $_POST data
    error_log('$_FILES content: ' . print_r($_FILES, true));
    error_log('$_POST content: ' . print_r($_POST, true));

        if (empty($_FILES)) {
            wp_send_json_error('No files uploaded');
        }
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded_files = array();
        
        foreach ($_FILES as $file) {
            $upload_result = $this->upload_to_cloud($file);
            if ($upload_result) {
                $uploaded_files[] = $upload_result;
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Files uploaded successfully',
            'files' => $uploaded_files
        ));
    }
    
    private function upload_to_cloud($file) {
        $settings = get_option('cloud_uploader_settings');
        
        try {
            switch ($this->storage_provider) {
                case 'aws':
                    return $this->upload_to_aws($file, $settings);
                case 'google':
                    return $this->upload_to_google($file, $settings);
                case 'digitalocean':
                    return $this->upload_to_digitalocean($file, $settings);
                default:
                    throw new Exception('Invalid storage provider');
            }
        } catch (Exception $e) {
            error_log('Cloud Upload Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function upload_to_aws($file, $settings) {
        if (!class_exists('Aws\S3\S3Client')) {
            require_once CLOUD_UPLOADER_PLUGIN_DIR . 'vendor/autoload.php';
        }
        
        $s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $settings['aws_region'] ?? 'us-east-1',
            'credentials' => [
                'key'    => $settings['aws_access_key'],
                'secret' => $settings['aws_secret_key'],
            ],
        ]);
        
        $file_key = 'uploads/' . uniqid() . '_' . sanitize_file_name($file['name']);
        
        $result = $s3->putObject([
            'Bucket' => $settings['aws_bucket_name'],
            'Key'    => $file_key,
            'Body'   => fopen($file['tmp_name'], 'rb'),
            'ACL'    => 'public-read',
        ]);
        
        return array(
            'name' => $file['name'],
            'url' => $result['ObjectURL'],
            'size' => $file['size'],
            'type' => $file['type']
        );
    }
    
    // Similar methods for Google Cloud and DigitalOcean...
}