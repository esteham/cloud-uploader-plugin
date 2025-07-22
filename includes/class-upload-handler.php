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
        try {
            // Verify nonce first
            if (!check_ajax_referer('cloud_uploader_nonce', 'nonce', false)) {
                throw new Exception('Security check failed - invalid nonce');
            }

            // Debug: Log entire $_FILES structure
            error_log('Cloud Uploader: $_FILES content - ' . print_r($_FILES, true));

            // Check if files were uploaded
            if (!isset($_FILES['cloud_uploader_files']) || empty($_FILES['cloud_uploader_files']['name'][0])) {
                error_log('Cloud Uploader: No valid files found in upload');
                throw new Exception('No valid files received');
            }

            $uploaded_files = array();
            $file_count = count($_FILES['cloud_uploader_files']['name']);

            error_log('Cloud Uploader: Processing ' . $file_count . ' files');

            for ($i = 0; $i < $file_count; $i++) {
                // Skip empty files
                if ($_FILES['cloud_uploader_files']['error'][$i] === UPLOAD_ERR_NO_FILE || 
                    empty($_FILES['cloud_uploader_files']['tmp_name'][$i])) {
                    error_log('Cloud Uploader: Skipping empty file at index ' . $i);
                    continue;
                }

                // Check for upload errors
                if ($_FILES['cloud_uploader_files']['error'][$i] !== UPLOAD_ERR_OK) {
                    $error_msg = $this->get_upload_error($_FILES['cloud_uploader_files']['error'][$i]);
                    error_log('Cloud Uploader: File upload error for ' . $_FILES['cloud_uploader_files']['name'][$i] . ': ' . $error_msg);
                    throw new Exception('File upload error: ' . $error_msg);
                }

                // Verify file was actually uploaded
                if (!is_uploaded_file($_FILES['cloud_uploader_files']['tmp_name'][$i])) {
                    error_log('Cloud Uploader: Possible file upload attack for ' . $_FILES['cloud_uploader_files']['name'][$i]);
                    throw new Exception('Invalid file upload detected');
                }

                $file = array(
                    'name' => $_FILES['cloud_uploader_files']['name'][$i],
                    'type' => $_FILES['cloud_uploader_files']['type'][$i],
                    'tmp_name' => $_FILES['cloud_uploader_files']['tmp_name'][$i],
                    'error' => $_FILES['cloud_uploader_files']['error'][$i],
                    'size' => $_FILES['cloud_uploader_files']['size'][$i]
                );

                error_log('Cloud Uploader: Processing file: ' . $file['name']);

                $upload_result = $this->upload_to_cloud($file);
                if ($upload_result) {
                    error_log('Cloud Uploader: Successfully uploaded: ' . $file['name']);
                    $uploaded_files[] = $upload_result;
                } else {
                    error_log('Cloud Uploader: Failed to upload: ' . $file['name']);
                }
            }

            if (empty($uploaded_files)) {
                error_log('Cloud Uploader: No files were successfully processed');
                throw new Exception('No files were successfully uploaded. Please check server logs for details.');
            }

            wp_send_json_success(array(
                'message' => count($uploaded_files) . ' file(s) uploaded successfully',
                'files' => $uploaded_files
            ));

        } catch (Exception $e) {
            error_log('Cloud Uploader Error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }
    
    private function get_upload_error($error_code) {
        $errors = array(
            UPLOAD_ERR_INI_SIZE => 'File is too large',
            UPLOAD_ERR_FORM_SIZE => 'File is too large',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        );
        return $errors[$error_code] ?? 'Unknown upload error';
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
        try {
            // Verify AWS SDK is available
            if (!class_exists('Aws\S3\S3Client')) {
                $sdk_path = CLOUD_UPLOADER_PLUGIN_DIR . 'vendor/autoload.php';
                if (file_exists($sdk_path)) {
                    require_once $sdk_path;
                } else {
                    throw new Exception('AWS SDK not found. Please install it using: composer require aws/aws-sdk-php');
                }
            }

            // Verify credentials
            if (empty($settings['aws_access_key']) || empty($settings['aws_secret_key']) || empty($settings['aws_bucket_name'])) {
                throw new Exception('AWS credentials not configured');
            }

            // Create S3 client with error handling
            $s3 = new Aws\S3\S3Client([
                'version' => 'latest',
                'region'  => $settings['aws_region'] ?? 'us-east-1',
                'credentials' => [
                    'key'    => $settings['aws_access_key'],
                    'secret' => $settings['aws_secret_key'],
                ]
            ]);

            // Generate unique file key
            $file_key = 'uploads/' . uniqid() . '_' . sanitize_file_name($file['name']);
            error_log('Cloud Uploader: Uploading to S3 with key: ' . $file_key);

            // Verify temporary file exists and is readable
            if (!file_exists($file['tmp_name']) || !is_readable($file['tmp_name'])) {
                throw new Exception('Temporary file not found or not readable');
            }

            // Upload to S3 with error handling
            $result = $s3->putObject([
                'Bucket' => $settings['aws_bucket_name'],
                'Key'    => $file_key,
                'Body'   => fopen($file['tmp_name'], 'rb'),
                'ACL'    => 'public-read',
            ]);

            // Verify upload was successful
            if (!isset($result['ObjectURL'])) {
                throw new Exception('S3 upload failed - no ObjectURL returned');
            }

            return array(
                'name' => $file['name'],
                'url' => $result['ObjectURL'],
                'size' => $file['size'],
                'type' => $file['type']
            );

        } catch (Exception $e) {
            error_log('AWS Upload Error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function upload_to_google($file, $settings) {
        // Implementation for Google Cloud Storage
        throw new Exception('Google Cloud Storage support not yet implemented');
    }
    
    private function upload_to_digitalocean($file, $settings) {
        // Implementation for DigitalOcean Spaces
        throw new Exception('DigitalOcean Spaces support not yet implemented');
    }
}