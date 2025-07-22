jQuery(document).ready(function($) {
    // Handle drag and drop
    const dropZone = $('.drop-zone');
    const fileInput = $('#cloud-uploader-files')[0];
    
    dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('drop-zone--over');
    });
    
    ['dragleave', 'dragend'].forEach(type => {
        dropZone.on(type, function() {
            $(this).removeClass('drop-zone--over');
        });
    });
    
    dropZone.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('drop-zone--over');
        
        if (e.originalEvent.dataTransfer.files.length) {
            fileInput.files = e.originalEvent.dataTransfer.files;
            updateFileList(fileInput.files);
        }
    });
    
    fileInput.addEventListener('change', function() {
        updateFileList(this.files);
    });
    
    function updateFileList(files) {
        const fileList = $('<ul class="file-list"></ul>');
        
        for (let i = 0; i < files.length; i++) {
            fileList.append(`
                <li>
                    <span class="file-name">${files[i].name}</span>
                    <span class="file-size">${formatFileSize(files[i].size)}</span>
                </li>
            `);
        }
        
        $('.upload-area').find('.file-list').remove();
        $('.upload-area').append(fileList);
    }
    
    // Handle form submission
    $('#cloud-uploader-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const files = fileInput.files;
        
        if (files.length === 0) {
            showUploadError('Please select at least one file');
            return;
        }
        
        // Add required parameters
        formData.append('action', 'handle_file_upload');
        formData.append('nonce', cloudUploader.nonce);
        
        // Add files
        for (let i = 0; i < files.length; i++) {
            formData.append('cloud_uploader_files[]', files[i]);
        }
        
        $('.progress-container').show();
        $('.upload-button').prop('disabled', true);
        $('.progress-bar').css('width', '0%');
        $('.progress-text').text('0%');
        
        $.ajax({
            url: cloudUploader.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        $('.progress-bar').css('width', percent + '%');
                        $('.progress-text').text(percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    displayUploadResults(response.data.files);
                } else {
                    showUploadError(response.data || 'Unknown error occurred');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Upload failed';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage += ': ' + response.data;
                    } else if (xhr.statusText) {
                        errorMessage += ': ' + xhr.statusText;
                    }
                } catch (e) {
                    errorMessage += ': Server error occurred';
                }
                showUploadError(errorMessage);
            },
            complete: function() {
                $('.progress-container').hide();
                $('.upload-button').prop('disabled', false);
            }
        });
    });
    
    function showUploadError(message) {
        const errorDiv = $('<div class="upload-error">')
            .text(message)
            .css({
                'color': 'red',
                'margin-top': '10px',
                'padding': '10px',
                'border': '1px solid red',
                'border-radius': '4px'
            });
        
        $('.upload-results').empty().append(errorDiv);
        setTimeout(() => errorDiv.fadeOut(), 5000);
    }
    
    function displayUploadResults(files) {
        const resultsContainer = $('.upload-results');
        resultsContainer.empty();
        
        if (!files || files.length === 0) {
            resultsContainer.append('<p>No files were uploaded</p>');
            return;
        }
        
        const list = $('<ul class="uploaded-files-list"></ul>');
        
        files.forEach(file => {
            list.append(`
                <li class="uploaded-file">
                    <div class="file-info">
                        <span class="file-name">${file.name}</span>
                        <span class="file-size">${formatFileSize(file.size)}</span>
                    </div>
                    <div class="file-actions">
                        <a href="${file.url}" target="_blank" class="view-link">View</a>
                        <button class="copy-link" data-url="${file.url}">Copy Link</button>
                    </div>
                </li>
            `);
        });
        
        resultsContainer.append(list);
        
        // Add copy link functionality
        $('.copy-link').on('click', function() {
            const url = $(this).data('url');
            navigator.clipboard.writeText(url).then(() => {
                $(this).text('Copied!');
                setTimeout(() => {
                    $(this).text('Copy Link');
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy URL: ', err);
            });
        });
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});