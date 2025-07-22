<?php
class Cloud_Uploader_Shortcodes {
    public function __construct() {
        add_shortcode('cloud_uploader', array($this, 'render_upload_form'));
    }
    
    public function render_upload_form($atts) {
        $atts = shortcode_atts(array(
            'multiple' => true,
            'max_size' => '10MB',
            'allowed_types' => '*'
        ), $atts);
        
        ob_start();
        ?>
        <div class="cloud-uploader-container">
            <form id="cloud-uploader-form" enctype="multipart/form-data">
                <div class="upload-area">
                    <input type="file" name="cloud_uploader_files[]" id="cloud-uploader-files" 
                           <?php echo $atts['multiple'] ? 'multiple' : ''; ?>>
                    <label for="cloud-uploader-files">
                        <div class="drop-zone">
                            <span class="drop-zone__prompt">Drop files here or click to upload</span>
                        </div>
                    </label>
                </div>
                <div class="upload-info">
                    <p>Max file size: <?php echo esc_html($atts['max_size']); ?></p>
                    <p>Allowed types: <?php echo esc_html($atts['allowed_types']); ?></p>
                </div>
                <button type="submit" class="upload-button">Upload Files</button>
                <div class="progress-container" style="display: none;">
                    <div class="progress-bar"></div>
                    <div class="progress-text">0%</div>
                </div>
                <div class="upload-results"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}