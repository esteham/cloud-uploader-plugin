<?php
class Cloud_Uploader_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_page() {
        add_options_page(
            'Cloud Uploader Settings',
            'Cloud Uploader',
            'manage_options',
            'cloud-uploader-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('cloud_uploader_options', 'cloud_uploader_settings');
        
        add_settings_section(
            'cloud_storage_settings',
            'Cloud Storage Configuration',
            array($this, 'render_section'),
            'cloud-uploader-settings'
        );
        
        // Add fields for different cloud providers
        $this->add_settings_field('storage_provider', 'Storage Provider', 'render_provider_field');
        $this->add_settings_field('aws_access_key', 'AWS Access Key', 'render_text_field', 'aws');
        $this->add_settings_field('aws_secret_key', 'AWS Secret Key', 'render_text_field', 'aws');
        $this->add_settings_field('aws_bucket_name', 'AWS Bucket Name', 'render_text_field', 'aws');
        // Add similar fields for other providers
    }
    
    private function add_settings_field($id, $title, $callback, $provider = '') {
        add_settings_field(
            $id,
            $title,
            array($this, $callback),
            'cloud-uploader-settings',
            'cloud_storage_settings',
            array(
                'label_for' => $id,
                'provider' => $provider,
                'class' => 'cloud-uploader-row'
            )
        );
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Cloud Uploader Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cloud_uploader_options');
                do_settings_sections('cloud-uploader-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function render_section() {
        echo '<p>Configure your cloud storage provider settings</p>';
    }
    
    public function render_provider_field($args) {
        $options = get_option('cloud_uploader_settings');
        $current = $options[$args['label_for']] ?? '';
        ?>
        <select id="<?php echo esc_attr($args['label_for']); ?>" 
                name="cloud_uploader_settings[<?php echo esc_attr($args['label_for']); ?>]">
            <option value="aws" <?php selected($current, 'aws'); ?>>AWS S3</option>
            <option value="google" <?php selected($current, 'google'); ?>>Google Cloud</option>
            <option value="digitalocean" <?php selected($current, 'digitalocean'); ?>>DigitalOcean Spaces</option>
        </select>
        <?php
    }
    
    public function render_text_field($args) {
        $options = get_option('cloud_uploader_settings');
        $value = $options[$args['label_for']] ?? '';
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" 
               name="cloud_uploader_settings[<?php echo esc_attr($args['label_for']); ?>]" 
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
    }
}